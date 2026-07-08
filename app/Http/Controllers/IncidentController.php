<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function index()
    {
        $incidents = Incident::with('citizen')
            ->orderByDesc('created_at')
            ->get();

        return view('incident', compact('incidents'));
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
            'status' => ['required', 'in:pending,responding,resolved'],
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
}