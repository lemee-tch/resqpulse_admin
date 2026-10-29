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
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

class IncidentController extends Controller
{
    /**
     * The team's report is with MDRRMO (status for_review, "For Review" in
     * the app). The incident isn't resolved yet: only MDRRMO marks it
     * resolved, when it approves the report (item 50).
     */
    private const UNDER_REVIEW_MESSAGE = 'Your Initial report is with MDRRMO for review. The incident isn\'t resolved until MDRRMO approves the report. You can only change it if MDRRMO sends it back to you.';

    /** MDRRMO approved the team's report: the incident is resolved. */
    private const RESOLVED_MESSAGE = 'MDRRMO approved this report and marked the incident resolved. It can no longer be changed.';

    /** Report Emergency without the phone's location (item 49). */
    private const NO_LOCATION_MESSAGE = 'Your location is needed to send a report. Turn on your phone\'s location and try again.';

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
     * A report or SOS from a resident MDRRMO blocked (item 54): refused,
     * with the reason, and the login on that phone ends here — the same as
     * on any signed-in call (RejectBlockedCitizens). The app goes back to
     * guest mode and shows the reason.
     */
    private function blockedReply(Request $request, Citizen $citizen)
    {
        PersonalAccessToken::findToken((string) $request->bearerToken())?->delete();

        return response()->json(['message' => $citizen->blockedMessage(), 'blocked' => true], 401);
    }

    /**
     * A guest report or SOS with the mobile number of a blocked account
     * (item 54) — refused, the same as from the account itself.
     */
    private function blockedNumberReply()
    {
        return response()->json(['message' => Citizen::BLOCKED_NUMBER_MESSAGE, 'blocked' => true], 403);
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
     *
     * The location is the phone's GPS pin, like an SOS (item 49): the app
     * no longer lets the resident search for a barangay, so the pin is
     * required, and the place name — street and barangay — is worked out
     * from it here (BarangayLocationService::resolve()). A location typed
     * in an older version of the app is still kept as it was sent.
     */
    public function store(
        Request $request,
        BarangayLocationService $locationService
    ) {
        $citizen = $this->resolveOptionalCitizen($request);
        if ($citizen?->isBlocked()) {
            return $this->blockedReply($request, $citizen);
        }
        $isGuest = ! $citizen;
        [$guestRules, $guestMessages] = $this->prepareGuestDetails($request, $isGuest);

        $validator = Validator::make($request->all(), [
            'emergency_type' => ['required', 'string', 'max:255'],
            // Worked out from the pin when it isn't sent — the app sends
            // only the pin since item 49.
            'location'       => ['nullable', 'string', 'max:255'],
            // A description is nice-to-have, not required, for guests.
            'description'    => [$isGuest ? 'nullable' : 'required', 'string'],
            'latitude'       => ['required', 'numeric', 'between:-90,90'],
            'longitude'      => ['required', 'numeric', 'between:-180,180'],
            'photos'         => [$isGuest ? 'nullable' : 'required', 'array', $isGuest ? '' : 'min:1'],
            'photos.*'       => ['file', 'image', 'max:5120'],
        ] + $guestRules, [
            'latitude.required'  => self::NO_LOCATION_MESSAGE,
            'longitude.required' => self::NO_LOCATION_MESSAGE,
        ] + $guestMessages);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        if ($isGuest && Citizen::numberIsBlocked($request->guest_mobile)) {
            return $this->blockedNumberReply();
        }

        $guest = $isGuest ? ['name' => $request->guest_name, 'mobile' => $request->guest_mobile] : null;
        $guestLabel = $guest ? "{$guest['name']} ({$guest['mobile']})" : null;

        $photoPaths = [];
        foreach ($request->file('photos', []) as $photo) {
            $photoPaths[] = $photo->store('incident_photos', 'public');
        }

        // Nobody types a location anymore — only the GPS pin is sent. The
        // pin becomes something a person can read (street and barangay
        // from Google, then OpenStreetMap, then the nearest barangay centre
        // — never raw coordinates). Guest reports keep their "Guest Report —"
        // label.
        $location = trim((string) $request->location);
        if ($location === '') {
            $lat = (float) $request->latitude;
            $lng = (float) $request->longitude;
            $location = $isGuest
                ? $locationService->approximateReportLabel($lat, $lng)
                : ($locationService->resolve($lat, $lng) ?: 'Rosales, Pangasinan');
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
        if ($citizen?->isBlocked()) {
            return $this->blockedReply($request, $citizen);
        }
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

        if ($isGuest && Citizen::numberIsBlocked($request->guest_mobile)) {
            return $this->blockedNumberReply();
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
                'assignment_notes', 'responders_on_record', 'reviewed_by',
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
            // Resolved, or the team's report is with MDRRMO for review (it
            // comes back here if MDRRMO sends the report back). Both are
            // listed in Report History instead.
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
            // The team(s) it was sent to — approving sends it to the teams
            // the admin chooses (Admin\IncidentController::approve(), item
            // 53), and the admin can add more later — plus anyone already
            // on it, and
            // incidents approved before teams were assigned (no team list at
            // all). One approved without being sent anywhere (item 36) has
            // an empty list, so no team sees it. MSWD keeps seeing
            // everything: its Incident Locations map is for awareness only
            // (it can't accept).
            ->when($responder->agency !== 'MSWD', function ($q) use ($responder) {
                $q->where(function ($q) use ($responder) {
                    $q->whereNull('assigned_teams')
                        ->orWhereJsonContains('assigned_teams', $responder->agency)
                        ->orWhereHas('responders', fn ($r) => $r->where('responders.id', $responder->id));
                });
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json($incidents);
    }

    /**
     * Report History — incidents this responder personally accepted (or
     * backed up on) whose report is done: resolved, or still with MDRRMO
     * for review (status for_review, shown as "For Review" — not resolved
     * until MDRRMO approves the report).
     * assignedToResponder() above filters both OUT by design (it's the
     * "what's still active" feed), so an incident disappears from it the
     * moment the report is sent — this is the separate feed the
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

        // The team already sent its report — nothing left to join.
        if ($incident->status === 'for_review') {
            return response()->json(['message' => "This incident's report is already with MDRRMO for review."], 409);
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

        // Approved, but MDRRMO hasn't sent it to the responders yet — so
        // there's nothing to accept.
        if ($incident->awaitingTeam()) {
            return response()->json([
                'message' => "MDRRMO hasn't sent this incident to the responders yet.",
            ], 409);
        }

        // MDRRMO sent it to other team(s) only (Approve & Send, item 53).
        // Joining needs MDRRMO to notify this team first (Incident Details
        // → Notify specific responders), which adds it.
        if (! $incident->isAssignedTo($responder->agency)
            && ! $incident->responders()->where('responder_id', $responder->id)->exists()) {
            $codes = $incident->assignedTeamCodes();
            $teams = count($codes) > 1
                ? implode(', ', array_slice($codes, 0, -1)) . ' and ' . end($codes)
                : ($codes[0] ?? 'another team');
            return response()->json([
                'message' => "MDRRMO sent this incident to {$teams}. Ask MDRRMO to notify your team if you need to respond.",
            ], 403);
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
            return response()->json(['message' => "This incident's report is already with MDRRMO for review."], 409);
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
     * Sends the MDRRMC Incident Report from the field — see
     * IncidentResolutionScreen (incident_resolution.dart). Only a
     * responder who's actually on this incident (i.e. called accept()
     * already) can send it: it ends the team's part of the mission, so it
     * isn't safe to leave open to any responder who merely sees it in
     * their feed.
     *
     * The team sends one report, the Initial report — there's no Final
     * report from the team anymore (item 49). It goes straight to MDRRMO:
     * the incident moves to For Review (status for_review). MDRRMO reads it,
     * can correct it (Admin\IncidentController::updateReport()) and approves
     * it as resolved (confirmResolved()) — or sends it back with what to fix
     * (returnReport()): the incident goes back to Active and the team sends
     * the report again. While MDRRMO has it the team can't change it (409),
     * and once MDRRMO approved it, it's final (409).
     *
     * Each sending replaces the saved report and rebuilds the PDF. The
     * photo is kept between sendings unless a new one is sent (it replaces
     * the old one) or remove_photo=1 is sent.
     *
     * An older app that still lets the team choose Initial or Final gets
     * the same either way: the report is saved as the Initial report and
     * goes to MDRRMO. One that sends only notes (no report) still finishes
     * the incident — it goes to For Review the same way.
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

        if ($incident->status === 'resolved') {
            return response()->json([
                'message'  => is_array($incident->report_data)
                    ? self::RESOLVED_MESSAGE
                    : 'This incident has already been resolved.',
                'incident' => $incident->load(['responders', 'citizen']),
            ], 409);
        }

        $saved = is_array($incident->report_data) ? $incident->report_data : null;

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
                // An older app may still say "final" — it's saved as the
                // Initial report all the same.
                'report.report_type' => ['nullable', 'in:initial,final'],
            ]
        );

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $reports    = app(IncidentReportService::class);
        $reportData = is_array($reportInput)
            ? $reports->normalize($incident, $responder, array_merge($reportInput, ['report_type' => 'initial']), $saved)
            : null;

        // MDRRMO sent it back before: this is the corrected report.
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

        // MDRRMO checks it first (Incidents → For Review).
        $changes = [
            'resolution_notes'      => $notes,
            'resolution_photo_path' => $photoPath,
            'status'                => 'for_review',
            'report_submitted_at'   => now(),
        ];
        if ($reportData !== null) {
            $changes['report_data'] = $reportData;
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
        $sentForReview = 'Initial report sent. MDRRMO will review it, then mark the incident resolved.';

        [$action, $description, $message] = match (true) {
            $reportData === null => [
                'updated',
                "Marked the {$what} as done (no report attached) — sent to MDRRMO for review.",
                'Sent to MDRRMO. They will review it, then mark the incident resolved.',
            ],
            $wasReturned => [
                'updated',
                "Sent the corrected Initial MDRRMC Incident Report for the {$what} back to MDRRMO for review.",
                'Initial report sent again. MDRRMO will review it, then mark the incident resolved.',
            ],
            $saved === null => [
                'created',
                "Filed the Initial MDRRMC Incident Report for the {$what} — sent to MDRRMO for review.",
                $sentForReview,
            ],
            default => [
                'updated',
                "Sent the Initial MDRRMC Incident Report for the {$what} to MDRRMO for review.",
                $sentForReview,
            ],
        };

        AuditLogService::responder($responder, $action, $description, ['incident' => $what]);

        $incident->refresh()->load(['responders', 'citizen']);

        return response()->json([
            'message'     => $message,
            'incident'    => $incident,
            'report_type' => $reportData['report_type'] ?? null,
            'resolved'    => false,
            'for_review'  => true,
            'can_edit'    => false,
        ]);
    }

    /**
     * GET /api/responder/incidents/{incident}/report — the report already
     * filed for this incident: the app's report form opens with it filled
     * in (sending it again after MDRRMO returned it), and the team reads it
     * on Incident Report (team_report.dart).
     *
     *   report           the saved report (null if none yet) — with
     *                    edited_by / edited_at / edited_parts when MDRRMO
     *                    corrected it, reviewed_by once MDRRMO approved it
     *   is_final         a Final report, sent before item 49
     *   under_review     the report is with MDRRMO (For Review)
     *   can_edit         the form may be sent (see resolve())
     *   returned_reason  what MDRRMO asked the team to fix, while a report
     *                    MDRRMO sent back is waiting to be sent again
     *   returned_at      when MDRRMO sent it back
     *   photo_path       the report's photo, on the public disk
     *   incident_status  so the app can update its own copy
     */
    public function reportForResponder(Request $request, Incident $incident, IncidentReportService $reports)
    {
        $responder = $request->user();

        if (! $responder instanceof Responder) {
            return response()->json(['message' => 'Not a responder account.'], 403);
        }

        if (! $incident->responders()->where('responder_id', $responder->id)->exists()) {
            return response()->json(['message' => 'You are not assigned to this incident.'], 403);
        }

        $report      = is_array($incident->report_data) ? $incident->report_data : null;
        $underReview = $incident->status === 'for_review';
        $resolved    = $incident->status === 'resolved';
        $canEdit     = ! $underReview && ! $resolved;
        $returned    = $canEdit && $incident->report_returned_at !== null && filled($incident->report_return_reason);

        // The team as the PDF lists it, read live (late backups included).
        if ($report !== null) {
            $report['team'] = $reports->currentTeam($incident);
        }

        return response()->json([
            'report'          => $report,
            // The PDF MDRRMO sees (item 48). The phone opens it in its
            // browser or PDF viewer, which can't send the sign-in token,
            // so the link carries its own signature — good for 30 minutes.
            'pdf_url'         => $report !== null ? $this->reportPdfLink($incident) : null,
            'submitted_at'    => $incident->report_submitted_at?->toIso8601String(),
            'is_final'        => IncidentReportService::isFinal($report),
            'under_review'    => $underReview,
            'can_edit'        => $canEdit,
            'returned_reason' => $returned ? $incident->report_return_reason : null,
            'returned_at'     => $returned ? $incident->report_returned_at?->toIso8601String() : null,
            'photo_path'      => $incident->resolution_photo_path,
            'incident_status' => $incident->status,
            'message'         => $canEdit ? null : match (true) {
                $underReview      => self::UNDER_REVIEW_MESSAGE,
                $report !== null  => self::RESOLVED_MESSAGE,
                default           => 'This incident has already been resolved.',
            },
        ]);
    }

    /**
     * A link to the report PDF for reportForResponder(): signed and good for
     * 30 minutes. Signed on its path only, so it still checks out when the
     * site sits behind a proxy that changes http/https.
     */
    private function reportPdfLink(Incident $incident): string
    {
        return url(URL::temporarySignedRoute(
            'responder.report-pdf',
            now()->addMinutes(30),
            ['incident' => $incident->id],
            false
        ));
    }

    /**
     * GET /api/responder/incidents/{incident}/report-pdf?expires=…&signature=…
     * The MDRRMC Incident Report PDF, as MDRRMO sees it, for the team on the
     * incident (item 48). The link comes from reportForResponder(), which
     * only gives it to a responder on the incident; the route checks the
     * signature instead of a sign-in token (routes/api.php).
     */
    public function reportPdfForResponder(Request $request, Incident $incident, IncidentReportService $reports)
    {
        abort_unless(is_array($incident->report_data), 404, 'No report was filed for this incident.');

        return $reports->pdfResponse($incident, $request->boolean('download'));
    }
}
