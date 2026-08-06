<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetOtp;
use App\Models\User;
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
        $recentAlerts = \App\Models\Alert::orderByDesc('created_at')->take(5)->get();
        $totalIncidents = \App\Models\Incident::count();
        $totalIncidents      = \App\Models\Incident::count();
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

        return view('dashboard', compact('recentAlerts', 'totalIncidents', 'incidentsToday', 'criticalIncidents', 'respondingIncidents', 'resolvedIncidents', 'reportsByType', 'recentIncidents'));


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
        return view('report-analytics');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}