<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Services\AuditLogService;
use App\Services\BarangayLocationService;
use App\Services\PushNotificationService;
use App\Services\IncidentReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class IncidentController extends Controller
{
    /**
     * Regular citizen-submitted reports only. SOS panic-button alerts
     * are excluded here and live on their own page — see sosIndex().
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
        if (! $incident->citizen_id) {
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

        if (in_array($status, ['pending', 'acknowledged', 'responding', 'resolved'], true)) {
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
     * Incidents' Resolved History (where declined reports are listed).
     */
    public function destroy(Incident $incident)
    {
        $isSos = $incident->emergency_type === 'SOS Emergency';
        $list = $isSos ? route('sos-alerts', ['tab' => 'declined']) : route('incident', ['tab' => 'history']);

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