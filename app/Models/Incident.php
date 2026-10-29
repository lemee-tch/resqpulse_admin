<?php

namespace App\Models;

use App\Services\PushNotificationService;
use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    /** Report types, as on the residents' Report Emergency screen. */
    public const TYPES = ['Fire', 'Flood', 'Earthquake', 'Accident', 'Medical Emergency', 'Landslide', 'Other'];

    /** How MDRRMO learned about an incident it adds by hand (Add Resolved Report). */
    public const REPORTED_VIA = ['Walk-in', 'Phone call', 'Radio', 'Barangay officials', 'Responders on patrol', 'Other'];

    /**
     * The response teams the admin can assign (panel comment #6). Each is
     * one shared responder account (see ResponderSeeder). MSWD isn't here:
     * it runs the evacuation centers and never responds to incidents.
     */
    public const TEAMS = [
        'BFP'  => 'Bureau of Fire Protection',
        'PNP'  => 'Philippine National Police',
        'SARS' => 'Search and Rescue',
        'HCU'  => 'Health Care Unit',
    ];

    /** Why MDRRMO sends the team's report back to the responders (For Review → Return). */
    public const RETURN_REASONS = [
        'Casualty details are missing or incomplete',
        'The narrative needs more detail',
        'Actions taken are missing or incomplete',
        'Wrong incident type, location, date or time',
        'The photo is missing or unclear',
        'Other',
    ];

    protected $fillable = [
        'citizen_id', 'responder_id', 'accepted_at', 'emergency_type', 'location', 'latitude', 'longitude',
        'description', 'photo_path', 'status', 'priority', 'admin_notes',
        'resolution_notes', 'resolution_photo_path', 'report_data', 'report_pdf_path',
        'ai_detected_type', 'ai_confidence', 'ai_analysis', 'needs_review', 'reviewed_at',
        'sos_emergency_type', 'decline_reason', 'declined_at',
        // What a guest (no account) typed before sending — see
        // Api\IncidentController and the add_guest_details migration.
        // On an incident MDRRMO added by hand, the person who reported it.
        'guest_name', 'guest_mobile',
        // For Review and Add Resolved Report — see the
        // add_review_and_manual_reports migration.
        'report_submitted_at', 'report_returned_at', 'report_return_reason',
        'resolved_at', 'resolved_by', 'source', 'reported_via',
        // Assign Response Team — see the add_team_assignment migration.
        'assigned_teams', 'assignment_notes', 'responders_on_record', 'assigned_at', 'reviewed_by',
    ];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    protected $casts = [
        'needs_review'        => 'boolean',
        'reviewed_at'         => 'datetime',
        'declined_at'         => 'datetime',
        'report_data'         => 'array',
        'report_submitted_at' => 'datetime',
        'report_returned_at'  => 'datetime',
        'resolved_at'         => 'datetime',
        'assigned_teams'      => 'array',
        'assigned_at'         => 'datetime',
    ];

    // Includes display_type (see getDisplayTypeAttribute() below) in
    // every JSON response automatically — both apps read incidents as
    // JSON over the API, so without this they'd each have to
    // reimplement the same "SOS Alert — {type}" formatting themselves.
    protected $appends = ['display_type'];

    // Never sent to the apps: the private PDF storage path, and the admins'
    // Internal Note ("admins only" on Incident Details). The responders app
    // got the note with every incident before (item 47).
    protected $hidden = ['report_pdf_path', 'admin_notes'];

    /**
     * Where an incident is in MDRRMO's process — one per tab on the
     * Incidents page:
     *
     *   pending     waiting for an admin to approve or decline it
     *   active      approved and sent to the chosen response team(s):
     *               waiting for one to accept, or being responded to
     *   for_review  the responders sent their Initial report; the admin
     *               checks it — and can correct it — then approves it as
     *               resolved (item 49)
     *   resolved    resolved — or declined (shown on the same tab)
     */
    public function getStageAttribute(): string
    {
        return match (true) {
            (bool) $this->needs_review       => 'pending',
            (bool) $this->declined_at        => 'resolved',
            $this->status === 'for_review'   => 'for_review',
            $this->status === 'resolved'     => 'resolved',
            default                          => 'active',
        };
    }

    /** The status as the admin pages show it. */
    public function getStatusLabelAttribute(): string
    {
        return match (true) {
            (bool) $this->needs_review       => 'Pending Review',
            (bool) $this->declined_at        => 'Declined',
            $this->status === 'for_review'   => 'For Review',
            $this->status === 'resolved'     => 'Resolved',
            $this->status === 'responding'   => 'Responding',
            $this->awaitingTeam()            => 'Needs Team',
            default                          => 'Awaiting Responder',
        };
    }

    /**
     * Where it stands, as one key — the Incidents tabs, with Active split
     * by whether anyone has accepted. Used where the status is shown
     * without the tabs (the Dashboard, Map View, the incidents overview,
     * the exported report):
     *
     *   review      waiting for approval ("Pending")
     *   declined    declined by MDRRMO
     *   needs_team  approved but not sent to anyone (item 36)
     *   awaiting    approved and sent, no one has accepted yet
     *               ("Active · Awaiting Responder")
     *   responding  a responder accepted
     *   for_review  the team's report is with MDRRMO
     *   resolved
     */
    public function progressKey(): string
    {
        return match (true) {
            (bool) $this->needs_review     => 'review',
            (bool) $this->declined_at      => 'declined',
            $this->status === 'for_review' => 'for_review',
            $this->status === 'resolved'   => 'resolved',
            $this->status === 'responding' => 'responding',
            $this->awaitingTeam()          => 'needs_team',
            default                        => 'awaiting',
        };
    }

    /** progressKey() in words. */
    public const PROGRESS_LABELS = [
        'review'     => 'Pending',
        'declined'   => 'Declined',
        'needs_team' => 'Active · Needs Team',
        'awaiting'   => 'Active · Awaiting Responder',
        'responding' => 'Responding',
        'for_review' => 'For Review',
        'resolved'   => 'Resolved',
    ];

    /** "Pending", "Active · Awaiting Responder", "Responding"… */
    public function progressLabel(): string
    {
        return self::PROGRESS_LABELS[$this->progressKey()];
    }

    /**
     * Approved, but not sent to anyone: an empty team list ([]). Approving
     * now sends it to the team(s) the admin chooses right away, so this is
     * only an incident approved while approving didn't send it anywhere
     * (item 36).
     * The admin sends it from Incident Details
     * (Admin\IncidentController::updateAssignment()).
     *
     * Told apart from an incident approved before teams were assigned at
     * all (no list, NULL): those stay open to every team.
     */
    public function awaitingTeam(): bool
    {
        return ! $this->needs_review
            && ! $this->declined_at
            && $this->assigned_teams === []
            && ! in_array($this->status, ['for_review', 'resolved'], true);
    }

    /** An incident handled outside the app that MDRRMO added by hand. */
    public function isManualRecord(): bool
    {
        return $this->source === 'manual';
    }

    public function isSos(): bool
    {
        return $this->emergency_type === 'SOS Emergency';
    }

    /**
     * The teams usually needed for this kind of incident (Fire → BFP +
     * SARS…): ticked for the admin when approving (item 53), and named as
     * a hint when notifying specific teams. The AI's reading of an SOS
     * photo wins over the type the person picked.
     */
    public function defaultTeams(): array
    {
        $type = $this->ai_detected_type
            ?: ($this->isSos() ? $this->sos_emergency_type : $this->emergency_type);

        $teams = array_values(array_intersect(PushNotificationService::agenciesFor($type), array_keys(self::TEAMS)));

        return $teams ?: ['SARS'];
    }

    /** The assigned teams, in the order of self::TEAMS. Empty = none set. */
    public function assignedTeamCodes(): array
    {
        $teams = is_array($this->assigned_teams) ? $this->assigned_teams : [];

        return array_values(array_intersect(array_keys(self::TEAMS), $teams));
    }

    /** Sent to every response team (the admin ticked all four). */
    public function sentToAllTeams(): bool
    {
        return $this->assignedTeamCodes() === array_keys(self::TEAMS);
    }

    /**
     * Whether this agency's responders may see and accept it. Nobody may
     * while the team isn't assigned yet. Incidents approved before teams
     * were assigned (no team list saved) stay open to every team.
     */
    public function isAssignedTo(?string $agency): bool
    {
        if ($this->awaitingTeam()) {
            return false;
        }

        $teams = $this->assignedTeamCodes();

        return $teams === [] || in_array($agency, $teams, true);
    }

    /** Where the report came from, for the Source badge. */
    public function getSourceLabelAttribute(): string
    {
        return match (true) {
            $this->isManualRecord() => 'MDRRMO' . ($this->reported_via ? ' (' . strtolower($this->reported_via) . ')' : ''),
            $this->isSos()          => $this->citizen_id ? 'SOS — Resident' : 'SOS — Guest',
            (bool) $this->citizen_id => 'Resident',
            default                 => 'Guest',
        };
    }

    public function isForReview(): bool
    {
        return $this->status === 'for_review';
    }

    /**
     * The report the responding team sent last, while it's with MDRRMO
     * (items 46 and 49). The Incidents and SOS lists tag it, Incident
     * Details says so at the top, and every admin page shows a notice when
     * one comes in (partials/report-alert). Null when there's nothing from
     * the team: no report yet, MDRRMO approved it (resolved), MDRRMO sent it
     * back and the team hasn't sent it again, or MDRRMO added the incident
     * itself (Add Resolved Report).
     *
     *   type      initial  the Initial report — the only report the team
     *                      sends since item 49
     *             final    a Final report sent before item 49
     *   review    true     it's on For Review, waiting for MDRRMO to approve
     *                      it (status for_review)
     *             false    an Initial report saved before item 49, while the
     *                      incident stayed Active until a Final report came.
     *                      It goes to For Review when the team sends it from
     *                      the app.
     *   at        when the team sent it
     *   revision  how many times the team has sent the report (1 = first)
     *   again     sent again after MDRRMO returned it
     *   key       one value per sending, so a notice the admin has seen
     *             isn't shown again
     *
     * @return array{type: string, review: bool, at: \Illuminate\Support\Carbon, revision: int, again: bool, key: string}|null
     */
    public function teamReport(): ?array
    {
        if ($this->isManualRecord() || $this->needs_review || $this->declined_at) {
            return null;
        }

        $report = is_array($this->report_data) ? $this->report_data : null;

        if ($this->status === 'for_review') {
            $review = true;
            // Older app versions finish an incident without a report.
            $type = ($report['report_type'] ?? 'final') === 'initial' ? 'initial' : 'final';
            $at = $this->report_submitted_at ?? $this->updated_at;
        } elseif (in_array($this->status, ['pending', 'responding'], true)
            && $report && ($report['report_type'] ?? 'final') === 'initial') {
            $review = false;
            $type = 'initial';
            $at = $this->reportSentAt($report) ?? $this->updated_at;

            // Sent back by MDRRMO: not new until the team sends it again
            // (it then goes to For Review).
            if ($this->report_returned_at && $at->lte($this->report_returned_at)) {
                return null;
            }
        } else {
            return null;
        }

        return [
            'type'     => $type,
            'review'   => $review,
            'at'       => $at,
            'revision' => max(1, (int) ($report['revision'] ?? 1)),
            'again'    => $review && $this->report_returned_at !== null,
            'key'      => $this->id . '-' . $at->getTimestamp(),
        ];
    }

    /**
     * How many reports from the teams are waiting on For Review — the
     * purple counts next to Incidents and SOS Alerts in the menu.
     *
     * @return array{incidents: int, sos: int}
     */
    public static function forReviewCounts(): array
    {
        $waiting = static::where('status', 'for_review')->whereNull('declined_at');

        return [
            'incidents' => (clone $waiting)->where('emergency_type', '!=', 'SOS Emergency')->count(),
            'sos'       => (clone $waiting)->where('emergency_type', 'SOS Emergency')->count(),
        ];
    }

    /** When the team last sent the report (report_data's updated_at). */
    private function reportSentAt(array $report): ?\Illuminate\Support\Carbon
    {
        try {
            return empty($report['updated_at'])
                ? null
                : \Illuminate\Support\Carbon::parse($report['updated_at'])->setTimezone(config('app.timezone'));
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function citizen()
    {
        return $this->belongsTo(Citizen::class);
    }

    /** The admin who marked it resolved, or who added it by hand. */
    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** The admin who approved (verified) or declined it. */
    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * DEPRECATED — kept only so nothing that still reads the old
     * singular `responder_id` column breaks. Multi-responder / backup
     * support means an incident can now have several responders at
     * once, so `responders()` below is the real source of truth. Do
     * not write to `responder_id` for new code paths.
     */
    public function responder()
    {
        return $this->belongsTo(Responder::class);
    }

    /**
     * Every responder currently on this incident — the first to accept
     * AND any backup responders who joined afterward. `pivot.accepted_at`
     * records when each individual responder joined (distinct from the
     * incident's own `created_at`/`accepted_at`).
     */
    public function responders()
    {
        return $this->belongsToMany(Responder::class, 'incident_responder')
            ->withPivot('accepted_at')
            ->withTimestamps();
    }

    /**
     * Responders who have personally declined this incident — see the
     * incident_declines migration. A decline only ever means "not me":
     * it never changes the incident for anyone else, so this is a
     * separate table rather than another flag on `responders()` above,
     * which would corrupt every place that reads that relation as
     * "who's actually responding" (the "Responding Team" list, the
     * "X Responding" badge, etc. on both apps).
     *
     * IncidentController::assignedToResponder() uses this to drop an
     * incident from the declining responder's own Active Incidents feed
     * — see decline()/accept() there for how rows here are written and
     * cleared.
     */
    public function declinedBy()
    {
        return $this->belongsToMany(Responder::class, 'incident_declines')
            ->withPivot('declined_at')
            ->withTimestamps();
    }

    /**
     * "SOS Alert — Accident", "SOS Alert — Fire", etc. — what admins and
     * responders actually want to see instead of a bare "SOS Emergency"
     * label, once the citizen has picked a hazard type on the SOS screen
     * (sos_emergency_type). Falls back to the plain emergency_type for
     * everything else (regular reports already carry their real type
     * there) and to the bare label for an SOS with no type picked yet
     * (older app builds, or the citizen skipped it).
     */
    public function getDisplayTypeAttribute(): string
    {
        if ($this->emergency_type === 'SOS Emergency') {
            return $this->sos_emergency_type
                ? "SOS Alert — {$this->sos_emergency_type}"
                : 'SOS Emergency';
        }

        return (string) $this->emergency_type;
    }

    /**
     * Who sent it, for the admin pages: the resident's name, or the full
     * name a guest had to enter before sending (guest_name). Guest reports
     * from before that was required just say "Guest". On an incident
     * MDRRMO added by hand, the person who reported it, if one was
     * written down.
     */
    public function getReporterNameAttribute(): string
    {
        if ($this->citizen) {
            return (string) $this->citizen->full_name;
        }
        if ($this->citizen_id) {
            return 'Unknown';
        }
        if (filled($this->guest_name)) {
            return (string) $this->guest_name;
        }

        return $this->isManualRecord() ? 'Not recorded' : 'Guest';
    }

    /** A number MDRRMO can call back: the resident's, or the guest's. */
    public function getReporterMobileAttribute(): ?string
    {
        $mobile = $this->citizen?->mobile ?: $this->guest_mobile;

        return filled($mobile) ? (string) $mobile : null;
    }
}