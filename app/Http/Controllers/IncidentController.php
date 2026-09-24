<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Services\AuditLogService;
use App\Services\BarangayLocationService;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;

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
            "Set priority of Incident #{$incident->id} to {$request->priority}.",
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
            "Set status of Incident #{$incident->id} to {$request->status}.",
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

        AuditLogService::log('updated', "Updated admin notes on Incident #{$incident->id}.", $incident);

        return back()->with('success', 'Note saved.');
    }

    /**
     * Approves a guest-submitted report/SOS that's been sitting in
     * needs_review, then dispatches it to responders — the exact same
     * push that would have fired immediately for a logged-in citizen's
     * report (see Api\IncidentController::store()/sos()).
     */
    public function approve(Incident $incident, PushNotificationService $push)
    {
        if (! $incident->needs_review) {
            return back()->with('success', 'This report was already approved.');
        }

        $incident->update([
            'needs_review' => false,
            'reviewed_at'  => now(),
        ]);

        $push->dispatchToRespondersForIncident($incident);

        $label = $incident->emergency_type === 'SOS Emergency' ? 'guest SOS alert' : 'guest report';
        AuditLogService::log(
            'approved',
            "Approved {$label} #{$incident->id} — dispatched to responders.",
            $incident
        );

        return back()->with('success', 'Report approved and sent to responders.');
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
        $latest = Incident::where('emergency_type', 'SOS Emergency')
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->first(['id', 'created_at', 'location']);

        // Lets the overlay say "3 SOS alerts waiting" instead of only
        // ever showing the single newest one and silently burying the
        // rest — matters most exactly when it matters most, i.e. several
        // SOS reports landing close together.
        $pendingCount = Incident::where('emergency_type', 'SOS Emergency')
            ->where('status', 'pending')
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
        $candidates = Incident::where('status', 'pending')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        $relabeled = 0;
        $flaggedForReview = 0;

        foreach ($candidates as $incident) {
            $updates = [];

            // Only re-resolve location text for reports that went
            // through the auto-resolution path in the first place (SOS
            // always does; a guest regular report only when they didn't
            // type one) — never touch a citizen's own typed address.
            $wasAutoResolved = $incident->emergency_type === 'SOS Emergency'
                || str_starts_with((string) $incident->location, 'Guest Report — ')
                || str_starts_with((string) $incident->location, 'SOS Alert — ');

            if ($wasAutoResolved) {
                $newLocation = $incident->emergency_type === 'SOS Emergency'
                    ? $locationService->approximateSosLabel($incident->latitude, $incident->longitude)
                    : $locationService->approximateReportLabel($incident->latitude, $incident->longitude);

                if ($newLocation !== $incident->location) {
                    $updates['location'] = $newLocation;
                    $relabeled++;
                }
            }

            if (! $incident->needs_review) {
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
            "Rechecked locations for {$candidates->count()} pending incidents — relabeled {$relabeled}, flagged {$flaggedForReview} for review.",
        );

        return back()->with(
            'success',
            "Rechecked {$candidates->count()} pending incidents: {$relabeled} location(s) corrected, {$flaggedForReview} newly flagged as outside Rosales and now pending review."
        );
    }
}