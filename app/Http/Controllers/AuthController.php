<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    public function dashboard()
    {
        $recentAlerts = \App\Models\Alert::orderByDesc('created_at')->take(5)->get();
        $totalIncidents = \App\Models\Incident::count();
        $totalIncidents      = \App\Models\Incident::count();
        $incidentsToday      = \App\Models\Incident::whereDate('created_at', today())->count();
        $criticalIncidents   = \App\Models\Incident::where('priority', 'critical')->count();
        $respondingIncidents = \App\Models\Incident::where('status', 'responding')->count();
        $resolvedIncidents   = \App\Models\Incident::where('status', 'resolved')->count();

        $reportsByType = \App\Models\Incident::selectRaw('emergency_type, COUNT(*) as total')
            ->groupBy('emergency_type')
            ->orderByDesc('total')
            ->pluck('total', 'emergency_type');

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
    public function users()
    {
        return view('users');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}