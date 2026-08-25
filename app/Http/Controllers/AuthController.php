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
        $criticalIncidents   = \App\Models\Incident::where('priority', 'critical')->count();
        $respondingIncidents = \App\Models\Incident::where('status', 'responding')->count();
        $resolvedIncidents   = \App\Models\Incident::where('status', 'resolved')->count();

        $reportsByType = \App\Models\Incident::selectRaw('COALESCE(ai_detected_type, emergency_type) as type, COUNT(*) as total')
            ->groupBy('type')
            ->orderByDesc('total')
            ->pluck('total', 'type');

        $recentIncidents = \App\Models\Incident::with('citizen')
            ->orderByDesc('created_at')
            ->whereDate('created_at', today())
            ->take(4)
            ->get();

        // Pins for the dashboard's mini preview map — same "still-active"
        // filter as MapViewController, capped to a small count since this
        // is just a glance-preview (the full Map View has everything).
        $mapIncidents = \App\Models\Incident::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('status', '!=', 'resolved')
            ->select('id', 'emergency_type', 'location', 'latitude', 'longitude', 'status')
            ->orderByDesc('created_at')
            ->take(15)
            ->get();

            return view('dashboard', compact(
                'recentAlerts', 'totalIncidents', 'incidentsToday', 'criticalIncidents',
                'respondingIncidents', 'resolvedIncidents', 'reportsByType', 'recentIncidents',
                'mapIncidents'
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
    public function reportsAnalytics()
    {
        $totalIncidents = \App\Models\Incident::count();
        $resolvedIncidents = \App\Models\Incident::where('status', 'resolved')->count();

        $reportsByType = \App\Models\Incident::selectRaw('COALESCE(ai_detected_type, emergency_type) as type, COUNT(*) as total')
            ->groupBy('type')
            ->orderByDesc('total')
            ->pluck('total', 'type');

        $reportsByLocation = \App\Models\Incident::selectRaw('location, COUNT(*) as total')
            ->whereNotNull('location')
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
            'totalIncidents', 'resolvedIncidents',
            'reportsByType', 'reportsByLocation',
            'trendWeek', 'trendMonth', 'trendYear'
        ));
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