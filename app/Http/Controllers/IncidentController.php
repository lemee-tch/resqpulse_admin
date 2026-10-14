<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Services\AuditLogService;
use App\Services\BarangayLocationService;
use App\Services\PushNotificationService;
use App\Services\IncidentReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class IncidentController extends Controller
{
    /**
     * Regular citizen-submitted reports only. SOS panic-button alerts
     * are excluded here and live on their own page — see sosIndex().
     *
     * incident.blade.php splits them into four tabs (Incident::$stage):
     *   Pending     waiting for the admin to approve or decline
     *   Active      approved and sent to the responders
     *   For Review  the responders sent their Final report — the admin
     *               marks it resolved (confirmResolved()) or sends it back
     *               (returnReport())
     *   Resolved    resolved or declined, plus incidents handled outside
     *               the app that the admin added (storeManual())
     */
    public function index()
    {
        $incidents = Incident::with(['citizen', 'responders'])
            ->where('emergency_type', '!=', 'SOS Emergency')
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'low' THEN 3 ELSE 4 END ASC")
            ->orderByDesc('created_at')
            ->get();

        return view('incident', compact('incidents'));
    }

    /**
     * How an incident is named in the Audit Log — by what, where and when
     * instead of its ID number, e.g. "Fire report at Rizal St, Brgy.
     * Carmay East, Rosales, Pangasinan (Oct 13, 2026 9:01 AM)".
     */
    private function describeIncident(Incident $incident): string
    {
        $what = $incident->emergency_type === 'SOS Emergency'
            ? 'SOS alert' . ($incident->sos_emergency_type ? " ({$incident->sos_emergency_type})" : '')
            : ($incident->emergency_type ?: 'incident') . ' report';
        if ($incident->isManualRecord()) {
            // Added by MDRRMO on the Resolved tab — no one used the app.
            $what = ($incident->emergency_type ?: 'incident') . ' incident (handled outside the app)';
        } elseif (! $incident->citizen_id) {
            $what = "guest {$what}"; // no resident account behind it
        }

        // Location labels start with "SOS Alert — " / "Guest Report — ".
        $where = trim((string) preg_replace('/^(SOS Alert|Guest Report)\s*—\s*/u', '', (string) $incident->location));
        $when  = $incident->created_at?->format('M d, Y g:i A');

        return $what
            . ($where !== '' ? " at {$where}" : '')
            . ($when ? " ({$when})" : '');
    }

    public function sosIndex()
    {
        $sosAlerts = Incident::with(['citizen', 'responders'])
            ->where('emergency_type', 'SOS Emergency')
            ->orderByDesc('created_at')
            ->get();

        return view('sos-alerts', compact('sosAlerts'));
    }

    /**
     * Everything critical and unresolved — regular incidents AND SOS
     * alerts together. Neither index() nor sosIndex() alone can answer
     * "show me everything critical right now": index() explicitly
     * excludes SOS Emergency, and SOS is *always* priority='critical' by
     * definition (see Api\IncidentController::sos()), so a critical SOS
     * would never appear on the regular Incidents page no matter how
     * it's filtered. This is what the Dashboard's "Critical" card
     * actually links to — its count already included SOS records, so
     * the destination needs to as well or the numbers don't match.
     */
    public function criticalIndex()
    {
        $criticalIncidents = Incident::with(['citizen', 'responders'])
            ->where('priority', 'critical')
            ->where('status', '!=', 'resolved')
            ->orderByDesc('created_at')
            ->get();

        return view('critical-incidents', compact('criticalIncidents'));
    }

    /**
     * Same problem as criticalIndex() above, for three more Dashboard
     * cards: Total Incidents, Responding, and Resolved all count from
     * the raw Incident model with no emergency_type exclusion (so their
     * counts already include SOS records), but used to link to the
     * regular Incidents page, which explicitly excludes SOS Emergency.
     * One reusable page instead of three near-duplicates of
     * criticalIndex() — optional ?status= query param scopes it, absent
     * means "everything" (Total Incidents).
     */
    public function overviewIndex(Request $request)
    {
        $status = $request->query('status');

        $query = Incident::with(['citizen', 'responders'])->orderByDesc('created_at');

        if (in_array($status, ['pending', 'acknowledged', 'responding', 'for_review', 'resolved'], true)) {
            $query->where('status', $status);
        }

        $incidents = $query->get();

        return view('incidents-overview', compact('incidents', 'status'));
    }

    public function updatePriority(Request $request, Incident $incident)
    {
        $request->validate([
            'priority' => ['required', 'in:critical,high,low'],
        ]);

        $old=$incident->priority;
        $incident->update(['priority' => $request->priority]);

        AuditLogService::Log(
            'updated',
            "Set priority of the {$this->describeIncident($incident)} to {$request->priority}.",
            $incident,
            ['priority' => $old],
            ['priority' => $request->priority]
        );

        return back()->with('success', 'Priority updated.');
    }

    public function updateStatus(Request $request, Incident $incident)
    {
        $request->validate([
            'status' => ['required', 'in:pending,acknowledged,responding,resolved'],
        ]);

        $old=$incident->status;
        $incident->update(['status' => $request->status]);

        AuditLogService::log(
            'updated',
            "Set status of the {$this->describeIncident($incident)} to {$request->status}.",
            $incident,
            ['status' => $old],
            ['status' => $request->status]
        );

        return back()->with('success', 'Status updated.');
    }

    public function updateNote(Request $request, Incident $incident)
    {
        $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $incident->update(['admin_notes' => $request->admin_notes]);

        AuditLogService::log('updated', "Updated admin notes on the {$this->describeIncident($incident)}.", $incident);

        return back()->with('success', 'Note saved.');
    }

    /**
     * Approves a report or SOS waiting in Pending Review and sends it to
     * the responders.
     *
     * For a report, this is where the admin decides its priority —
     * Critical, High or Low, picked in the Approve dialog. Nothing sets
     * priority automatically any more. SOS alerts are always Critical, so
     * their Approve button doesn't ask.
     *
     * A logged-in citizen also gets a push saying their report was
     * approved; a guest has no account to notify.
     */
    public function approve(Request $request, Incident $incident, PushNotificationService $push)
    {
        if (! $incident->needs_review) {
            return back()->with('success', 'This report was already approved.');
        }

        $isSos = $incident->emergency_type === 'SOS Emergency';

        if (! $isSos) {
            $request->validate([
                'priority' => ['required', 'in:critical,high,low'],
            ], [
                'priority.required' => 'Choose a priority (Critical, High or Low) before approving the report.',
                'priority.in'       => 'Choose a priority (Critical, High or Low) before approving the report.',
            ]);
        }

        $oldPriority = $incident->priority;
        $changes = [
            'needs_review' => false,
            'reviewed_at'  => now(),
        ];
        if (! $isSos) {
            $changes['priority'] = $request->priority;
        }
        $incident->update($changes);

        $push->dispatchToRespondersForIncident($incident);

        // Only a logged-in citizen's report can be told apart from a pure
        // guest submission — a guest has no citizen_id and therefore no
        // account to push a notification to.
        if ($incident->citizen_id && $incident->citizen) {
            $push->notifyCitizen(
                $incident->citizen,
                'Report Approved',
                $isSos
                    ? 'Your SOS alert has been approved and sent to responders.'
                    : "Your {$incident->emergency_type} report has been approved and sent to responders."
            );
        }

        if ($isSos) {
            AuditLogService::log(
                'approved',
                "Approved the {$this->describeIncident($incident)} — dispatched to responders.",
                $incident
            );

            return back()->with('success', 'Report approved and sent to responders.');
        }

        $priorityLabel = ucfirst($incident->priority);

        AuditLogService::log(
            'approved',
            "Approved the {$this->describeIncident($incident)} as {$priorityLabel} priority — dispatched to responders.",
            $incident,
            ['priority' => $oldPriority],
            ['priority' => $incident->priority]
        );

        return back()->with('success', "Report approved as {$priorityLabel} priority and sent to responders.");
    }

    /**
     * Declines a Pending Review report instead of approving it — e.g.
     * spam, a duplicate, or too vague to dispatch on. Requires a reason
     * (shown to the citizen, if there is one to notify) and never
     * dispatches to responders. Same citizen_id-gated notification rule
     * as approve(): a pure guest submission has no account to notify.
     */
    public function decline(Request $request, Incident $incident, PushNotificationService $push)
    {
        if (! $incident->needs_review) {
            return back()->with('success', 'This report was already reviewed.');
        }

        $request->validate([
            'decline_reason' => ['required', 'string', 'max:500'],
        ]);

        $incident->update([
            'needs_review'    => false,
            'reviewed_at'     => now(),
            'declined_at'     => now(),
            'decline_reason'  => $request->decline_reason,
        ]);

        if ($incident->citizen_id && $incident->citizen) {
            $isSos = $incident->emergency_type === 'SOS Emergency';
            $push->notifyCitizen(
                $incident->citizen,
                'Report Declined',
                $isSos
                    ? "Your SOS alert was declined: {$request->decline_reason}"
                    : "Your {$incident->emergency_type} report was declined: {$request->decline_reason}"
            );
        }

        AuditLogService::log(
            'rejected',
            "Declined the {$this->describeIncident($incident)} — {$request->decline_reason}",
            $incident
        );

        return back()->with('success', 'Report declined.');
    }

    /**
     * Permanently deletes a DECLINED report or SOS alert (spam, a prank,
     * a duplicate…). Anything that wasn't declined — waiting for review,
     * active or resolved — can't be deleted. Its photos are removed from
     * storage and its responder links go with it (cascade). The Audit Log
     * keeps a line with what was deleted, who sent it and why it was
     * declined, since the record itself is gone.
     *
     * Goes back to the list it belongs to: SOS Alerts' Declined tab, or
     * Incidents' Resolved tab (where declined reports are listed).
     */
    public function destroy(Incident $incident)
    {
        $isSos = $incident->emergency_type === 'SOS Emergency';
        $list = $isSos ? route('sos-alerts', ['tab' => 'declined']) : route('incident', ['tab' => 'resolved']);

        if (! $incident->declined_at) {
            return redirect($list)->withErrors(['delete' => 'Only declined reports and SOS alerts can be deleted.']);
        }

        $what = $this->describeIncident($incident);

        AuditLogService::log(
            'deleted',
            "Deleted the declined {$what}" . ($incident->decline_reason ? " — declined as: {$incident->decline_reason}" : '') . '.',
            $incident,
            [
                'type'           => $incident->display_type,
                'location'       => $incident->location,
                'reporter'       => $incident->reporter_name,
                'mobile'         => $incident->reporter_mobile,
                'reported_at'    => $incident->created_at?->toDateTimeString(),
                'declined_at'    => $incident->declined_at?->toDateTimeString(),
                'decline_reason' => $incident->decline_reason,
            ],
            null
        );

        // Photos on the public disk: photo_path is a JSON list (very old
        // rows hold one plain path), plus a resolution photo if any.
        $photos = [];
        if ($incident->photo_path) {
            $decoded = json_decode($incident->photo_path, true);
            $photos = is_array($decoded) ? $decoded : [$incident->photo_path];
        }
        if ($incident->resolution_photo_path) {
            $photos[] = $incident->resolution_photo_path;
        }
        $photos = array_values(array_filter($photos, fn ($p) => is_string($p) && $p !== ''));

        $incident->delete();

        try {
            if ($photos) {
                Storage::disk('public')->delete($photos);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Deleted incident photos could not be removed.', ['error' => $e->getMessage()]);
        }

        return redirect($list)->with('success', $isSos ? 'Declined SOS alert deleted.' : 'Declined report deleted.');
    }

    /**
     * For Review → "Mark as Resolved". The admin has checked the
     * responders' Final MDRRMC report and accepts it: the incident is now
     * resolved. The PDF is rebuilt with who reviewed it ("Reviewed by"),
     * the resident who reported it (if any) and the responding team are
     * told.
     */
    public function confirmResolved(Incident $incident, IncidentReportService $reports, PushNotificationService $push)
    {
        if (! $incident->isForReview()) {
            return back()->withErrors(['review' => 'This incident is no longer waiting for review.']);
        }

        $admin = auth('web')->user();
        $changes = [
            'status'      => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $admin?->id,
        ];

        if (is_array($incident->report_data)) {
            $report = $incident->report_data;
            $report['reviewed_by'] = $admin?->name ? "{$admin->name}, MDRRMO Rosales" : 'MDRRMO Rosales';
            $report['reviewed_at'] = now()->format('F j, Y g:i A');
            $changes['report_data'] = $report;
        }

        $incident->update($changes);
        $this->rebuildReportPdf($incident, $reports);

        $isSos = $incident->emergency_type === 'SOS Emergency';
        if ($incident->citizen_id && $incident->citizen) {
            $push->notifyCitizen(
                $incident->citizen,
                'Incident Resolved',
                $isSos
                    ? 'Your SOS alert has been resolved. Stay safe!'
                    : "Your {$incident->emergency_type} report has been resolved. Thank you for reporting."
            );
        }
        $push->notifyResponders(
            $incident->responders,
            'Report accepted by MDRRMO',
            "{$incident->display_type} — {$incident->location}: your Final report was accepted. The incident is now resolved."
        );

        AuditLogService::log(
            'resolved',
            "Reviewed the responders' Final report and marked the {$this->describeIncident($incident)} as resolved.",
            $incident,
            ['status' => 'for_review'],
            ['status' => 'resolved']
        );

        return back()->with('success', 'Report accepted. The incident is now resolved.');
    }

    /**
     * For Review → "Return to Responders". Something in the Final report
     * needs fixing. The report becomes Initial again and the incident
     * goes back to Active (status responding), so the team can update it
     * in the app and send it as Final again — it then comes back here.
     * The team gets a push with the reason; the app shows it too
     * (Api\IncidentController::reportForResponder()).
     */
    public function returnReport(Request $request, Incident $incident, IncidentReportService $reports, PushNotificationService $push)
    {
        if (! $incident->isForReview()) {
            return back()->withErrors(['review' => 'This incident is no longer waiting for review.']);
        }

        $request->validate([
            'return_reason' => ['required', Rule::in(Incident::RETURN_REASONS)],
            'return_note'   => ['nullable', 'required_if:return_reason,Other', 'string', 'max:500'],
        ], [
            'return_reason.required' => 'Choose what needs to be fixed before sending the report back.',
            'return_reason.in'       => 'Choose what needs to be fixed before sending the report back.',
            'return_note.required_if' => 'Write what the responders need to fix.',
            'return_note.max'        => 'Keep the note under 500 characters.',
        ]);

        $note   = trim((string) $request->input('return_note'));
        $reason = $request->return_reason === 'Other'
            ? $note
            : $request->return_reason . ($note !== '' ? ": {$note}" : '');

        $changes = [
            'status'               => 'responding',
            'report_submitted_at'  => null,
            'report_returned_at'   => now(),
            'report_return_reason' => $reason,
        ];

        if (is_array($incident->report_data)) {
            $report = $incident->report_data;
            $report['report_type']  = 'initial';
            $report['finalized_at'] = null;
            unset($report['reviewed_by'], $report['reviewed_at']);
            $changes['report_data'] = $report;
        }

        $incident->update($changes);
        $this->rebuildReportPdf($incident, $reports);

        $push->notifyResponders(
            $incident->responders,
            'Report sent back by MDRRMO',
            "{$incident->display_type} — {$incident->location}: " . rtrim($reason, '. ') . '. Update the report and submit it as Final again.'
        );

        AuditLogService::log(
            'rejected',
            "Sent the Final report for the {$this->describeIncident($incident)} back to the responders — {$reason}",
            $incident,
            ['status' => 'for_review'],
            ['status' => 'responding', 'return_reason' => $reason]
        );

        return back()->with('success', 'Report sent back to the responders. It comes back to For Review when they submit it again.');
    }

    /**
     * Resolved → "Add Resolved Report": the form for an incident MDRRMO
     * handled outside the app (a walk-in, a phone call, a radio report…).
     */
    public function createManual()
    {
        return view('incident-manual-report', [
            'barangays' => array_keys(BarangayLocationService::allCoordinates()),
        ]);
    }

    /**
     * Saves an incident that was handled outside the app, already
     * resolved, with its MDRRMC Incident Report (same PDF the responders
     * file). It shows on the Resolved tab marked "Added by MDRRMO".
     *
     * It's dated when it happened (created_at), so the Dashboard and
     * Reports & Analytics count it on the right day. No one is notified
     * and nothing is sent to the responders — it's a record.
     */
    public function storeManual(Request $request, IncidentReportService $reports)
    {
        // "0917 123 4567" / "+639171234567" → "09171234567"; landlines stay as typed.
        $phone = preg_replace('/\s+/', ' ', trim((string) $request->input('reporter_mobile')));
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) === 12 && str_starts_with($digits, '639')) {
            $phone = '0' . substr($digits, 2);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $phone = '0' . $digits;
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '09')) {
            $phone = $digits;
        }
        $request->merge(['reporter_mobile' => $phone]);

        $casualtyRow = ['nullable', 'string'];
        $validated = $request->validate([
            'emergency_type'  => ['required', Rule::in(Incident::TYPES)],
            'other_type'      => ['nullable', 'required_if:emergency_type,Other', 'string', 'max:60'],
            'priority'        => ['required', 'in:critical,high,low'],
            'barangay'        => ['required', Rule::in(array_keys(BarangayLocationService::allCoordinates()))],
            'street'          => ['nullable', 'string', 'max:150'],
            'occurred_at'     => ['required', 'date', 'before_or_equal:now'],
            'resolved_at'     => ['required', 'date', 'after_or_equal:occurred_at', 'before_or_equal:now'],
            'reported_via'    => ['required', Rule::in(Incident::REPORTED_VIA)],
            'reporter_name'   => ['nullable', 'string', 'max:120'],
            'reporter_mobile' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+()\-\s]{7,20}$/'],
            'narrative'       => ['required', 'string', 'max:2000'],
            'effects'         => ['nullable', 'string', 'max:1000'],
            'actions_taken'   => ['required', 'string', 'max:1500'],
            'released_by'     => ['nullable', 'string', 'max:255'],
            'casualties'                => ['nullable', 'array'],
            'casualties.*'              => ['nullable', 'array', 'max:30'],
            'casualties.*.*.name'       => [...$casualtyRow, 'max:120'],
            'casualties.*.*.age'        => [...$casualtyRow, 'max:10'],
            'casualties.*.*.sex'        => [...$casualtyRow, 'max:10'],
            'casualties.*.*.address'    => [...$casualtyRow, 'max:160'],
            'casualties.*.*.cause'      => [...$casualtyRow, 'max:120'],
            'casualties.*.*.remarks'    => [...$casualtyRow, 'max:200'],
            'team'            => ['nullable', 'array', 'max:20'],
            'team.*.name'     => ['nullable', 'string', 'max:120'],
            'team.*.agency'   => ['nullable', 'string', 'max:160'],
            'photo'           => ['nullable', 'image', 'max:5120'],
        ], [
            'emergency_type.required' => 'Choose the type of incident.',
            'other_type.required_if'  => 'Type what kind of incident it was.',
            'priority.required'       => 'Choose the priority (Critical, High or Low).',
            'barangay.required'       => 'Choose the barangay where it happened.',
            'barangay.in'             => 'Choose a barangay of Rosales from the list.',
            'occurred_at.required'    => 'Enter when the incident happened.',
            'occurred_at.before_or_equal' => "The incident's date and time can't be in the future.",
            'resolved_at.required'    => 'Enter when the incident was resolved.',
            'resolved_at.after_or_equal' => 'It can only be resolved after it happened — check both dates and times.',
            'resolved_at.before_or_equal' => "The resolved date and time can't be in the future.",
            'reported_via.required'   => 'Choose how MDRRMO learned about the incident.',
            'reporter_mobile.regex'   => 'Enter a valid contact number, e.g. 09171234567.',
            'narrative.required'      => 'Describe what happened (Details / Narrative).',
            'actions_taken.required'  => 'Write the actions taken.',
            'photo.image'             => 'The photo must be an image (JPG or PNG).',
            'photo.max'               => 'The photo must be 5 MB or smaller.',
        ]);

        $admin    = auth('web')->user();
        $type     = $validated['emergency_type'] === 'Other'
            ? trim($validated['other_type'])
            : $validated['emergency_type'];
        $street   = trim((string) ($validated['street'] ?? ''));
        $location = ($street !== '' ? "{$street}, " : '') . "Brgy. {$validated['barangay']}, Rosales, Pangasinan";
        $occurred = Carbon::parse($validated['occurred_at']);
        $resolved = Carbon::parse($validated['resolved_at']);

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('incident_resolution_photos', 'public')
            : null;

        $incident = new Incident([
            'citizen_id'            => null,
            'emergency_type'        => $type,
            'location'              => $location,
            'latitude'              => null,
            'longitude'             => null,
            'description'           => $validated['narrative'],
            'status'                => 'resolved',
            'priority'              => $validated['priority'],
            'needs_review'          => false,
            'reviewed_at'           => now(),
            'resolution_notes'      => $validated['narrative'],
            'resolution_photo_path' => $photoPath,
            'resolved_at'           => $resolved,
            'resolved_by'           => $admin?->id,
            'source'                => 'manual',
            'reported_via'          => $validated['reported_via'],
            'guest_name'            => filled($validated['reporter_name'] ?? null) ? trim($validated['reporter_name']) : null,
            'guest_mobile'          => filled($validated['reporter_mobile'] ?? null) ? $validated['reporter_mobile'] : null,
        ]);
        // Dated when it happened, not when it was typed in.
        $incident->created_at = $occurred;
        $incident->save();

        $releasedBy = trim((string) ($validated['released_by'] ?? ''));
        if ($releasedBy === '') {
            $releasedBy = $admin?->name ? strtoupper($admin->name) . ', MDRRMO Rosales' : 'MDRRMO Rosales';
        }

        $report = $reports->normalize($incident, null, [
            'report_type'   => 'final',
            'location'      => $location,
            'narrative'     => $validated['narrative'],
            'casualties'    => $validated['casualties'] ?? [],
            'effects'       => $validated['effects'] ?? '',
            'actions_taken' => $validated['actions_taken'],
            'released_by'   => $releasedBy,
        ]);
        $report['team']         = $reports->teamFromInput($validated['team'] ?? []);
        $report['source']       = 'manual';
        $report['reported_via'] = $validated['reported_via'];
        $report['recorded_by']  = $admin?->name ? "{$admin->name}, MDRRMO Rosales" : 'MDRRMO Rosales';
        $report['recorded_at']  = now()->format('F j, Y g:i A');
        $report['resolved_at']  = $resolved->format('F j, Y g:i A');

        $incident->update(['report_data' => $report]);
        $this->rebuildReportPdf($incident, $reports);

        AuditLogService::log(
            'created',
            "Added a resolved {$this->describeIncident($incident)} — reported to MDRRMO via " . strtolower($validated['reported_via']) . '.',
            $incident
        );

        return redirect()
            ->route('incident.detail', $incident)
            ->with('success', 'Resolved report added. It is listed on the Resolved tab.');
    }

    /** Rebuilds the MDRRMC report PDF. A PDF problem never undoes the change itself. */
    private function rebuildReportPdf(Incident $incident, IncidentReportService $reports): void
    {
        if (! is_array($incident->report_data)) {
            return;
        }

        try {
            $path = $reports->generate($incident->fresh());
            if ($path) {
                $incident->update(['report_pdf_path' => $path]);
            }
        } catch (\Throwable $e) {
            Log::warning('Incident report PDF could not be rebuilt.', ['incident' => $incident->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * The MDRRMC Incident Report PDF the responder filed when resolving
     * this incident. Stored on the PRIVATE disk, so it's only ever served
     * through this auth-gated route (never via /storage). `?download=1`
     * forces a download instead of opening it in the browser tab.
     *
     * If the PDF is missing but the report data exists (e.g. dompdf wasn't
     * installed yet when it was filed), it's generated on the spot.
     */
    public function reportPdf(Request $request, Incident $incident, IncidentReportService $reports)
    {
        abort_unless($incident->report_data, 404, 'No report was filed for this incident.');

        $disk = Storage::disk(IncidentReportService::DISK);
        $path = $incident->report_pdf_path;

        if (! $path || ! $disk->exists($path)) {
            $path = $reports->generate($incident);
            abort_unless($path, 503, 'PDF generator is not installed (composer require barryvdh/laravel-dompdf).');
            $incident->update(['report_pdf_path' => $path]);
        }

        // Named by type and date/time, not the incident's ID number.
        $filename = 'MDRRMC-Incident-Report_'
            . \Illuminate\Support\Str::slug($incident->emergency_type ?: 'incident')
            . '_' . $incident->created_at->format('Y-m-d_Hi') . '.pdf';

        return $request->boolean('download')
            ? $disk->download($path, $filename)
            : response($disk->get($path), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"{$filename}\"",
            ]);
    }

    public function show(Incident $incident)
    {
        $incident->load(['citizen', 'responders']);
        return view('incident-details', compact('incident'));
    }

    /**
     * Dedicated SOS detail page — separate from show() above because an
     * SOS needs different things front-and-center: a one-tap "Call
     * Reporter" button (a generic incident report has no equivalent
     * urgency), the Approve action inline for a guest SOS instead of
     * only reachable from the list, and no priority dropdown since an
     * SOS is always critical by definition.
     */
    public function sosShow(Incident $incident)
    {
        $incident->load(['citizen', 'responders']);
        return view('sos-details', compact('incident'));
    }

    public function latestSos()
    {
        // A declined SOS keeps status "pending", so leave those out — the
        // siren is for alerts that still need someone to act.
        $latest = Incident::where('emergency_type', 'SOS Emergency')
            ->where('status', 'pending')
            ->whereNull('declined_at')
            ->orderByDesc('created_at')
            ->first(['id', 'created_at', 'location']);

        // Lets the overlay say "3 SOS alerts waiting" instead of only
        // ever showing the single newest one and silently burying the
        // rest — matters most exactly when it matters most, i.e. several
        // SOS reports landing close together.
        $pendingCount = Incident::where('emergency_type', 'SOS Emergency')
            ->where('status', 'pending')
            ->whereNull('declined_at')
            ->count();

        return response()->json(['latest' => $latest, 'pendingCount' => $pendingCount]);
    }

    /**
     * Powers the topbar notification bell — deliberately EXCLUDES SOS
     * Emergency, since SOS already has its own full-screen siren overlay
     * (sos-alert-overlay). Without this exclusion, every SOS would ding
     * the bell too, on top of triggering the siren — same event,
     * announced twice. This is for everything else: regular incident
     * reports (including guest ones pending review).
     */
    public function latestIncidentNotifications(Request $request)
    {
        $since = $request->query('since');

        $query = Incident::where('emergency_type', '!=', 'SOS Emergency');
        if ($since) {
            $query->where('created_at', '>', $since);
        }
        $newCount = (clone $query)->count();

        $recent = Incident::where('emergency_type', '!=', 'SOS Emergency')
            ->orderByDesc('created_at')
            ->take(5)
            ->get(['id', 'emergency_type', 'location', 'status', 'created_at']);

        return response()->json([
            'newCount'   => $newCount,
            'recent'     => $recent,
            // Plain 'Y-m-d H:i:s', NOT toIso8601String(). created_at is
            // stored as a naive DATETIME (no timezone suffix) — the
            // client round-trips this value straight back as ?since= on
            // the next poll, and MySQL compares it against created_at
            // as a plain string/date. An ISO-8601 string carries a
            // "+08:00" offset that a DATETIME column comparison doesn't
            // understand, so `where('created_at', '>', $since)` matched
            // nothing and newCount was always 0 — the bell's dropdown
            // still rendered fine (it isn't since-filtered), but the
            // badge and notifSound, both gated on newCount > 0, never
            // fired. Matching the column's own format fixes the compare.
            'serverTime' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * One-time cleanup for a real bug that's now fixed in
     * BarangayLocationService: it used to silently default an unresolved
     * municipality to 'Rosales' instead of saying "unknown" — so a pin
     * in a real place like Vacante (an actual barangay, but of
     * Binalonan, not Rosales) got mislabeled "Vacante, Rosales" AND
     * auto-dispatched to responders, since isWithinRosales() naturally
     * saw the fabricated "Rosales" text and let it through.
     *
     * This re-runs the (now-corrected) location resolution + boundary
     * check against every STILL-PENDING, not-yet-resolved incident —
     * already-resolved/responding ones are left alone; responders were
     * already dispatched and there's no undoing that, so silently
     * flipping needs_review on a closed case would just be confusing,
     * not useful. For anything still pending: refreshes the location
     * text, and flips needs_review to true if the corrected check now
     * says it's actually outside Rosales (so it stops responders being
     * dispatched to it going forward, until an admin confirms it).
     *
     * A button, not an artisan command — this deployment doesn't have
     * reliable CLI access, so this has to be triggerable from the web.
     */
    public function recheckLocations(BarangayLocationService $locationService)
    {
        // Every pinned incident is looked at, whatever its status:
        //   - still-pending ones get their auto-made label refreshed and the
        //     "outside Rosales" check re-run (as before);
        //   - older/closed ones only get fixed when their label is still
        //     raw GPS coordinates ("SOS Alert — Lat 15.89…, Lng 120.63…")
        //     or the generic "SOS Alert, Rosales" — they now show the
        //     street and barangay instead.
        // A citizen's own typed address is never touched.
        $candidates = Incident::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $relabeled = 0;
        $flaggedForReview = 0;

        foreach ($candidates as $incident) {
            $updates = [];
            $location = (string) $incident->location;
            $isPending = $incident->status === 'pending';

            $wasAutoResolved = $incident->emergency_type === 'SOS Emergency'
                || str_starts_with($location, 'Guest Report — ')
                || str_starts_with($location, 'SOS Alert — ')
                || $location === 'Guest Report, Rosales'
                || $location === 'SOS Alert, Rosales'
                || $location === '';

            $showsCoordinates = (bool) preg_match('/\bLat\s*-?\d+(\.\d+)?\s*,\s*Lng\s*-?\d+/i', $location)
                || in_array($location, ['', 'Guest Report, Rosales', 'SOS Alert, Rosales'], true);

            if ($wasAutoResolved && ($isPending || $showsCoordinates)) {
                // No OpenStreetMap here: its free service allows about one
                // lookup per second, too slow for a whole batch. Google
                // (cached) and the offline barangay lookup are used instead.
                $newLocation = $incident->emergency_type === 'SOS Emergency'
                    ? $locationService->approximateSosLabel($incident->latitude, $incident->longitude, false)
                    : $locationService->approximateReportLabel($incident->latitude, $incident->longitude, false);

                if ($newLocation !== $incident->location) {
                    $updates['location'] = $newLocation;
                    $relabeled++;
                }
            }

            // Only still-pending reports can be held back for review —
            // responders were already dispatched for the rest.
            if ($isPending && ! $incident->needs_review) {
                $stillWithinRosales = $locationService->isWithinRosales($incident->latitude, $incident->longitude);
                if (! $stillWithinRosales) {
                    $updates['needs_review'] = true;
                    $flaggedForReview++;
                }
            }

            if ($updates) {
                $incident->update($updates);
            }
        }

        AuditLogService::log(
            'updated',
            "Rechecked locations for {$candidates->count()} incidents — relabeled {$relabeled}, flagged {$flaggedForReview} for review.",
        );

        return back()->with(
            'success',
            "Rechecked {$candidates->count()} incidents: {$relabeled} location(s) now show the street/barangay instead of coordinates, {$flaggedForReview} newly flagged as outside Rosales and now pending review."
        );
    }
}