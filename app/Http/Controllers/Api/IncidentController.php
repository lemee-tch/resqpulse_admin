<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Citizen;
use App\Models\Incident;
use App\Models\Responder;
use App\Services\AuditLogService;
use App\Services\BarangayLocationService;
use App\Services\ImageAnalysisService;
use App\Services\IncidentReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

class IncidentController extends Controller
{
    /** A responder's Final report is with MDRRMO (status for_review). */
    private const UNDER_REVIEW_MESSAGE = 'Your Final report is with MDRRMO for review. You can only change it if MDRRMO sends it back to you.';

    /**
     * Resolves the citizen from the bearer token if one was sent, WITHOUT
     * rejecting the request when it's absent or invalid. This is what
     * lets store()/sos() serve both logged-in citizens and guests from
     * the exact same endpoint — see api.php, where these two routes were
     * deliberately pulled out of the auth:sanctum group.
     */
    private function resolveOptionalCitizen(Request $request): ?Citizen
    {
        $token = $request->bearerToken();
        if (! $token) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);

        return $accessToken?->tokenable instanceof Citizen ? $accessToken->tokenable : null;
    }

    /**
     * Guests (no account) must give their full name and mobile number
     * before an SOS or a report goes through, so MDRRMO can call them back
     * to confirm it and pranks can be traced to someone. Logged-in
     * residents skip this — their account already has both.
     *
     * Tidies the two fields first (extra spaces, "+63" numbers), then
     * returns [rules, messages] to add to the request's validation.
     */
    private function prepareGuestDetails(Request $request, bool $isGuest): array
    {
        if (! $isGuest) {
            return [[], []];
        }

        $name   = $request->input('guest_name');
        $mobile = $request->input('guest_mobile');
        $request->merge([
            'guest_name'   => is_string($name) ? preg_replace('/\s+/u', ' ', trim($name)) : $name,
            'guest_mobile' => is_string($mobile) ? $this->normalizeMobile($mobile) : $mobile,
        ]);

        return [
            [
                // At least two words (first and last name), letters only.
                'guest_name'   => ['required', 'string', 'min:5', 'max:120', 'regex:/^[\pL\pM][\pL\pM.\'-]*(\s[\pL\pM][\pL\pM.\'-]*)+$/u'],
                'guest_mobile' => ['required', 'string', 'regex:/^09\d{9}$/'],
            ],
            [
                'guest_name.required'   => 'Enter your full name before sending.',
                'guest_name.min'        => 'Enter your full name (first and last name).',
                'guest_name.max'        => 'Your name is too long.',
                'guest_name.regex'      => 'Enter your full name (first and last name), using letters only.',
                'guest_mobile.required' => 'Enter your mobile number before sending.',
                'guest_mobile.regex'    => 'Enter a valid mobile number, e.g. 09171234567.',
            ],
        ];
    }

    /** "0917 123 4567", "+639171234567" and "639171234567" all become "09171234567". */
    private function normalizeMobile(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile);

        if (strlen($digits) === 12 && str_starts_with($digits, '63')) {
            return '0' . substr($digits, 2);
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            return '0' . $digits;
        }

        return $digits;
    }

    /**
     * The guest's details for Incident::create(). Left out if the
     * guest_name / guest_mobile columns haven't been added to the database
     * yet (see the add_guest_details migration), so an SOS never fails over
     * it. The name and number also go in the description and the audit
     * log, so MDRRMO sees them either way.
     */
    private function guestColumns(?array $guest): array
    {
        if (! $guest || ! $this->hasGuestColumns()) {
            return [];
        }

        return ['guest_name' => $guest['name'], 'guest_mobile' => $guest['mobile']];
    }

    private ?bool $guestColumnsExist = null;

    private function hasGuestColumns(): bool
    {
        return $this->guestColumnsExist ??= Schema::hasColumns('incidents', ['guest_name', 'guest_mobile']);
    }

    /**
     * A report from the app ("Report Emergency", or a guest via the API).
     *
     * Nothing is sent to responders from here: every report waits in the
     * admin's Pending Review until an admin approves it
     * (Admin\IncidentController::approve() dispatches it then) or declines
     * it.
     *
     * Priority is left empty. The admin picks Critical, High or Low when
     * approving — the photo is no longer run through the AI to guess one.
     */
    public function store(
        Request $request,
        BarangayLocationService $locationService
    ) {
        $citizen = $this->resolveOptionalCitizen($request);
        $isGuest = ! $citizen;
        [$guestRules, $guestMessages] = $this->prepareGuestDetails($request, $isGuest);

        $validator = Validator::make($request->all(), [
            'emergency_type' => ['required', 'string', 'max:255'],
            // Guests only ever send a GPS pin — location text and a
            // description are nice-to-have, not required, for them.
            'location'       => [$isGuest ? 'nullable' : 'required', 'string', 'max:255'],
            'description'    => [$isGuest ? 'nullable' : 'required', 'string'],
            'latitude'       => [$isGuest ? 'required' : 'nullable', 'numeric', 'between:-90,90'],
            'longitude'      => [$isGuest ? 'required' : 'nullable', 'numeric', 'between:-180,180'],
            'photos'         => [$isGuest ? 'nullable' : 'required', 'array', $isGuest ? '' : 'min:1'],
            'photos.*'       => ['file', 'image', 'max:5120'],
        ] + $guestRules, $guestMessages);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $guest = $isGuest ? ['name' => $request->guest_name, 'mobile' => $request->guest_mobile] : null;
        $guestLabel = $guest ? "{$guest['name']} ({$guest['mobile']})" : null;

        $photoPaths = [];
        foreach ($request->file('photos', []) as $photo) {
            $photoPaths[] = $photo->store('incident_photos', 'public');
        }

        // Guests never type a location — only a GPS pin. Resolve that pin
        // to something a human can actually read (street/barangay via
        // Nominatim, falling back to the nearest known barangay centroid,
        // and only raw coordinates as a last resort) instead of leaving
        // `location` blank, which is what forced every admin view to show
        // naked lat/lng numbers for guest reports.
        $location = $request->location;
        if ($isGuest && ! $location) {
            $location = $locationService->approximateReportLabel($request->latitude, $request->longitude);
        }

        // Only changes the message below now — every report is reviewed
        // anyway. Only checked when coordinates were actually sent (see
        // isWithinRosales()).
        $isOutsideRosales = ! $locationService->isWithinRosales($request->latitude, $request->longitude);

        $incident = Incident::create([
            'citizen_id'     => $citizen?->id,
            'emergency_type' => $request->emergency_type,
            'location'       => $location,
            'latitude'       => $request->latitude,
            'longitude'      => $request->longitude,
            'description'    => $request->description ?: "Guest report from {$guestLabel} — location only. Awaiting MDRRMO review.",
            'photo_path'     => $photoPaths ? json_encode($photoPaths) : null,
            'status'         => 'pending',
            // Every report waits for an admin to review it — guest or
            // resident, inside or outside Rosales. Responders are only
            // notified when an admin approves it (see
            // Admin\IncidentController::approve()).
            'needs_review'   => true,
            // No priority yet — the admin sets it when approving.
            'priority'       => null,
        ] + $this->guestColumns($guest));

        // Resident activity trail — shows up in the admin Audit Log page
        // (filterable by action=created). Same convention as login() in
        // Api\AuthController: user_id/user_name stay null (the admin web
        // guard is never authenticated here), so the actual actor is
        // captured via $auditable (this Incident, which carries
        // citizen_id) and named directly in the description.
        AuditLogService::log(
            'created',
            $isGuest
                ? "Guest {$guestLabel} reported a {$incident->emergency_type} incident."
                : "Citizen {$citizen->full_name} reported a {$incident->emergency_type} incident.",
            $incident
        );

        // No responders are notified here — that happens when an admin
        // approves the report (Admin\IncidentController::approve()).
        $message = $isOutsideRosales
            ? 'Report submitted. This location is outside Rosales, so MDRRMO will check it before sending responders.'
            : 'Report submitted. MDRRMO will review it and then send responders.';

        return response()->json([
            'message'  => $message,
            'incident' => $incident,
        ], 201);
    }

    /**
     * An SOS from the app's panic button.
     *
     * Like reports, every SOS waits in the admin's Pending Review —
     * guest or resident, inside or outside Rosales — so MDRRMO can verify
     * it first. Responders are notified only when an admin approves it
     * (Admin\IncidentController::approve()).
     */
    public function sos(
        Request $request,
        ImageAnalysisService $imageAnalysis,
        BarangayLocationService $locationService
    ) {
        $citizen = $this->resolveOptionalCitizen($request);
        $isGuest = ! $citizen;
        [$guestRules, $guestMessages] = $this->prepareGuestDetails($request, $isGuest);

        $validator = Validator::make($request->all(), [
            'latitude'       => ['required', 'numeric', 'between:-90,90'],
            'longitude'      => ['required', 'numeric', 'between:-180,180'],
            'photo'          => ['nullable', 'file', 'image', 'max:5120'],
            // Optional — the citizen now picks a hazard type on the SOS
            // screen before holding the button, but the button still has
            // to work even if this is never sent (e.g. an older app
            // build). Free text is allowed via "Other" on the app side,
            // so this isn't restricted to a fixed list server-side.
            'emergency_type' => ['nullable', 'string', 'max:255'],
        ] + $guestRules, $guestMessages);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $guest = $isGuest ? ['name' => $request->guest_name, 'mobile' => $request->guest_mobile] : null;
        $guestLabel = $guest ? "{$guest['name']} ({$guest['mobile']})" : null;

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('incident_photos', 'public')
            : null;

        // Only changes the message below now — every SOS is reviewed
        // anyway. SOS always carries coordinates, so this always runs.
        $isOutsideRosales = ! $locationService->isWithinRosales($request->latitude, $request->longitude);

        $incident = Incident::create([
            'citizen_id'         => $citizen?->id,
            'emergency_type'     => 'SOS Emergency',
            // SOS never carries typed location text — only ever a GPS
            // pin, from a guest or logged-in citizen alike — so this
            // always needs resolving, unlike store() above where only
            // guests hit this path.
            'location'           => $locationService->approximateSosLabel($request->latitude, $request->longitude),
            'latitude'           => $request->latitude,
            'longitude'          => $request->longitude,
            'description'        => $isGuest ? "Guest SOS from {$guestLabel}. Awaiting MDRRMO review." : 'SOS panic-button alert.',
            'photo_path'         => $photoPath ? json_encode([$photoPath]) : null,
            'status'             => 'pending',
            'priority'           => 'critical',
            // What the citizen picked on the SOS screen before holding
            // the button (Fire, Flood, Medical, etc.) — kept separate
            // from emergency_type (always 'SOS Emergency' above, which
            // every SOS-specific query/filter/route relies on) and from
            // ai_detected_type (an AI guess made from the photo, set
            // below, which can end up disagreeing with what the citizen
            // actually said). Purely informational for MDRRMO right now
            // — it doesn't change routing/dispatch.
            'sos_emergency_type' => $request->emergency_type ?: null,
            // Every SOS waits for an admin to verify it, same as reports.
            'needs_review'       => true,
        ] + $this->guestColumns($guest));

        if ($photoPath) {
            $analysis = $imageAnalysis->classify($photoPath, $request->emergency_type);
            if ($analysis) {
                $incident->update([
                    'ai_detected_type' => $analysis['type'],
                    'ai_confidence'    => $analysis['confidence'],
                    'ai_analysis'      => $analysis['notes'],
                ]);
            }
        }

        // Same resident activity trail as store() above — an SOS is still
        // "reporting an incident" from the audit log's point of view, so
        // it shows up in the same Audit Log page (action=created), just
        // with wording that makes clear it came from the SOS button.
        AuditLogService::log(
            'created',
            $isGuest
                ? "Guest {$guestLabel} sent an SOS alert."
                : "Citizen {$citizen->full_name} sent an SOS alert.",
            $incident
        );

        // No responders are notified here — that happens when an admin
        // approves the SOS (Admin\IncidentController::approve()).
        $message = $isOutsideRosales
            ? 'SOS sent. Since this location is outside Rosales, MDRRMO will review it before responders are notified.'
            : 'SOS sent. MDRRMO will verify it right away and then send responders.';

        return response()->json([
            'message'  => $message,
            'incident' => $incident,
        ], 201);
    }

    /**
     * The resident's own reports (My Reports). MDRRMO's internal notes,
     * the responders' MDRRMC report (it names casualties) and what MDRRMO
     * asked the responders to fix stay out of it — the app shows none of
     * them.
     */
    public function mine(Request $request)
    {
        $incidents = Incident::with('responders')
            ->where('citizen_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get()
            ->each(fn (Incident $incident) => $incident->makeHidden([
                'admin_notes', 'report_data', 'report_return_reason', 'resolved_by',
            ]));

        return response()->json($incidents);
    }

    public function assignedToResponder(Request $request)
    {
        $responder = $request->user();

        if (! $responder instanceof Responder) {
            return response()->json(['message' => 'Not a responder account.'], 403);
        }

        $incidents = Incident::with(['citizen', 'responders'])
            // Resolved, or the team's Final report is with MDRRMO for
            // review (it comes back here if MDRRMO sends the report back).
            // Both are listed in Report History instead.
            ->whereNotIn('status', ['resolved', 'for_review'])
            // Reports and SOS alerts still waiting for admin review must
            // never reach the responder feed — they're only dispatched
            // when an admin approves them.
            ->where('needs_review', false)
            // An admin decline flips needs_review back to false too —
            // same as an approval (see the decline-fields migration) —
            // so without this a report MDRRMO explicitly rejected as
            // spam/a duplicate/unverifiable would silently reappear here
            // looking exactly like an approved one. Exclude it for good,
            // same tier as the resolved/needs_review checks above.
            ->whereNull('declined_at')
            // Hides an incident only from the one responder who
            // personally declined it (see decline()/accept() below and
            // Incident::declinedBy()) — everyone else, same agency or
            // not, still sees it exactly as before.
            ->whereDoesntHave('declinedBy', function ($q) use ($responder) {
                $q->where('responders.id', $responder->id);
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json($incidents);
    }

    /**
     * Report History — incidents this responder personally accepted (or
     * backed up on) whose Final report is done: resolved, or still with
     * MDRRMO for review (status for_review, shown as "Under Review").
     * assignedToResponder() above filters both OUT by design (it's the
     * "what's still active" feed), so an incident disappears from it the
     * moment the Final report is sent — this is the separate feed the
     * Report History screen needs instead. Scoped via the
     * incident_responder pivot rather than the deprecated singular
     * responder_id column, matching how accept()/decline() actually
     * record responders now.
     */
    public function resolvedForResponder(Request $request)
    {
        $responder = $request->user();

        if (! $responder instanceof Responder) {
            return response()->json(['message' => 'Not a responder account.'], 403);
        }

        $incidents = Incident::with(['citizen', 'responders'])
            ->whereIn('status', ['resolved', 'for_review'])
            ->whereHas('responders', function ($q) use ($responder) {
                $q->where('responders.id', $responder->id);
            })
            ->orderByDesc('updated_at')
            ->get();

        return response()->json($incidents);
    }

    /**
     * Backup-support model: accepting no longer exclusively claims the
     * incident for one responder. Any number of responders (from the
     * same or different agencies) can join the same incident — this
     * just adds the caller to the pivot table if they aren't on it
     * already. Tapping Accept a second time (e.g. a stale UI, or the
     * app re-sending after a timeout) is a safe no-op thanks to the
     * UNIQUE(incident_id, responder_id) constraint on incident_responder.
     */
    public function accept(Request $request, Incident $incident)
    {
        $responder = $request->user();

        if (! $responder instanceof Responder) {
            return response()->json(['message' => 'Not a responder account.'], 403);
        }

        // MSWD manages evacuation centers, not incident response — see
        // EvacuationCenterController::store()/storeEvacuee() for the
        // inverse rule (those are MSWD-only). The Flutter home screen
        // already keeps every response-workflow tile off MSWD's
        // screen, but Incident Locations is still shown to them for
        // situational awareness, and its map pins open this same
        // accept flow — so the enforcement has to live here too, not
        // just in which tiles are shown.
        if ($responder->agency === 'MSWD') {
            return response()->json([
                'message' => 'MSWD accounts manage evacuation centers, not incident response.',
            ], 403);
        }

        if ($incident->status === 'resolved') {
            return response()->json(['message' => 'This incident has already been resolved.'], 409);
        }

        // The team already sent its Final report — nothing left to join.
        if ($incident->status === 'for_review') {
            return response()->json(['message' => "This incident's Final report is already with MDRRMO for review."], 409);
        }

        // Declined by MDRRMO (spam, a duplicate, unverifiable, outside
        // the service area, etc. — see Admin\IncidentController::
        // decline()). A decline flips needs_review back to false, same
        // as an approval does (see the decline-fields migration), so
        // declined_at is the only signal left that this one was
        // actually rejected rather than cleared for dispatch.
        // assignedToResponder() above hides it from the list for the
        // same reason — this is the enforcement that stops a stale
        // cached list, a shared link, or a direct API call from reviving
        // a report MDRRMO already turned down.
        if ($incident->declined_at !== null) {
            return response()->json([
                'message' => 'This report was declined by MDRRMO and cannot be accepted.',
            ], 409);
        }

        // Still waiting on admin review (every new report and SOS starts
        // there) — store()/sos() withhold the responder
        // dispatch until an admin approves it, so there's nothing
        // actually dispatched yet to accept. assignedToResponder()
        // already keeps these out of the normal feed; this is the same
        // belt-and-suspenders enforcement as the declined_at check above.
        if ($incident->needs_review) {
            return response()->json([
                'message' => "This report is still pending MDRRMO review and hasn't been dispatched yet.",
            ], 409);
        }

        $alreadyJoined = $incident->responders()->where('responder_id', $responder->id)->exists();

        if (! $alreadyJoined) {
            $incident->responders()->attach($responder->id, ['accepted_at' => now()]);
        }

        // Accepting after an earlier decline (reconsidering, or reached
        // via a push notification rather than the now-filtered Active
        // Incidents list) should put this incident back in the
        // responder's normal feed — not leave a stale decline hiding an
        // incident they're now actually on.
        $incident->declinedBy()->detach($responder->id);

        // First acceptance (by anyone) moves the incident out of
        // 'pending' — later backup joins don't need to change status
        // again, it's already 'responding'.
        if ($incident->status === 'pending') {
            $incident->update(['status' => 'responding']);
        }

        if (! $alreadyJoined) {
            AuditLogService::responder(
                $responder,
                'accepted',
                'Accepted the ' . AuditLogService::describeIncident($incident) . ' — now responding.',
                ['incident' => AuditLogService::describeIncident($incident)]
            );
        }

        $incident->refresh()->load(['responders', 'citizen']);

        return response()->json([
            'message'  => $alreadyJoined
                ? "You're already responding to this incident."
                : 'Mission accepted.',
            'incident' => $incident,
        ]);
    }

    /**
     * Records that THIS responder, personally, is opting out of this
     * incident — see the incident_declines migration and
     * Incident::declinedBy(). It never touches the incident itself (no
     * status change, no effect on anyone else): assignedToResponder()
     * above is the only place that reads it, to drop the incident from
     * this one responder's own Active Incidents feed. Everyone else
     * (same agency or not) keeps seeing it normally.
     */
    public function decline(Request $request, Incident $incident)
    {
        $responder = $request->user();

        if (! $responder instanceof Responder) {
            return response()->json(['message' => 'Not a responder account.'], 403);
        }

        // Nothing left to opt out of once it's closed out.
        if ($incident->status === 'resolved') {
            return response()->json(['message' => 'This incident has already been resolved.'], 409);
        }
        if ($incident->status === 'for_review') {
            return response()->json(['message' => "This incident's Final report is already with MDRRMO for review."], 409);
        }

        // Already on this incident — the app already disables Decline
        // once joined, so this only guards a stale/racing request from
        // hiding an incident this responder is actually working.
        $alreadyJoined = $incident->responders()->where('responder_id', $responder->id)->exists();

        if ($alreadyJoined) {
            return response()->json(['message' => "You're already responding to this incident."], 409);
        }

        // syncWithoutDetaching so declining twice (a stale UI retry,
        // etc.) just refreshes declined_at instead of hitting the
        // unique(incident_id, responder_id) constraint.
        $incident->declinedBy()->syncWithoutDetaching([
            $responder->id => ['declined_at' => now()],
        ]);

        AuditLogService::responder(
            $responder,
            'declined',
            'Declined to take the ' . AuditLogService::describeIncident($incident) . '.',
            ['incident' => AuditLogService::describeIncident($incident)]
        );

        return response()->json(['message' => 'Mission declined.']);
    }

    /**
     * Files the MDRRMC Incident Report from the field — see
     * IncidentResolutionScreen (incident_resolution.dart). Only a
     * responder who's actually on this incident (i.e. called accept()
     * already) can file it: a Final report ends the team's part of the
     * mission, so it isn't safe to leave open to any responder who merely
     * sees it in their feed.
     *
     * The report's type decides what happens:
     *   initial  saved, and the incident stays open. It can be sent again
     *            as often as needed — each time replaces the saved report
     *            and rebuilds the PDF — until it's sent as Final.
     *   final    saved, and the incident goes to MDRRMO's For Review tab
     *            (status for_review). MDRRMO then marks it resolved, or
     *            sends the report back (Admin\IncidentController::
     *            returnReport() — it becomes Initial again, so the team
     *            can fix it and send it as Final once more). While it's
     *            with MDRRMO the report can't be changed (409).
     *
     * If an admin resolved the incident while the report was still
     * Initial (incidents resolved before For Review existed), the team can
     * still update and finalize it (the status stays resolved). A resolved
     * incident with no report at all is refused, as before.
     *
     * The photo is kept between updates unless a new one is sent (it
     * replaces the old one) or remove_photo=1 is sent.
     *
     * Older app versions that send only notes (no report) still finish the
     * incident — it goes to For Review the same way.
     */
    public function resolve(Request $request, Incident $incident)
    {
        $responder = $request->user();

        if (! $responder instanceof Responder) {
            return response()->json(['message' => 'Not a responder account.'], 403);
        }

        $isAssigned = $incident->responders()->where('responder_id', $responder->id)->exists();

        if (! $isAssigned) {
            return response()->json(['message' => 'You are not assigned to this incident.'], 403);
        }

        if ($incident->status === 'for_review') {
            return response()->json([
                'message'  => self::UNDER_REVIEW_MESSAGE,
                'incident' => $incident->load(['responders', 'citizen']),
            ], 409);
        }

        $saved = is_array($incident->report_data) ? $incident->report_data : null;

        if (IncidentReportService::isFinal($saved)) {
            return response()->json([
                'message'  => 'The final report for this incident was already submitted and can no longer be changed.',
                'incident' => $incident->load(['responders', 'citizen']),
            ], 409);
        }

        if ($incident->status === 'resolved' && $saved === null) {
            return response()->json(['message' => 'This incident has already been resolved.'], 409);
        }

        // The app sends the MDRRMC Incident Report as a JSON string in
        // `report` (multipart can't carry nested fields otherwise).
        $reportInput = $request->input('report');
        if (is_string($reportInput)) {
            $reportInput = json_decode($reportInput, true);
        }

        $validator = Validator::make(
            array_merge($request->all(), ['report' => $reportInput]),
            [
                'notes'        => ['nullable', 'string', 'max:2000'],
                'photo'        => ['nullable', 'file', 'image', 'max:5120'],
                'remove_photo' => ['nullable', 'boolean'],
                'report'       => ['nullable', 'array'],
                'report.narrative'   => ['nullable', 'string', 'max:2000'],
                'report.report_type' => ['nullable', 'in:initial,final'],
            ]
        );

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $reports    = app(IncidentReportService::class);
        $reportData = is_array($reportInput)
            ? $reports->normalize($incident, $responder, $reportInput, $saved)
            : null;

        // No report sent (older app versions) = finished, as before.
        $isFinal     = $reportData === null || IncidentReportService::isFinal($reportData);
        $wasResolved = $incident->status === 'resolved';
        $wasReturned = $incident->report_returned_at !== null;

        $oldPhoto  = $incident->resolution_photo_path;
        $photoPath = $oldPhoto;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('incident_resolution_photos', 'public');
        } elseif ($request->boolean('remove_photo')) {
            $photoPath = null;
        }

        // The narrative doubles as the short "resolution notes" shown in
        // the admin panel and Report History, unless plain notes were sent.
        $notes = $request->notes ?: ($reportData['narrative'] ?? $incident->resolution_notes);

        $changes = [
            'resolution_notes'      => $notes,
            'resolution_photo_path' => $photoPath,
        ];
        if ($reportData !== null) {
            $changes['report_data'] = $reportData;
        }
        if ($isFinal && ! $wasResolved) {
            // MDRRMO checks it first (Incidents → For Review).
            $changes['status']              = 'for_review';
            $changes['report_submitted_at'] = now();
        }

        $incident->update($changes);

        // The replaced or removed photo isn't used anywhere else.
        if ($oldPhoto && $oldPhoto !== $photoPath) {
            try {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPhoto);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Never let a PDF problem undo a report that was already saved.
        if ($reportData) {
            try {
                $pdfPath = $reports->generate($incident->fresh());
                if ($pdfPath) {
                    $incident->update(['report_pdf_path' => $pdfPath]);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $what = AuditLogService::describeIncident($incident);
        $sentForReview = 'Final report submitted. MDRRMO will review it, then mark the incident resolved.';

        [$action, $description, $message] = match (true) {
            $reportData === null => [
                'updated',
                "Marked the {$what} as done (no report attached) — sent to MDRRMO for review.",
                'Sent to MDRRMO. They will review it, then mark the incident resolved.',
            ],
            ! $isFinal && $saved === null => [
                'created',
                "Filed the initial MDRRMC Incident Report for the {$what} — the incident stays open until the final report.",
                'Initial report submitted. You can update it until you submit the Final report.',
            ],
            ! $isFinal => [
                'updated',
                "Updated the initial MDRRMC Incident Report for the {$what}.",
                'Initial report updated. You can keep updating it until you submit the Final report.',
            ],
            $wasResolved => [
                'updated',
                "Filed the final MDRRMC Incident Report for the {$what}.",
                'Final report submitted.',
            ],
            $wasReturned => [
                'updated',
                "Sent the corrected final MDRRMC Incident Report for the {$what} back to MDRRMO for review.",
                $sentForReview,
            ],
            $saved === null => [
                'created',
                "Filed the final MDRRMC Incident Report for the {$what} — sent to MDRRMO for review.",
                $sentForReview,
            ],
            default => [
                'updated',
                "Submitted the final MDRRMC Incident Report for the {$what} — sent to MDRRMO for review.",
                $sentForReview,
            ],
        };

        AuditLogService::responder($responder, $action, $description, ['incident' => $what]);

        $incident->refresh()->load(['responders', 'citizen']);

        return response()->json([
            'message'     => $message,
            'incident'    => $incident,
            'report_type' => $reportData['report_type'] ?? null,
            'resolved'    => $incident->status === 'resolved',
            'for_review'  => $incident->status === 'for_review',
            'can_edit'    => ! $isFinal,
        ]);
    }

    /**
     * GET /api/responder/incidents/{incident}/report — the report already
     * filed for this incident, so the app's report form opens with it
     * filled in when an Initial report is being updated.
     *
     *   report           the saved report (null if none yet)
     *   is_final         a Final report was submitted — nothing to edit
     *   under_review     the Final report is with MDRRMO (For Review)
     *   can_edit         the form may be sent (see resolve())
     *   returned_reason  what MDRRMO asked the team to fix, while a report
     *                    MDRRMO sent back is waiting to be sent again
     *   returned_at      when MDRRMO sent it back
     *   photo_path       the report's photo, on the public disk
     *   incident_status  so the app can update its own copy
     */
    public function reportForResponder(Request $request, Incident $incident)
    {
        $responder = $request->user();

        if (! $responder instanceof Responder) {
            return response()->json(['message' => 'Not a responder account.'], 403);
        }

        if (! $incident->responders()->where('responder_id', $responder->id)->exists()) {
            return response()->json(['message' => 'You are not assigned to this incident.'], 403);
        }

        $report      = is_array($incident->report_data) ? $incident->report_data : null;
        $isFinal     = IncidentReportService::isFinal($report);
        $underReview = $incident->status === 'for_review';
        $canEdit     = ! $isFinal && ! $underReview && ! ($incident->status === 'resolved' && $report === null);
        $returned    = $canEdit && $incident->report_returned_at !== null && filled($incident->report_return_reason);

        return response()->json([
            'report'          => $report,
            'is_final'        => $isFinal,
            'under_review'    => $underReview,
            'can_edit'        => $canEdit,
            'returned_reason' => $returned ? $incident->report_return_reason : null,
            'returned_at'     => $returned ? $incident->report_returned_at?->toIso8601String() : null,
            'photo_path'      => $incident->resolution_photo_path,
            'incident_status' => $incident->status,
            'message'         => $canEdit ? null : match (true) {
                $underReview => self::UNDER_REVIEW_MESSAGE,
                $isFinal     => 'The final report for this incident was already submitted and can no longer be changed.',
                default      => 'This incident has already been resolved.',
            },
        ]);
    }
}
