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
use Illuminate\Support\Facades\RateLimiter;
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

        // Pending = waiting for approval. Approved with no one accepted yet
        // is Awaiting Responder (status 'pending' too, but it's Active).
        match ($status) {
            'pending'  => $query->where('needs_review', true)->whereNull('declined_at'),
            'awaiting' => $query->where('needs_review', false)->whereNull('declined_at')->where('status', 'pending'),
            'acknowledged', 'responding', 'for_review', 'resolved' => $query->where('status', $status),
            default    => null,
        };

        $incidents = $query->get();

        return view('incidents-overview', compact('incidents', 'status'));
    }

    /**
     * The priority is chosen while the report is Pending (approve()) and
     * can't be changed after it's approved — the Active tab and Incident
     * Details only show it. This refuses any change after that.
     */
    public function updatePriority(Request $request, Incident $incident)
    {
        if (! $incident->needs_review) {
            return back()->withErrors(['priority' => 'The priority is set when the report is approved and can\'t be changed after that.']);
        }

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
     * Approve — Incident Details → Administrative Actions, while it's
     * Pending. The lists only have View: an admin approves or declines
     * after checking the photos, the map and the reporter on the details
     * page.
     *
     *   priority  reports only — Critical, High or Low. Nothing sets it
     *             automatically, and it can't be changed after approving.
     *             SOS is always Critical.
     *
     * Approving sends it to all responders right away — every response
     * team (Incident::TEAMS) gets it and any of them can accept it (Lem:
     * "once accepted, it will send to all responder, but once approved: it
     * can notify specific responder"). On Active, the admin can notify
     * specific teams (updateAssignment()). A logged-in resident is told it
     * was approved; a guest has no account to notify.
     */
    public function approve(Request $request, Incident $incident, PushNotificationService $push)
    {
        if (! $incident->needs_review) {
            return back()->with('success', 'This report was already approved.');
        }

        $isSos = $incident->isSos();

        if (! $isSos) {
            $request->validate(['priority' => ['required', 'in:critical,high,low']], $this->assignmentMessages());
        }

        $oldPriority = $incident->priority;
        $changes = [
            'needs_review'     => false,
            'reviewed_at'      => now(),
            'reviewed_by'      => auth('web')->id(),
            'assignment_notes' => null,
        ];
        if (! $isSos) {
            $changes['priority'] = $request->priority;
        }
        $incident->update($changes);

        $this->sendToAllResponders($incident, $push);

        $details = redirect()->route($isSos ? 'sos.detail' : 'incident.detail', $incident);

        if ($isSos) {
            AuditLogService::log(
                'approved',
                "Approved the {$this->describeIncident($incident)} — sent to all responders.",
                $incident,
                null,
                ['teams' => $incident->assignedTeamCodes()]
            );

            return $details->with('success', 'SOS approved and sent to all responders.');
        }

        $priorityLabel = ucfirst($incident->priority);

        AuditLogService::log(
            'approved',
            "Approved the {$this->describeIncident($incident)} as {$priorityLabel} priority — sent to all responders.",
            $incident,
            ['priority' => $oldPriority],
            ['priority' => $incident->priority, 'teams' => $incident->assignedTeamCodes()]
        );

        return $details->with('success', "Report approved as {$priorityLabel} priority and sent to all responders.");
    }

    /**
     * Incident Details → Notify specific responders, while the incident is
     * Active. Approving already sent it to every response team; this
     * notifies the teams the admin ticks:
     *
     *   no one has accepted yet  "🚨 Fire report — no one has accepted yet"
     *   someone is on it         "🚨 Fire — MDRRMO needs your team", so the
     *                            team can join as backup
     *
     *   teams[]           BFP / PNP / SARS / HCU — the teams to notify
     *   assignment_notes  instructions for the team (optional): saved,
     *                     shown in the responder app and sent along
     *
     * It never takes the incident away from a team. A ticked team that
     * couldn't see it yet (sent to some teams only, before approving sent
     * it to all) is added, and a responder on a ticked team who declined it
     * gets it back in their list — otherwise the alert would point to
     * something they can't see. Each team can be notified once a minute, so
     * a double click doesn't send it twice. The priority isn't changed here
     * (a priority sent along is ignored).
     *
     * An incident approved while approving didn't send it anywhere
     * (Incident::awaitingTeam(), item 36) is sent to all responders, the
     * same as approving.
     */
    public function updateAssignment(Request $request, Incident $incident, PushNotificationService $push)
    {
        if ($incident->stage !== 'active') {
            return back()->withErrors(['assignment' => $incident->needs_review
                ? "Approve it first. It's sent to all responders when you approve it."
                : 'The responders can only be notified while the incident is active.']);
        }

        if ($incident->awaitingTeam()) {
            $this->sendToAllResponders($incident, $push);

            AuditLogService::log(
                'updated',
                "Sent the {$this->describeIncident($incident)} to all responders.",
                $incident,
                ['teams' => []],
                ['teams' => $incident->assignedTeamCodes()]
            );

            return back()->with('success', "Sent to all responders. They've been notified.");
        }

        $request->validate($this->assignmentRules(), $this->assignmentMessages());

        $teams = $this->orderedTeams($request->input('teams'));
        $notes = $this->cleanText($request->input('assignment_notes'));

        $justNotified = array_values(array_filter(
            $teams,
            fn ($team) => RateLimiter::tooManyAttempts($this->notifyKey($incident, $team), 1)
        ));
        if ($justNotified) {
            $many = count($justNotified) > 1;

            return back()->withInput()->withErrors(['assignment' => $this->teamList($justNotified)
                . ($many ? ' were' : ' was') . ' just notified. Wait a minute before notifying '
                . ($many ? 'them' : 'it') . ' again.']);
        }
        foreach ($teams as $team) {
            RateLimiter::hit($this->notifyKey($incident, $team), 60);
        }

        $someoneOnIt = $incident->responders()->exists();
        $before      = $incident->assignedTeamCodes();
        // No list at all (approved before teams were assigned): every team
        // sees it already.
        $added = $incident->assigned_teams === null ? [] : array_values(array_diff($teams, $before));
        $old   = ['teams' => $before, 'assignment_notes' => $incident->assignment_notes];

        $changes = ['assignment_notes' => $notes];
        if ($added) {
            $changes['assigned_teams'] = $this->orderedTeams(array_merge($before, $added));
        }
        $incident->update($changes);

        $declined = $incident->declinedBy()->whereIn('responders.agency', $teams)->pluck('responders.id');
        if ($declined->isNotEmpty()) {
            $incident->declinedBy()->detach($declined);
        }

        $kind = $someoneOnIt ? 'help' : 'again';
        $push->dispatchToRespondersForIncident($incident->fresh(), $teams, $kind);

        $teamList = $this->teamList($teams);
        $also = array_filter([
            $added ? 'added ' . $this->teamList($added) : null,
            $notes !== $old['assignment_notes'] ? 'updated the notes for the team' : null,
        ]);

        AuditLogService::log(
            'updated',
            ($someoneOnIt
                ? "Notified {$teamList} to help with the {$this->describeIncident($incident)}"
                : "Notified {$teamList} again about the {$this->describeIncident($incident)} — no one had accepted yet")
                . ($also ? ' (' . implode(', ', $also) . ')' : '') . '.',
            $incident,
            $old,
            [
                'notified'         => $teams,
                'kind'             => $kind,
                'teams'            => $incident->assignedTeamCodes(),
                'assignment_notes' => $notes,
            ]
        );

        return back()->with('success', $someoneOnIt
            ? "Asked {$teamList} to help. " . (count($teams) > 1 ? "They've" : "It's") . ' been notified.'
            : "Sent again to {$teamList}.");
    }

    /**
     * Sends an approved incident to every response team, and tells a
     * logged-in resident it was approved (a guest has no account to tell).
     */
    private function sendToAllResponders(Incident $incident, PushNotificationService $push): void
    {
        $incident->update([
            'assigned_teams' => array_keys(Incident::TEAMS),
            'assigned_at'    => now(),
        ]);

        $push->dispatchToRespondersForIncident($incident->fresh());

        if ($incident->citizen_id && $incident->citizen) {
            $push->notifyCitizen(
                $incident->citizen,
                'Report Approved',
                $incident->isSos()
                    ? 'Your SOS alert has been approved and sent to responders.'
                    : "Your {$incident->emergency_type} report has been approved and sent to responders."
            );
        }
    }

    /** The once-a-minute limit for notifying one team about one incident. */
    private function notifyKey(Incident $incident, string $team): string
    {
        return "incident-notify:{$incident->id}:{$team}";
    }

    /** Validation for the teams to notify and the notes for the team. */
    private function assignmentRules(): array
    {
        return [
            'teams'            => ['required', 'array', 'min:1'],
            'teams.*'          => ['string', Rule::in(array_keys(Incident::TEAMS))],
            'assignment_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function assignmentMessages(): array
    {
        return [
            'priority.required'    => 'Choose a priority (Critical, High or Low) before approving the report.',
            'priority.in'          => 'Choose a priority (Critical, High or Low) before approving the report.',
            'teams.required'       => 'Choose at least one team to notify.',
            'teams.min'            => 'Choose at least one team to notify.',
            'teams.*.in'           => 'Choose the response team from the list.',
            'assignment_notes.max' => 'Keep the notes for the team under 500 characters.',
        ];
    }

    /** The picked teams, without repeats, in the order of Incident::TEAMS. */
    private function orderedTeams($teams): array
    {
        return array_values(array_intersect(array_keys(Incident::TEAMS), array_unique((array) $teams)));
    }

    /** "BFP", "BFP and SARS", "BFP, PNP and SARS". */
    private function teamList(array $teams): string
    {
        if (count($teams) <= 1) {
            return $teams[0] ?? 'no team';
        }

        return implode(', ', array_slice($teams, 0, -1)) . ' and ' . end($teams);
    }

    private function cleanText($text): ?string
    {
        $text = trim(preg_replace('/[ \t]+/u', ' ', (string) $text));

        return $text === '' ? null : $text;
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
            'reviewed_by'     => auth('web')->id(),
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
            "{$incident->display_type} — {$incident->location}: your Final report was accepted. The incident is now resolved.",
            $incident
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
            "{$incident->display_type} — {$incident->location}: " . rtrim($reason, '. ') . '. Update the report and submit it as Final again.',
            $incident
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
        $incident->load(['citizen', 'responders', 'resolvedBy', 'reviewedBy']);
        return view('incident-details', ['incident' => $incident, 'isSos' => false]);
    }

    /**
     * SOS Details. sos-details.blade.php shows the same page as Incident
     * Details (incident-details.blade.php with $isSos = true); the SOS
     * version adds a one-tap "Call Reporter" button, has no priority
     * choice (an SOS is always Critical) and goes back to SOS Alerts.
     */
    public function sosShow(Incident $incident)
    {
        $incident->load(['citizen', 'responders', 'resolvedBy', 'reviewedBy']);
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
     * Powers the "report received" notice on every admin page
     * (partials/report-alert, item 46): the reports the responding teams
     * sent that MDRRMO may not have looked at yet, newest first —
     * Initial reports from the last 3 hours, and Final reports from the
     * last day that are still waiting for review. The page remembers the
     * ones the admin opened or closed, and rings for one that just came in.
     *
     * Also the For Review counts, so the purple counts in the menu change
     * without reloading the page.
     */
    public function latestReports()
    {
        $now   = now();
        $since = ['initial' => $now->copy()->subHours(3), 'final' => $now->copy()->subDay()];

        $reports = Incident::with('responders')
            // Sending a report updates the incident, so this only skips
            // incidents nothing happened to since.
            ->where('updated_at', '>=', $since['final'])
            ->where(function ($q) {
                $q->where('status', 'for_review')
                    ->orWhere(fn ($q) => $q->whereIn('status', ['pending', 'responding'])->whereNotNull('report_data'));
            })
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(function (Incident $incident) use ($since) {
                $report = $incident->teamReport();

                return $report && $report['at']->gte($since[$report['type']])
                    ? $this->reportNotice($incident, $report)
                    : null;
            })
            ->filter()
            ->sortByDesc('at')
            ->take(10)
            ->values();

        return response()->json([
            'reports'   => $reports,
            'forReview' => Incident::forReviewCounts(),
            'now'       => $now->getTimestamp(),
        ]);
    }

    /** One report, as partials/report-alert shows it. */
    private function reportNotice(Incident $incident, array $report): array
    {
        $isFinal = $report['type'] === 'final';
        $lead    = $incident->responders->sortBy(fn ($r) => (string) $r->pivot->accepted_at)->first();
        // "SOS Alert — Brgy. Acop, Rosales" → "Brgy. Acop, Rosales"
        $place = trim((string) preg_replace('/^(SOS Alert|Guest Report)\s*[—,]\s*/u', '', (string) $incident->location));

        return [
            'key'    => $report['key'],
            'type'   => $report['type'],
            'title'  => match (true) {
                $isFinal && $report['again'] => 'Final report sent again',
                $isFinal                     => 'Final report received',
                $report['revision'] > 1      => 'Initial report updated',
                default                      => 'Initial report received',
            },
            'what'   => $incident->display_type,
            'where'  => $place !== '' ? $place : 'No location',
            'from'   => $lead ? $lead->full_name . ($lead->agency ? " ({$lead->agency})" : '') : 'The responders',
            'at'     => $report['at']->getTimestamp(),
            'action' => $isFinal ? 'Review' : 'View',
            'url'    => $incident->isSos() ? route('sos.detail', $incident) : route('incident.detail', $incident),
        ];
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