<?php

namespace App\Http\Controllers;

use App\Models\BackupRequest;
use App\Models\Incident;
use App\Services\AuditLogService;
use App\Services\BarangayLocationService;
use App\Services\PlaceNames;
use App\Services\PushNotificationService;
use App\Services\IncidentReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
     *   For Review  the responders sent their Initial report — the admin
     *               can correct it (editReport()), then approves it as
     *               resolved (confirmResolved()) or sends it back
     *               (returnReport())
     *   Resolved    resolved or declined, plus incidents handled outside
     *               the app that the admin added (storeManual())
     */
    public function index()
    {
        $incidents = Incident::with(['citizen', 'responders', 'backupRequests'])
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
        $sosAlerts = Incident::with(['citizen', 'responders', 'backupRequests'])
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
     * Approve & Send — Incident Details → Administrative Actions, while it's
     * Pending. The lists only have View: an admin approves or declines
     * after checking the photos, the map and the reporter on the details
     * page.
     *
     *   priority          reports only — Critical, High or Low. Nothing
     *                     sets it automatically, and it can't be changed
     *                     after approving. SOS is always Critical.
     *   teams[]           BFP / PNP / SARS / HCU — the response team(s) it
     *                     goes to. The page ticks the teams usually needed
     *                     for the type (Incident::defaultTeams()).
     *   assignment_notes  instructions for the team (optional): sent along
     *                     and shown in the responder app.
     *
     * Only the chosen teams are notified, and only they can see and accept
     * it (item 53, as in item 26 — Lem: "while the report was on pending,
     * it will assign a responder team specifically"; items 40–41 had sent
     * it to the usual teams, then to all of them). MSWD still sees it on its
     * map. On Active, the admin can notify more teams (updateAssignment()).
     * A logged-in resident is told it was approved; a guest has no account
     * to notify.
     */
    public function approve(Request $request, Incident $incident, PushNotificationService $push)
    {
        if (! $incident->needs_review) {
            return back()->with('success', 'This report was already approved.');
        }

        $isSos = $incident->isSos();

        // The priority first, so a report missing both says that first.
        $rules = $isSos ? [] : ['priority' => ['required', 'in:critical,high,low']];
        $request->validate($rules + $this->assignmentRules(), array_merge($this->assignmentMessages(), [
            'teams.required' => 'Choose the response team to send it to.',
            'teams.min'      => 'Choose the response team to send it to.',
        ]));

        $teams = $this->orderedTeams($request->input('teams'));

        $oldPriority = $incident->priority;
        $changes = [
            'needs_review'     => false,
            'reviewed_at'      => now(),
            'reviewed_by'      => auth('web')->id(),
            'assignment_notes' => $this->cleanText($request->input('assignment_notes')),
        ];
        if (! $isSos) {
            $changes['priority'] = $request->priority;
        }
        $incident->update($changes);

        $this->sendToTeams($incident, $teams, $push);

        $sentTo  = $this->sentToLabel($teams);
        $details = redirect()->route($isSos ? 'sos.detail' : 'incident.detail', $incident);

        if ($isSos) {
            AuditLogService::log(
                'approved',
                "Approved the {$this->describeIncident($incident)} — sent to {$sentTo}.",
                $incident,
                null,
                ['teams' => $incident->assignedTeamCodes(), 'assignment_notes' => $incident->assignment_notes]
            );

            return $details->with('success', "SOS approved and sent to {$sentTo}.");
        }

        $priorityLabel = ucfirst($incident->priority);

        AuditLogService::log(
            'approved',
            "Approved the {$this->describeIncident($incident)} as {$priorityLabel} priority — sent to {$sentTo}.",
            $incident,
            ['priority' => $oldPriority],
            ['priority' => $incident->priority, 'teams' => $incident->assignedTeamCodes(), 'assignment_notes' => $incident->assignment_notes]
        );

        return $details->with('success', "Report approved as {$priorityLabel} priority and sent to {$sentTo}.");
    }

    /**
     * Incident Details → Notify specific responders, while the incident is
     * Active and no one has accepted it yet. Approving sent it to the
     * team(s) the admin chose (approve()); this notifies the teams the admin
     * ticks now: "🚨 Fire report — no one has accepted yet". Once a team is
     * responding, more teams are sent as backup instead (sendBackup(), item
     * 55) — this then says so.
     *
     *   teams[]           BFP / PNP / SARS / HCU — the teams to notify
     *   assignment_notes  instructions for the team (optional): saved,
     *                     shown in the responder app and sent along
     *
     * It never takes the incident away from a team. A ticked team that
     * couldn't see it yet (it wasn't sent to that team) is added and can
     * accept it, and a responder on a ticked team who declined it
     * gets it back in their list — otherwise the alert would point to
     * something they can't see. Each team can be notified once a minute, so
     * a double click doesn't send it twice. The priority isn't changed here
     * (a priority sent along is ignored).
     *
     * An incident approved while approving didn't send it anywhere
     * (Incident::awaitingTeam(), item 36) is sent to all responders (Send
     * to All Responders).
     */
    public function updateAssignment(Request $request, Incident $incident, PushNotificationService $push)
    {
        if ($incident->stage !== 'active') {
            return back()->withErrors(['assignment' => $incident->needs_review
                ? "Approve it first. It's sent to the team you choose when you approve it."
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

        // A team accepted it (maybe while this page was open): more teams
        // go as backup now — the page shows Send Backup.
        $lead = $incident->load('responders')->teamInOrder()->first();
        if ($lead) {
            return back()->withInput()->withErrors(['assignment' => BackupRequest::label($lead)
                . ' is responding now. To get more teams there, use Send Backup.']);
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

        $push->dispatchToRespondersForIncident($incident->fresh(), $teams, 'again');

        $teamList = $this->teamList($teams);
        $also = array_filter([
            $added ? 'added ' . $this->teamList($added) : null,
            $notes !== $old['assignment_notes'] ? 'updated the notes for the team' : null,
        ]);

        AuditLogService::log(
            'updated',
            "Notified {$teamList} again about the {$this->describeIncident($incident)} — no one had accepted yet"
                . ($also ? ' (' . implode(', ', $also) . ')' : '') . '.',
            $incident,
            $old,
            [
                'notified'         => $teams,
                'kind'             => 'again',
                'teams'            => $incident->assignedTeamCodes(),
                'assignment_notes' => $notes,
            ]
        );

        return back()->with('success', "Sent again to {$teamList}.");
    }

    /**
     * Incident Details → Send Backup (item 55), while a team is responding.
     * The ticked teams get "🆘 Backup needed" — it rings like a new incident
     * and says who's on scene and what's needed — and can join the team on
     * scene. The team on scene is told backup is on the way.
     *
     *   teams[]  BFP / PNP / SARS / HCU — the team on scene's own team too,
     *            for more of its people (their own phones don't ring)
     *   needs    what's needed (optional), sent along
     *
     * It answers the team's request from Resqponder when one is waiting
     * (the form starts with its teams and what they need), or MDRRMO sends
     * backup on its own. A ticked team that couldn't see the incident yet
     * is added, and anyone on it who declined gets it back. Each team can be
     * notified once a minute, so a double click doesn't send it twice.
     */
    public function sendBackup(Request $request, Incident $incident, PushNotificationService $push)
    {
        $onIt = $incident->load('responders')->teamInOrder();

        if ($incident->stage !== 'active' || $onIt->isEmpty()) {
            return back()->withErrors(['backup' => $incident->stage !== 'active'
                ? 'Backup can only be sent while the incident is active.'
                : "Backup is for a team that's responding, and no one has accepted this yet. Use Notify specific responders."]);
        }

        $request->validate([
            'teams'   => ['required', 'array', 'min:1'],
            'teams.*' => ['string', Rule::in(array_keys(Incident::TEAMS))],
            'needs'   => ['nullable', 'string', 'max:255'],
        ], [
            'teams.required' => 'Choose at least one team to send as backup.',
            'teams.min'      => 'Choose at least one team to send as backup.',
            'teams.*.in'     => 'Choose the team from the list.',
            'needs.max'      => "Keep what's needed under 255 characters.",
        ]);

        $teams = $this->orderedTeams($request->input('teams'));
        $needs = $this->cleanText($request->input('needs'));

        $justNotified = array_values(array_filter(
            $teams,
            fn ($team) => RateLimiter::tooManyAttempts($this->notifyKey($incident, $team), 1)
        ));
        if ($justNotified) {
            $many = count($justNotified) > 1;

            return back()->withInput()->withErrors(['backup' => $this->teamList($justNotified)
                . ($many ? ' were' : ' was') . ' just notified. Wait a minute before notifying '
                . ($many ? 'them' : 'it') . ' again.']);
        }
        foreach ($teams as $team) {
            RateLimiter::hit($this->notifyKey($incident, $team), 60);
        }

        // The teams can see it and accept it, and anyone on them who
        // declined gets it back. No list at all (approved before teams were
        // assigned): every team sees it already.
        $before = $incident->assignedTeamCodes();
        $added  = $incident->assigned_teams === null ? [] : array_values(array_diff($teams, $before));
        if ($added) {
            $incident->update(['assigned_teams' => $this->orderedTeams(array_merge($before, $added))]);
        }
        $declined = $incident->declinedBy()->whereIn('responders.agency', $teams)->pluck('responders.id');
        if ($declined->isNotEmpty()) {
            $incident->declinedBy()->detach($declined);
        }

        // The team's request it answers, if one is waiting. What's needed is
        // what MDRRMO sent (the form starts with the team's words).
        $waiting = $incident->backupRequests()->where('status', BackupRequest::WAITING)->with('responder')->get()->last();
        $answer  = [
            'status'      => BackupRequest::SENT,
            'sent_teams'  => $teams,
            'added_teams' => $added ?: null,
            'needs'       => $needs ?? $waiting?->needs,
            'handled_by'  => auth('web')->id(),
            'handled_at'  => now(),
        ];
        if ($waiting) {
            $waiting->update($answer);
            $backup = $waiting;
        } else {
            $backup = $incident->backupRequests()->create($answer + ['teams' => $teams]);
        }

        $teamList = $this->teamList($teams);
        $push->dispatchBackup($incident->fresh(), $teams, $backup->needs);
        $push->notifyResponders(
            $onIt,
            "Backup on the way — {$teamList}",
            "MDRRMO sent {$teamList} to back you up at " . $this->placeOf($incident) . '.',
            $incident,
            'backup'
        );

        // What's needed goes last: a long one is what gets cut when the
        // line is too long for the Audit Log (AuditLogService).
        AuditLogService::log(
            'backup',
            "Sent {$teamList} as backup to the {$this->describeIncident($incident)}."
                . ($waiting?->responder ? ' Asked for by ' . BackupRequest::label($waiting->responder) . '.' : '')
                . ($added ? ' ' . $this->teamList($added) . " added to the incident's teams." : '')
                . ($backup->needs ? ' Needed: ' . rtrim($backup->needs, '. ') . '.' : ''),
            $incident,
            null,
            ['backup_request' => $backup->id, 'teams' => $teams, 'needs' => $backup->needs, 'answered' => (bool) $waiting]
        );

        return back()->with('success', "Backup sent to {$teamList}. "
            . ($onIt->count() > 1 ? 'The team on scene was' : $onIt->first()->full_name . ' was')
            . " told it's on the way.");
    }

    /**
     * Incident Details → Can't Send (item 55): MDRRMO can't send the backup
     * the team asked for. The reply is required — it's what the team on
     * scene reads ("MDRRMO can't send backup right now").
     */
    public function declineBackup(Request $request, Incident $incident, BackupRequest $backup, PushNotificationService $push)
    {
        abort_unless((int) $backup->incident_id === (int) $incident->id, 404);

        if (! $backup->isWaiting()) {
            return back()->with('success', 'That backup request was already answered.');
        }

        $request->validate([
            'reply' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'reply.required' => "Tell the team why you can't send backup.",
            'reply.min'      => 'Say a bit more, so the team knows what to do.',
            'reply.max'      => 'Keep the answer under 255 characters.',
        ]);

        $reply = $this->cleanText($request->input('reply'));

        $backup->update([
            'status'     => BackupRequest::DECLINED,
            'reply'      => $reply,
            'handled_by' => auth('web')->id(),
            'handled_at' => now(),
        ]);

        $onIt = $incident->load('responders')->teamInOrder();
        $push->notifyResponders($onIt, "MDRRMO can't send backup right now", $reply, $incident, 'backup');

        $asker = $backup->responder ? BackupRequest::label($backup->responder) : 'the team';

        AuditLogService::log(
            'backup',
            "Answered the backup request from {$asker} for the {$this->describeIncident($incident)}: can't send — "
                . rtrim($reply, '. ') . '.',
            $incident,
            null,
            ['backup_request' => $backup->id, 'reply' => $reply]
        );

        return back()->with('success', 'Your answer was sent to ' . ($onIt->count() > 1 ? 'the team on scene' : $asker) . '.');
    }

    /**
     * Sends an approved incident to every response team (an incident
     * approved under item 36 that was never sent: Send to All Responders).
     */
    private function sendToAllResponders(Incident $incident, PushNotificationService $push): void
    {
        $this->sendToTeams($incident, array_keys(Incident::TEAMS), $push);
    }

    /**
     * Sends an approved incident to these response teams — only they are
     * notified and can see and accept it — and tells a logged-in resident
     * it was approved (a guest has no account to tell).
     */
    private function sendToTeams(Incident $incident, array $teams, PushNotificationService $push): void
    {
        $incident->update([
            'assigned_teams' => $this->orderedTeams($teams),
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

    /** "BFP and SARS" — or "all responders" when it's every team. */
    private function sentToLabel(array $teams): string
    {
        return $this->orderedTeams($teams) === array_keys(Incident::TEAMS)
            ? 'all responders'
            : $this->teamList($this->orderedTeams($teams));
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
     * For Review → "Approve as Resolved" (item 49). The admin read the
     * team's Initial report — and corrected it if something was wrong
     * (editReport()) — and accepts it: the incident is now resolved. A
     * Final report sent before item 49 is approved the same way.
     */
    public function confirmResolved(Incident $incident, IncidentReportService $reports, PushNotificationService $push)
    {
        if (! $incident->isForReview()) {
            return back()->withErrors(['review' => 'This incident is no longer waiting for review.']);
        }

        $this->approveReport($incident, $reports, $push);

        return back()->with('success', 'Report approved. The incident is now resolved.');
    }

    /**
     * Resolves an incident on For Review: the PDF is rebuilt with who
     * reviewed it ("Reviewed by"), and the resident who reported it (if
     * any) and the responding team are told.
     */
    private function approveReport(Incident $incident, IncidentReportService $reports, PushNotificationService $push): void
    {
        $admin  = auth('web')->user();
        $report = is_array($incident->report_data) ? $incident->report_data : null;
        $kind   = $this->reportKind($report);

        $changes = [
            'status'      => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $admin?->id,
        ];
        if ($report !== null) {
            $report['reviewed_by'] = $this->mdrrmoName($admin);
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
            'Report approved by MDRRMO',
            "{$incident->display_type} — {$incident->location}: MDRRMO approved your {$kind}"
                . (! empty($report['edited_by']) ? ' with a few corrections' : '')
                . '. The incident is now resolved.',
            $incident
        );

        AuditLogService::log(
            'resolved',
            "Approved the responders' {$kind} and marked the {$this->describeIncident($incident)} as resolved.",
            $incident,
            ['status' => 'for_review'],
            ['status' => 'resolved']
        );
    }

    /** "Initial report", or "Final report" for one sent before item 49. */
    private function reportKind(?array $report): string
    {
        return $report !== null && IncidentReportService::isFinal($report) ? 'Final report' : 'Initial report';
    }

    /** "Ana Cruz, MDRRMO Rosales" — how the report names the admin. */
    private function mdrrmoName($admin): string
    {
        return $admin?->name ? "{$admin->name}, MDRRMO Rosales" : 'MDRRMO Rosales';
    }

    /** Incident Details, or SOS Details for an SOS. */
    private function detailsUrl(Incident $incident): string
    {
        return $incident->isSos() ? route('sos.detail', $incident) : route('incident.detail', $incident);
    }

    /**
     * For Review → "Review & Edit" (item 49): the team's report as a form,
     * so MDRRMO can correct it before approving it — the location, the
     * narrative, the casualties, the effects, the actions taken and who
     * released it. The type, the date and time, the team and the photo
     * stay as the team sent them.
     */
    public function editReport(Incident $incident, IncidentReportService $reports)
    {
        if (! $incident->isForReview() || ! is_array($incident->report_data)) {
            return redirect($this->detailsUrl($incident))->withErrors(['review' => $incident->isForReview()
                ? 'There is no report to edit: the team finished this incident without one (an older version of the app).'
                : 'Only a report waiting for your review can be edited.']);
        }

        $incident->load(['responders', 'citizen']);

        return view('incident-report-edit', [
            'incident' => $incident,
            'report'   => $incident->report_data,
            'team'     => $reports->currentTeam($incident),
            'kind'     => $this->reportKind($incident->report_data),
            'details'  => $this->detailsUrl($incident),
        ]);
    }

    /**
     * Saves MDRRMO's corrections from editReport(). "Save Changes" keeps the
     * report on For Review; "Save & Approve as Resolved" (approve=1) also
     * approves it, like confirmResolved(). The PDF is rebuilt with the
     * corrections, and the team sees them in the app.
     */
    public function updateReport(Request $request, Incident $incident, IncidentReportService $reports, PushNotificationService $push)
    {
        if (! $incident->isForReview() || ! is_array($incident->report_data)) {
            return redirect($this->detailsUrl($incident))
                ->withErrors(['review' => 'This report is no longer waiting for your review.']);
        }

        $casualtyRow = ['nullable', 'string'];
        $validated = $request->validate([
            'location'                  => ['required', 'string', 'max:255'],
            'narrative'                 => ['required', 'string', 'max:2000'],
            'effects'                   => ['nullable', 'string', 'max:1000'],
            'actions_taken'             => ['nullable', 'string', 'max:1500'],
            'released_by'               => ['nullable', 'string', 'max:255'],
            'casualties'                => ['nullable', 'array'],
            'casualties.*'              => ['nullable', 'array', 'max:30'],
            'casualties.*.*.name'       => [...$casualtyRow, 'max:120'],
            'casualties.*.*.age'        => [...$casualtyRow, 'max:10'],
            'casualties.*.*.sex'        => [...$casualtyRow, 'max:10'],
            'casualties.*.*.address'    => [...$casualtyRow, 'max:160'],
            'casualties.*.*.cause'      => [...$casualtyRow, 'max:120'],
            'casualties.*.*.remarks'    => [...$casualtyRow, 'max:200'],
            'approve'                   => ['nullable', 'boolean'],
        ], [
            'location.required'   => 'Write where it happened (Location).',
            'narrative.required'  => 'Describe what happened (Details / Narrative).',
            'narrative.max'       => 'Keep the narrative under 2,000 characters.',
            'effects.max'         => 'Keep the effects under 1,000 characters.',
            'actions_taken.max'   => 'Keep the actions taken under 1,500 characters.',
            'casualties.*.max'    => 'A casualty list can have up to 30 people.',
        ]);

        $approve = $request->boolean('approve');
        $kind    = $this->reportKind($incident->report_data);
        [$report, $changed] = $reports->applyEdit($incident->report_data, $validated, $this->mdrrmoName(auth('web')->user()));

        if ($changed) {
            $changes = ['report_data' => $report];
            // The narrative doubles as the short notes on the admin pages
            // and in the team's Report History.
            if (in_array(IncidentReportService::EDITABLE_PARTS['narrative'], $changed, true)) {
                $changes['resolution_notes'] = $report['narrative'];
            }
            $incident->update($changes);

            AuditLogService::log(
                'updated',
                "Corrected the responders' {$kind} for the {$this->describeIncident($incident)} — " . implode(', ', $changed) . '.',
                $incident,
                null,
                ['corrected' => $changed]
            );

            // Approving rebuilds it anyway.
            if (! $approve) {
                $this->rebuildReportPdf($incident, $reports);
            }
        }

        if ($approve) {
            $this->approveReport($incident, $reports, $push);

            return redirect($this->detailsUrl($incident))->with('success', $changed
                ? 'Report corrected and approved. The incident is now resolved.'
                : 'Report approved. The incident is now resolved.');
        }

        return redirect($this->detailsUrl($incident))->with('success', $changed
            ? 'Changes saved to the report and its PDF. Approve it when it\'s ready.'
            : 'Nothing was changed.');
    }

    /**
     * For Review → "Return to Responders". Something in the report needs
     * fixing that MDRRMO can't correct itself (e.g. the names of the
     * injured). The incident goes back to Active (status responding), so
     * the team can update the report in the app and send it again — it
     * then comes back here. The team gets a push with the reason; the app
     * shows it too (Api\IncidentController::reportForResponder()).
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

        $kind   = $this->reportKind(is_array($incident->report_data) ? $incident->report_data : null);
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
            "{$incident->display_type} — {$incident->location}: " . rtrim($reason, '. ') . '. Update the report and send it to MDRRMO again.',
            $incident
        );

        AuditLogService::log(
            'rejected',
            "Sent the {$kind} for the {$this->describeIncident($incident)} back to the responders — {$reason}",
            $incident,
            ['status' => 'for_review'],
            ['status' => 'responding', 'return_reason' => $reason]
        );

        return back()->with('success', 'Report sent back to the responders. It comes back to For Review when they send it again.');
    }

    /** Photos on one Add Resolved Report (item 71). */
    public const MANUAL_PHOTOS_MAX = 10;

    /**
     * Resolved → "Add Resolved Report": the form for an incident MDRRMO
     * handled outside the app (a walk-in, a phone call, a radio report…).
     * Opened from Incidents → Resolved, or from All Incidents (?from=overview,
     * item 71), which its Back and Cancel links go back to. It can be in
     * Rosales or in another town (item 72).
     */
    public function createManual(Request $request)
    {
        return view('incident-manual-report', [
            'barangays' => array_keys(BarangayLocationService::allCoordinates()),
            'otherTowns' => PlaceNames::otherTowns(),
            'provinces' => PlaceNames::PROVINCES,
            'photosMax' => self::MANUAL_PHOTOS_MAX,
            'photoLimits' => self::photoUploadLimits(),
            'backUrl'   => $request->query('from') === 'overview'
                ? route('incidents.overview', ['status' => 'resolved'])
                : route('incident', ['tab' => 'resolved']),
            'backLabel' => $request->query('from') === 'overview' ? 'Back to All Incidents' : 'Back to Incidents',
        ]);
    }

    /**
     * What this server's PHP takes in one save, in bytes (item 71), so the
     * form keeps the photos within it: one photo up to upload_max_filesize
     * (and the 8 MB rule), all of them together up to post_max_size less
     * room for the rest of the form. Sending more than post_max_size would
     * lose the whole form. 0 = no limit.
     */
    public static function photoUploadLimits(): array
    {
        $bytes = function (string $key): int {
            $value = trim((string) ini_get($key));
            $number = (float) $value;

            return (int) match (strtolower(substr($value, -1))) {
                'g' => $number * 1024 ** 3,
                'm' => $number * 1024 ** 2,
                'k' => $number * 1024,
                default => $number,
            };
        };
        $rule = 8192 * 1024; // photos.* max:8192
        $file = $bytes('upload_max_filesize');
        $post = $bytes('post_max_size');

        return [
            'file'  => $file > 0 ? min($file, $rule) : $rule,
            'total' => $post > 0 ? max(1024 * 1024, $post - 256 * 1024) : 0,
        ];
    }

    /**
     * Saves an incident that was handled outside the app, already
     * resolved, with its MDRRMC Incident Report (same PDF the responders
     * file). It shows on the Resolved tab marked "Added by MDRRMO".
     *
     * It's dated when it happened (created_at), so the Dashboard and
     * Reports & Analytics count it on the right day. No one is notified
     * and nothing is sent to the responders — it's a record.
     *
     * Item 71: up to MANUAL_PHOTOS_MAX photos (was one). They're kept like a
     * resident's report photos (photo_path, incident_photos on the public
     * disk), shown on its page as "Photos from the scene" and printed on the
     * PDF. The form makes big photos smaller before sending them.
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
        // In Rosales unless "Another town or city" is chosen (item 72).
        $request->mergeIfMissing(['place' => 'rosales']);

        $casualtyRow = ['nullable', 'string'];
        $validated = $request->validate([
            'emergency_type'  => ['required', Rule::in(Incident::TYPES)],
            'other_type'      => ['nullable', 'required_if:emergency_type,Other', 'string', 'max:60'],
            'priority'        => ['required', 'in:critical,high,low'],
            'place'           => ['required', 'in:rosales,other'],
            'barangay'        => ['exclude_unless:place,rosales', 'required', Rule::in(array_keys(BarangayLocationService::allCoordinates()))],
            'town'            => ['exclude_unless:place,other', 'required', 'string', 'max:40',
                function (string $attribute, $value, \Closure $fail) use ($request) {
                    if (is_string($value) && PlaceNames::isRosales($value, (string) $request->input('province'))) {
                        $fail('For Rosales, choose "Rosales" above and pick the barangay.');
                    }
                }],
            'province'        => ['exclude_unless:place,other', 'required', 'string', 'max:30'],
            'town_barangay'   => ['exclude_unless:place,other', 'nullable', 'string', 'max:40'],
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
            'photos'          => ['nullable', 'array', 'max:' . self::MANUAL_PHOTOS_MAX],
            'photos.*'        => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'emergency_type.required' => 'Choose the type of incident.',
            'other_type.required_if'  => 'Type what kind of incident it was.',
            'priority.required'       => 'Choose the priority (Critical, High or Low).',
            'place.in'                => 'Choose Rosales or another town or city.',
            'barangay.required'       => 'Choose the barangay where it happened.',
            'barangay.in'             => 'Choose a barangay of Rosales from the list.',
            'town.required'           => 'Type the town or city where it happened.',
            'town.max'                => 'The town or city can be up to 40 characters.',
            'province.required'       => 'Type the province.',
            'province.max'            => 'The province can be up to 30 characters.',
            'town_barangay.max'       => 'The barangay can be up to 40 characters.',
            'occurred_at.required'    => 'Enter when the incident happened.',
            'occurred_at.before_or_equal' => "The incident's date and time can't be in the future.",
            'resolved_at.required'    => 'Enter when the incident was resolved.',
            'resolved_at.after_or_equal' => 'It can only be resolved after it happened — check both dates and times.',
            'resolved_at.before_or_equal' => "The resolved date and time can't be in the future.",
            'reported_via.required'   => 'Choose how MDRRMO learned about the incident.',
            'reporter_mobile.regex'   => 'Enter a valid contact number, e.g. 09171234567.',
            'narrative.required'      => 'Describe what happened (Details / Narrative).',
            'actions_taken.required'  => 'Write the actions taken.',
            'photos.max'              => 'Add up to ' . self::MANUAL_PHOTOS_MAX . ' photos.',
            'photos.*.uploaded'       => "A photo couldn't be uploaded — it may be too big. Try a smaller one.",
            'photos.*.file'           => "A photo couldn't be uploaded — it may be too big. Try a smaller one.",
            'photos.*.image'          => 'Each photo must be an image (JPG, PNG or WebP).',
            'photos.*.mimes'          => 'Each photo must be an image (JPG, PNG or WebP).',
            'photos.*.max'            => 'Each photo must be 8 MB or smaller.',
        ]);

        $admin    = auth('web')->user();
        $type     = $validated['emergency_type'] === 'Other'
            ? trim($validated['other_type'])
            : $validated['emergency_type'];
        $street   = trim((string) ($validated['street'] ?? ''));
        [$location, $address, $place] = $this->manualPlace($validated, $street);
        $occurred = Carbon::parse($validated['occurred_at']);
        $resolved = Carbon::parse($validated['resolved_at']);

        // Kept like a resident's report photos (item 71).
        $photoPaths = [];
        foreach ((array) $request->file('photos', []) as $file) {
            $photoPaths[] = $file->store('incident_photos', 'public');
        }

        $incident = new Incident([
            'citizen_id'            => null,
            'emergency_type'        => $type,
            'location'              => $location,
            'latitude'              => null,
            'longitude'             => null,
            'description'           => $validated['narrative'],
            'photo_path'            => $photoPaths ? json_encode($photoPaths) : null,
            'status'                => 'resolved',
            'priority'              => $validated['priority'],
            'needs_review'          => false,
            'reviewed_at'           => now(),
            'resolution_notes'      => $validated['narrative'],
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
            'location'      => $address, // the PDF: the address, without "Outside Rosales:"
            'narrative'     => $validated['narrative'],
            'casualties'    => $validated['casualties'] ?? [],
            'effects'       => $validated['effects'] ?? '',
            'actions_taken' => $validated['actions_taken'],
            'released_by'   => $releasedBy,
        ]);
        $report['team']         = $reports->teamFromInput($validated['team'] ?? []);
        $report['source']       = 'manual';
        $report['place']        = $place;
        $report['reported_via'] = $validated['reported_via'];
        $report['recorded_by']  = $admin?->name ? "{$admin->name}, MDRRMO Rosales" : 'MDRRMO Rosales';
        $report['recorded_at']  = now()->format('F j, Y g:i A');
        $report['resolved_at']  = $resolved->format('F j, Y g:i A');

        $incident->update(['report_data' => $report]);
        $this->rebuildReportPdf($incident, $reports);

        $photoCount = count($photoPaths);
        AuditLogService::log(
            'created',
            "Added a resolved {$this->describeIncident($incident)} — reported to MDRRMO via " . strtolower($validated['reported_via'])
                . ($photoCount ? ', with ' . $photoCount . ' ' . ($photoCount === 1 ? 'photo' : 'photos') : '') . '.',
            $incident
        );

        return redirect()
            ->route('incident.detail', $incident)
            ->with('success', 'Resolved report added. It is listed on the Resolved tab.');
    }

    /**
     * Add Resolved Report: where it happened, as
     * [the incident's location, the address for the PDF, the parts].
     *   - Rosales: "Rizal St, Brgy. Acop, Rosales, Pangasinan" for both.
     *   - Another town (item 72): "Outside Rosales: Rizal St, Brgy.
     *     Poblacion, Villasis, Pangasinan" — like a GPS pin outside Rosales,
     *     so it's counted under "Outside Rosales", not under a Rosales
     *     barangay of the same name. The PDF prints the address without
     *     "Outside Rosales:".
     */
    private function manualPlace(array $validated, string $street): array
    {
        if (($validated['place'] ?? 'rosales') !== 'other') {
            $location = ($street !== '' ? "{$street}, " : '') . "Brgy. {$validated['barangay']}, Rosales, Pangasinan";

            return [$location, $location, [
                'in_rosales' => true,
                'barangay'   => $validated['barangay'],
                'town'       => 'Rosales',
                'province'   => 'Pangasinan',
                'street'     => $street !== '' ? $street : null,
            ]];
        }

        $province = PlaceNames::province($validated['province']);
        $town     = PlaceNames::town($validated['town'], $province);
        $barangay = PlaceNames::barangay((string) ($validated['town_barangay'] ?? ''));
        $tail     = implode(', ', array_filter([$barangay !== '' ? "Brgy. {$barangay}" : '', $town, $province]));
        $address  = ($street !== '' ? "{$street}, " : '') . $tail;

        // incidents.location holds 255 characters: a very long street is
        // shortened there (the PDF keeps all of it).
        $prefix = 'Outside Rosales: ';
        $room   = 255 - mb_strlen($prefix) - mb_strlen($tail) - 2;
        $short  = $street !== '' ? Str::limit($street, max(10, $room - 1), '…') : '';
        $location = $prefix . ($short !== '' ? "{$short}, " : '') . $tail;

        return [$location, $address, [
            'in_rosales' => false,
            'barangay'   => $barangay !== '' ? $barangay : null,
            'town'       => $town,
            'province'   => $province,
            'street'     => $street !== '' ? $street : null,
        ]];
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

        return $reports->pdfResponse($incident, $request->boolean('download'));
    }

    public function show(Incident $incident)
    {
        $incident->load(['citizen', 'responders', 'resolvedBy', 'reviewedBy', 'backupRequests.responder', 'backupRequests.handledBy']);
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
        $incident->load(['citizen', 'responders', 'resolvedBy', 'reviewedBy', 'backupRequests.responder', 'backupRequests.handledBy']);
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
     *
     * The bell only lists them now. The sound for a new report comes from
     * the notice on every admin page (latestReports(), item 49), so it
     * rings on every page — not only on the Dashboard — and only once.
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
     * Powers the notice in the corner of every admin page
     * (partials/report-alert, items 46 and 49), newest first:
     *
     *   new     a resident's new report (Report Emergency) waiting for
     *           approval on Pending, from the last day. It used to ring
     *           only on the Dashboard (the bell); now every page shows it
     *           and rings, like a report from the responders. SOS alerts
     *           aren't here: they have their own siren (sos-alert-overlay).
     *   review  a team's report waiting on For Review, from the last day
     *   open    an Initial report a team saved before item 49 (the
     *           incident stayed Active), from the last 3 hours
     *
     * The page remembers the ones the admin opened or closed, and rings for
     * one that just came in.
     *
     * Also the For Review counts, so the purple counts in the menu change
     * without reloading the page.
     */
    public function latestReports()
    {
        $now   = now();
        $since = ['open' => $now->copy()->subHours(3), 'review' => $now->copy()->subDay()];

        $reports = Incident::with('responders')
            // Sending a report updates the incident, so this only skips
            // incidents nothing happened to since.
            ->where('updated_at', '>=', $since['review'])
            ->where(function ($q) {
                $q->where('status', 'for_review')
                    ->orWhere(fn ($q) => $q->whereIn('status', ['pending', 'responding'])->whereNotNull('report_data'));
            })
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(function (Incident $incident) use ($since) {
                $report = $incident->teamReport();

                return $report && $report['at']->gte($since[$report['review'] ? 'review' : 'open'])
                    ? $this->reportNotice($incident, $report)
                    : null;
            })
            ->filter();

        $newReports = Incident::with('citizen')
            ->where('emergency_type', '!=', 'SOS Emergency')
            ->where('needs_review', true)
            ->whereNull('declined_at')
            ->where('created_at', '>=', $since['review'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (Incident $incident) => $this->newReportNotice($incident));

        // A team on scene asked for backup (item 55) and MDRRMO hasn't
        // answered yet.
        $backups = BackupRequest::with(['incident', 'responder'])
            ->where('status', BackupRequest::WAITING)
            ->where('created_at', '>=', $since['review'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->filter(fn (BackupRequest $backup) => $backup->incident && $backup->incident->stage === 'active')
            ->map(fn (BackupRequest $backup) => $this->backupNotice($backup));

        return response()->json([
            'reports'   => $reports->concat($newReports)->concat($backups)->sortByDesc('at')->take(10)->values(),
            'forReview' => Incident::forReviewCounts(),
            'now'       => $now->getTimestamp(),
        ]);
    }

    /**
     * A team's backup request waiting for MDRRMO, as partials/report-alert
     * shows it. Opening the incident counts as seeing it — the page marks
     * "backup-{id}" as seen.
     */
    private function backupNotice(BackupRequest $backup): array
    {
        $teams = $backup->teamCodes();

        return [
            'key'    => 'backup-' . $backup->id,
            'kind'   => 'backup',
            'title'  => 'Backup requested',
            'what'   => $backup->incident->display_type . ($teams ? ' · needs ' . $this->teamList($teams) : ''),
            'where'  => $this->placeOf($backup->incident),
            'from'   => $backup->responder ? BackupRequest::label($backup->responder) : 'The team on scene',
            'at'     => $backup->created_at->getTimestamp(),
            'action' => 'Respond',
            'url'    => $this->detailsUrl($backup->incident) . '#backup',
        ];
    }

    /** "SOS Alert — Brgy. Acop, Rosales" → "Brgy. Acop, Rosales" */
    private function placeOf(Incident $incident): string
    {
        $place = trim((string) preg_replace('/^(SOS Alert|Guest Report)\s*[—,]\s*/u', '', (string) $incident->location));

        return $place !== '' ? $place : 'No location';
    }

    /** A team's report, as partials/report-alert shows it. */
    private function reportNotice(Incident $incident, array $report): array
    {
        $review = $report['review'];
        $kind   = $report['type'] === 'final' ? 'Final report' : 'Initial report';
        $lead   = $incident->responders->sortBy(fn ($r) => (string) $r->pivot->accepted_at)->first();

        return [
            'key'    => $report['key'],
            'kind'   => $review ? 'review' : 'open',
            'title'  => match (true) {
                $review && $report['again'] => "{$kind} sent again",
                $review                     => "{$kind} received",
                $report['revision'] > 1     => 'Initial report updated',
                default                     => 'Initial report saved',
            },
            'what'   => $incident->display_type,
            'where'  => $this->placeOf($incident),
            'from'   => $lead ? $lead->full_name . ($lead->agency ? " ({$lead->agency})" : '') : 'The responders',
            'at'     => $report['at']->getTimestamp(),
            'action' => $review ? 'Review' : 'View',
            'url'    => $this->detailsUrl($incident),
        ];
    }

    /**
     * A resident's new report waiting for approval, as partials/report-alert
     * shows it. Opening it (Incident Details) counts as seeing it — the page
     * marks "new-{id}" as seen.
     */
    private function newReportNotice(Incident $incident): array
    {
        return [
            'key'    => 'new-' . $incident->id,
            'kind'   => 'new',
            'title'  => 'New report',
            'what'   => $incident->display_type,
            'where'  => $this->placeOf($incident),
            'from'   => $incident->reporter_name . ($incident->citizen_id ? '' : ' (guest)'),
            'at'     => $incident->created_at->getTimestamp(),
            'action' => 'Review',
            'url'    => $this->detailsUrl($incident),
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
        //   - older/closed ones only get fixed when their label is still
        //     raw GPS coordinates ("SOS Alert — Lat 15.89…, Lng 120.63…")
        //     or the generic "SOS Alert, Rosales" — they now show the
        //     street and barangay instead;
        //   - still-pending ones whose label was made from the pin (SOS,
        //     guest reports, and residents' reports since item 49 — "…,
        //     Brgy. Acop, Rosales, Pangasinan") get the right barangay when
        //     the label names another one than the official borders say
        //     (item 59 — the old barangay centres were off), or says
        //     outside Rosales when it isn't (or the other way round). Only
        //     the barangay is swapped — the street and landmark stay;
        //   - still-pending ones are checked again for being outside Rosales.
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
            $isPending = $incident->status === 'pending';

            $newLocation = $this->recheckedLocation($incident, $locationService);
            if ($newLocation !== null && $newLocation !== $incident->location) {
                $updates['location'] = $newLocation;
                $relabeled++;
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
            "Rechecked {$candidates->count()} incidents: {$relabeled} location(s) updated to the street and the right barangay, {$flaggedForReview} newly flagged as outside Rosales and now pending review."
        );
    }

    /** A location made from the pin: "…, Brgy. Acop, Rosales, Pangasinan". */
    private const PIN_LABEL = '/\bBrgy\. ([^,]+), Rosales, Pangasinan$/u';

    /**
     * Recheck Locations: the incident's new location text, or null to leave
     * it as it is (see recheckLocations()).
     *
     * No OpenStreetMap here: its free service allows about one lookup per
     * second, too slow for a whole batch. Google (cached) and the official
     * barangay borders are used instead — and no new landmark searches
     * (item 59), which Google bills one by one: a landmark found when the
     * report came in (kept 7 days) stays in the label.
     */
    private function recheckedLocation(Incident $incident, BarangayLocationService $places): ?string
    {
        $location = (string) $incident->location;
        $lat = (float) $incident->latitude;
        $lng = (float) $incident->longitude;
        $isSos = $incident->emergency_type === 'SOS Emergency';

        $rebuild = fn () => match (true) {
            $isSos => $places->approximateSosLabel($lat, $lng, false, false),
            str_starts_with($location, 'Guest Report') || $location === '' => $places->approximateReportLabel($lat, $lng, false, false),
            default => $places->resolve($lat, $lng, false, false),
        };

        // Raw coordinates or a generic label: always worth a real one.
        if (preg_match('/\bLat\s*-?\d+(\.\d+)?\s*,\s*Lng\s*-?\d+/i', $location)
            || in_array($location, ['', 'Guest Report, Rosales', 'SOS Alert, Rosales'], true)) {
            return $rebuild();
        }

        if ($incident->status !== 'pending') {
            return null;
        }

        $madeFromPin = $isSos
            || str_starts_with($location, 'Guest Report — ')
            || str_starts_with($location, 'SOS Alert — ')
            || str_starts_with($location, 'Outside Rosales')
            || preg_match(self::PIN_LABEL, $location);
        if (! $madeFromPin) {
            return null; // typed by the resident (an older app)
        }

        // Without the boundary file: refreshed, as before item 59.
        if (! BarangayLocationService::boundaries()) {
            return ($isSos || str_starts_with($location, 'Guest Report')) ? $rebuild() : null;
        }

        // Is the label right? Its barangay (null = outside Rosales)
        // against the official border the pin is inside.
        $atPin = $places->barangayAt($lat, $lng);
        $named = match (true) {
            str_contains($location, 'Outside Rosales')          => null,
            (bool) preg_match(self::PIN_LABEL, $location, $m)   => $places->barangayFromText($m[1]) ?? $m[1],
            default                                             => $places->barangayFromText($location),
        };
        if ($named === $atPin) {
            return null;
        }

        // Swap only the barangay, keeping the street and landmark…
        if ($atPin && preg_match(self::PIN_LABEL, $location)) {
            return preg_replace_callback(self::PIN_LABEL, fn () => "Brgy. {$atPin}, Rosales, Pangasinan", $location);
        }

        // …or, inside ↔ outside Rosales, a new label.
        return $rebuild();
    }
}