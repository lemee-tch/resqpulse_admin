<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetOtp;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            
            AuditLogService::log('login', auth()->user()->name . ' logged in.');

            return redirect()->route('dashboard');

        }

        return back()->withErrors([
            'email' => 'Invalid email or password.',
        ])->onlyInput('email');
    }

    // ── Forgot Password (OTP) ───────────────────────────────────────────
    // Same pattern as the citizen app: request a code, then submit the
    // code + new password. Kept as two separate GET/POST pairs so a page
    // refresh on step 2 doesn't lose the email address (passed via query).

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        // Don't reveal whether the email exists — same response either way.
        if ($user) {
            $otp = (string) random_int(100000, 999999);

            $user->update([
                'reset_otp'            => $otp,
                'reset_otp_expires_at' => now()->addMinutes(10),
            ]);

            Mail::to($user->email)->send(new PasswordResetOtp($otp, $user->name));
        }

        return redirect()
            ->route('password.reset', ['email' => $request->email])
            ->with('success', 'If that email is registered, a 6-digit code has been sent.');
    }

    public function showResetPassword(Request $request)
    {
        return view('auth.reset-password', [
            'email' => $request->query('email'),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'otp'      => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = User::where('email', $request->email)
            ->where('reset_otp', $request->otp)
            ->first();

        if (! $user) {
            return back()->withErrors(['otp' => 'Invalid code.'])->onlyInput('email');
        }

        if ($user->reset_otp_expires_at === null || now()->greaterThan($user->reset_otp_expires_at)) {
            return back()->withErrors(['otp' => 'This code has expired. Please request a new one.'])->onlyInput('email');
        }

        $user->update([
            'password'              => Hash::make($request->password),
            'reset_otp'             => null,
            'reset_otp_expires_at'  => null,
            // Clears any "remember me" cookie tied to the old password
            // on other devices — a reset should end those sessions too.
            'remember_token'        => null,
        ]);

        return redirect()->route('login')->with('success', 'Password reset successful. Please log in.');
    }

    public function dashboard()
    {
        $recentAlerts = \App\Models\Alert::where('created_at', '>=', now()->subDays(3))
            ->orderByDesc('created_at')->take(5)->get();
        $totalIncidents = \App\Models\Incident::count();
        $incidentsToday      = \App\Models\Incident::whereDate('created_at', today())->count();
        $criticalIncidents = \App\Models\Incident::where('priority', 'critical')
            ->where('status', '!=', 'resolved')
            ->count();
        $respondingIncidents = \App\Models\Incident::where('status', 'responding')->count();
        $resolvedIncidents   = \App\Models\Incident::where('status', 'resolved')->count();

        // Normalizes stray/duplicate type labels before counting, rather
        // than charting every raw string as its own slice:
        //   - 'Vehicular Accident' was a citizen-facing dropdown option
        //     that duplicated 'Accident' (see report_incident.dart) —
        //     removed from the dropdown going forward, but existing rows
        //     already saved with it still need folding in here.
        //   - ai_detected_type is bounded to a fixed list (see
        //     ImageAnalysisService::EMERGENCY_TYPES) and always passes
        //     through as-is.
        //   - emergency_type, when a citizen picks "Other", is whatever
        //     free text they typed (e.g. "Side-Sweep", "Drowing
        //     Incident") — unbounded by nature, so anything that isn't a
        //     recognized canonical type rolls up into 'Other' instead of
        //     fragmenting the chart into one sliver per typo/phrasing.
        //   - SOS Emergency is checked BEFORE the ai_detected_type
        //     COALESCE, not folded into it. An SOS report can carry a
        //     photo that gets AI-classified (e.g. as 'Accident') — that
        //     describes what's IN the photo, not how the report was
        //     triggered. Without this priority check, an SOS with a
        //     photo would silently get counted as whatever the AI saw
        //     instead of as an SOS, undercounting genuine panic-button
        //     triggers in this breakdown.
        $reportsByType = \App\Models\Incident::selectRaw("
                CASE
                    WHEN emergency_type = 'SOS Emergency' THEN 'SOS Emergency'
                    WHEN COALESCE(ai_detected_type, emergency_type) = 'Vehicular Accident' THEN 'Accident'
                    WHEN COALESCE(ai_detected_type, emergency_type) IN (
                        'Fire', 'Flood', 'Earthquake', 'Accident', 'Medical Emergency', 'Landslide', 'SOS Emergency', 'Other'
                    ) THEN COALESCE(ai_detected_type, emergency_type)
                    ELSE 'Other'
                END as type,
                COUNT(*) as total
            ")
            ->groupBy('type')
            ->orderByDesc('total')
            ->pluck('total', 'type');

        $recentIncidents = \App\Models\Incident::with('citizen')
            ->orderByDesc('created_at')
            ->whereDate('created_at', today())
            ->take(4)
            ->get();

        $mapIncidents = \App\Models\Incident::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('status', '!=', 'resolved')
            ->select('id', 'emergency_type', 'location', 'latitude', 'longitude', 'status')
            ->orderByDesc('created_at')
            ->take(15)
            ->get();

        // "Incidents over Time" mini trend — last 7 days, one bucket per day.
        // Same shape/pattern as reportsAnalytics()'s $trendWeek.
        $trendWeek = collect(range(6, 0))->mapWithKeys(function ($daysAgo) {
            $date = now()->subDays($daysAgo);
            return [$date->format('M d') => \App\Models\Incident::whereDate('created_at', $date->toDateString())->count()];
        });

        return view('dashboard', compact(
            'recentAlerts', 'totalIncidents', 'incidentsToday', 'criticalIncidents',
            'respondingIncidents', 'resolvedIncidents', 'reportsByType', 'recentIncidents',
            'mapIncidents', 'trendWeek'
        ));
    }
    public function incident()
    {
        return view('incident');
    }

    public function incidentDetail($id)
    {
        return view('incident-details');
    }
    
    public function mapview()
    {
        return view('mapview');
    }
    public function alerts()
    {
        return view('alerts');
    }
    public function evacuation()
    {
        return view('evacuation');
    }
    public function reportsAnalytics(Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        // Default: last 30 days ending today. The date range at the top
        // of this page used to be hardcoded text ("May 21 - 28, 2025")
        // that wasn't wired to anything — by now it's over a year in the
        // past and never filtered a single number on the page.
        $toDate = isset($validated['to'])
            ? \Carbon\Carbon::parse($validated['to'])->endOfDay()
            : now()->endOfDay();
        $fromDate = isset($validated['from'])
            ? \Carbon\Carbon::parse($validated['from'])->startOfDay()
            : now()->subDays(29)->startOfDay();

        // Previous period of equal length, immediately before $fromDate —
        // this is what makes the trend arrows real instead of the
        // hardcoded "+12%" / "+5%" that never moved no matter what the
        // underlying data did.
        $periodLengthDays = $fromDate->diffInDays($toDate) + 1;
        $prevToDate = $fromDate->copy()->subSecond();
        $prevFromDate = $prevToDate->copy()->subDays($periodLengthDays - 1)->startOfDay();

        $totalIncidents = \App\Models\Incident::whereBetween('created_at', [$fromDate, $toDate])->count();
        $resolvedIncidents = \App\Models\Incident::whereBetween('created_at', [$fromDate, $toDate])
            ->where('status', 'resolved')->count();
        $resolutionRate = $totalIncidents > 0 ? round($resolvedIncidents / $totalIncidents * 100) : 0;
        $avgResponseMinutes = $this->averageResponseMinutes($fromDate, $toDate);

        $prevTotal = \App\Models\Incident::whereBetween('created_at', [$prevFromDate, $prevToDate])->count();
        $prevResolved = \App\Models\Incident::whereBetween('created_at', [$prevFromDate, $prevToDate])
            ->where('status', 'resolved')->count();
        $prevResolutionRate = $prevTotal > 0 ? round($prevResolved / $prevTotal * 100) : 0;
        $prevAvgResponseMinutes = $this->averageResponseMinutes($prevFromDate, $prevToDate);

        $totalTrend = $this->percentChange($prevTotal, $totalIncidents);
        $resolvedTrend = $this->percentChange($prevResolved, $resolvedIncidents);
        // Percentage-POINT difference, not a percent change of a percent
        // — 80% -> 84% should read "+4 pts", not a confusing "+5%".
        $resolutionRateTrend = $resolutionRate - $prevResolutionRate;
        $responseTimeTrend = ($avgResponseMinutes !== null && $prevAvgResponseMinutes !== null)
            ? $avgResponseMinutes - $prevAvgResponseMinutes
            : null;

        // Normalizes stray/duplicate type labels before counting, rather
        // than charting every raw string as its own slice:
        //   - 'Vehicular Accident' was a citizen-facing dropdown option
        //     that duplicated 'Accident' (see report_incident.dart) —
        //     removed from the dropdown going forward, but existing rows
        //     already saved with it still need folding in here.
        //   - ai_detected_type is bounded to a fixed list (see
        //     ImageAnalysisService::EMERGENCY_TYPES) and always passes
        //     through as-is.
        //   - emergency_type, when a citizen picks "Other", is whatever
        //     free text they typed (e.g. "Side-Sweep", "Drowing
        //     Incident") — unbounded by nature, so anything that isn't a
        //     recognized canonical type rolls up into 'Other' instead of
        //     fragmenting the chart into one sliver per typo/phrasing.
        //   - SOS Emergency is checked BEFORE the ai_detected_type
        //     COALESCE, not folded into it. An SOS report can carry a
        //     photo that gets AI-classified (e.g. as 'Accident') — that
        //     describes what's IN the photo, not how the report was
        //     triggered. Without this priority check, an SOS with a
        //     photo would silently get counted as whatever the AI saw
        //     instead of as an SOS, undercounting genuine panic-button
        //     triggers in this breakdown.
        $reportsByType = \App\Models\Incident::selectRaw("
                CASE
                    WHEN emergency_type = 'SOS Emergency' THEN 'SOS Emergency'
                    WHEN COALESCE(ai_detected_type, emergency_type) = 'Vehicular Accident' THEN 'Accident'
                    WHEN COALESCE(ai_detected_type, emergency_type) IN (
                        'Fire', 'Flood', 'Earthquake', 'Accident', 'Medical Emergency', 'Landslide', 'SOS Emergency', 'Other'
                    ) THEN COALESCE(ai_detected_type, emergency_type)
                    ELSE 'Other'
                END as type,
                COUNT(*) as total
            ")
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy('type')
            ->orderByDesc('total')
            ->pluck('total', 'type');

        // Same normalization, scoped to status='pending' only — lets the
        // legend show "2 pending" under a type instead of just a flat
        // count that doesn't say whether any of them still need
        // attention. Uses the same CASE WHEN as $reportsByType above so
        // a type here always matches a type there.
        $reportsByTypePending = \App\Models\Incident::selectRaw("
                CASE
                    WHEN emergency_type = 'SOS Emergency' THEN 'SOS Emergency'
                    WHEN COALESCE(ai_detected_type, emergency_type) = 'Vehicular Accident' THEN 'Accident'
                    WHEN COALESCE(ai_detected_type, emergency_type) IN (
                        'Fire', 'Flood', 'Earthquake', 'Accident', 'Medical Emergency', 'Landslide', 'SOS Emergency', 'Other'
                    ) THEN COALESCE(ai_detected_type, emergency_type)
                    ELSE 'Other'
                END as type,
                COUNT(*) as total
            ")
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->where('status', 'pending')
            ->groupBy('type')
            ->pluck('total', 'type');

        $reportsByLocation = \App\Models\Incident::selectRaw('location, COUNT(*) as total')
            ->whereNotNull('location')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy('location')
            ->orderByDesc('total')
            ->take(5)
            ->pluck('total', 'location');

        // Last 7 days, one bucket per day
        $trendWeek = collect(range(6, 0))->mapWithKeys(function ($daysAgo) {
            $date = now()->subDays($daysAgo);
            return [$date->format('D') => \App\Models\Incident::whereDate('created_at', $date->toDateString())->count()];
        });

        // Last 5 weeks, one bucket per week
        $trendMonth = collect(range(4, 0))->mapWithKeys(function ($weeksAgo) {
            $start = now()->subWeeks($weeksAgo)->startOfWeek();
            $end = now()->subWeeks($weeksAgo)->endOfWeek();
            $label = 'Wk ' . (5 - $weeksAgo);
            return [$label => \App\Models\Incident::whereBetween('created_at', [$start, $end])->count()];
        });

        // Last 12 months, one bucket per month
        $trendYear = collect(range(11, 0))->mapWithKeys(function ($monthsAgo) {
            $date = now()->subMonths($monthsAgo);
            return [$date->format('M') => \App\Models\Incident::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count()];
        });

        return view('report-analytics', compact(
            'totalIncidents', 'resolvedIncidents', 'resolutionRate', 'avgResponseMinutes',
            'totalTrend', 'resolvedTrend', 'resolutionRateTrend', 'responseTimeTrend',
            'reportsByType', 'reportsByTypePending', 'reportsByLocation',
            'trendWeek', 'trendMonth', 'trendYear',
            'fromDate', 'toDate'
        ));
    }

    /**
     * "Export Report" — was a button with no click handler at all, wired
     * to nothing. CSV rather than PDF: no new package to add (no
     * dompdf/snappy dependency to guess is actually installed), and CSV
     * opens directly in Excel/Sheets, which is what an MDRRMO office
     * actually needs this for (further sorting/filtering their own way).
     * Respects whatever from/to range is currently applied on the page —
     * exporting should never silently ignore the filter you're looking
     * at.
     */
    /**
     * "Export Report" — was a button with no click handler at all, wired
     * to nothing. Produces a real PDF via barryvdh/laravel-dompdf WHEN
     * that package is installed; otherwise falls back to the CSV export
     * this method used to always produce. Checked with class_exists()
     * rather than assumed, since there's no way to confirm from here
     * whether that package is actually in this project's composer.json —
     * guessing wrong would mean a fatal "class not found" the instant
     * someone clicks Export. Run:
     *   composer require barryvdh/laravel-dompdf
     * to enable real PDF output — nothing else needs to change once it's
     * installed, this method picks it up automatically.
     */
    public function exportReport(Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $toDate = isset($validated['to'])
            ? \Carbon\Carbon::parse($validated['to'])->endOfDay()
            : now()->endOfDay();
        $fromDate = isset($validated['from'])
            ? \Carbon\Carbon::parse($validated['from'])->startOfDay()
            : now()->subDays(29)->startOfDay();

        $incidents = \App\Models\Incident::with('citizen')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->orderBy('created_at')
            ->get();

        $totalIncidents = $incidents->count();
        $resolvedIncidents = $incidents->where('status', 'resolved')->count();
        $resolutionRate = $totalIncidents > 0 ? round($resolvedIncidents / $totalIncidents * 100) : 0;

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return $this->exportReportAsPdf($incidents, $fromDate, $toDate, $totalIncidents, $resolvedIncidents, $resolutionRate);
        }

        return $this->exportReportAsCsv($incidents, $fromDate, $toDate);
    }

    private function exportReportAsPdf($incidents, $fromDate, $toDate, $totalIncidents, $resolvedIncidents, $resolutionRate)
    {
        $filename = 'resqpulse-report_' . $fromDate->format('Y-m-d') . '_to_' . $toDate->format('Y-m-d') . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('report-pdf', [
            'incidents'         => $incidents,
            'fromDate'          => $fromDate,
            'toDate'            => $toDate,
            'totalIncidents'    => $totalIncidents,
            'resolvedIncidents' => $resolvedIncidents,
            'resolutionRate'    => $resolutionRate,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    private function exportReportAsCsv($incidents, $fromDate, $toDate)
    {
        $filename = 'resqpulse-report_' . $fromDate->format('Y-m-d') . '_to_' . $toDate->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($incidents) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM so Excel renders barangay names with ñ/special
            // characters correctly instead of mangling them.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['ID', 'Type', 'Priority', 'Status', 'Location', 'Reporter', 'Mobile', 'Reported At']);

            foreach ($incidents as $inc) {
                fputcsv($handle, [
                    $inc->id,
                    $inc->emergency_type,
                    $inc->priority ? ucfirst($inc->priority) : '—',
                    $inc->needs_review ? 'Pending Review' : ucfirst($inc->status),
                    $inc->location ?: '—',
                    $inc->citizen?->full_name ?? ($inc->citizen_id ? 'Unknown' : 'Guest'),
                    $inc->citizen?->mobile ?? '—',
                    $inc->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Average minutes from an incident's created_at to the FIRST
     * responder's accepted_at within the incident_responder pivot. This
     * is what the "Response Time (Avg)" stat card actually measures now
     * — it used to be a hardcoded "18m" with no query behind it at all.
     * Returns null (not 0) when nothing in range has been accepted yet,
     * so the view can show "—" instead of a misleading "0m".
     */
    private function averageResponseMinutes($from, $to): ?int
    {
        $avg = \Illuminate\Support\Facades\DB::table('incident_responder')
            ->join('incidents', 'incidents.id', '=', 'incident_responder.incident_id')
            ->whereBetween('incidents.created_at', [$from, $to])
            ->whereNotNull('incident_responder.accepted_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, incidents.created_at, incident_responder.accepted_at)) as avg_minutes')
            ->value('avg_minutes');

        return $avg !== null ? (int) round($avg) : null;
    }

    /** Percent change from $old to $new, handling the $old === 0 edge case. */
    private function percentChange(int $old, int $new): int
    {
        if ($old === 0) {
            return $new > 0 ? 100 : 0;
        }
        return (int) round((($new - $old) / $old) * 100);
    }

    public function logout(Request $request)
    {
        AuditLogService::log('logout', auth()->user()->name . ' logged out.');
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}