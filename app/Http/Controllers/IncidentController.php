<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    /**
     * Regular citizen-submitted reports only. SOS panic-button alerts
     * are excluded here and live on their own page — see sosIndex().
     */
    public function index()
    {
        $incidents = Incident::with('citizen')
            ->where('emergency_type', '!=', 'SOS Emergency')
            ->orderByDesc('created_at')
            ->get();

        return view('incident', compact('incidents'));
    }

    /**
     * SOS panic-button alerts only — kept separate from regular reports
     * since these are always critical priority and need faster triage.
     */
    public function sosIndex()
    {
        $sosAlerts = Incident::with('citizen')
            ->where('emergency_type', 'SOS Emergency')
            ->orderByDesc('created_at')
            ->get();

        return view('sos-alerts', compact('sosAlerts'));
    }

    public function updatePriority(Request $request, Incident $incident)
    {
        $request->validate([
            'priority' => ['required', 'in:critical,high,moderate,low'],
        ]);

        $incident->update(['priority' => $request->priority]);

        return back()->with('success', 'Priority updated.');
    }

    public function updateStatus(Request $request, Incident $incident)
    {
        $request->validate([
            'status' => ['required', 'in:pending,acknowledged,responding,resolved'],
        ]);

        $incident->update(['status' => $request->status]);

        return back()->with('success', 'Status updated.');
    }

    public function updateNote(Request $request, Incident $incident)
    {
        $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $incident->update(['admin_notes' => $request->admin_notes]);

        return back()->with('success', 'Note saved.');
    }

    public function show(Incident $incident)
    {
        $incident->load('citizen');
        return view('incident-details', compact('incident'));
    }

    public function latestSos()
    {
        $latest = Incident::where('emergency_type', 'SOS Emergency')
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->first(['id', 'created_at', 'location']);

        return response()->json(['latest' => $latest]);
    }
}