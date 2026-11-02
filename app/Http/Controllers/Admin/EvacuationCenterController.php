<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EvacuationCenter;
use Illuminate\Http\Request;

class EvacuationCenterController extends Controller
{
    public function index()
    {
        $centers = EvacuationCenter::orderByDesc('created_at')->get();

        return view('evacuation', compact('centers'));
    }

    public function store(Request $request)
    {
        // No status on the form: centers are always open (item 58). A new
        // center has room; MSWD marks it full in Resqponder when it fills.
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'barangay'  => ['required', 'string', 'max:255'],
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        EvacuationCenter::create($validated + ['status' => 'open', 'occupancy' => 0]);

        return back()->with('success', 'Evacuation center added.');
    }

    /**
     * Marks a center full, or takes that off once it has room again.
     * Centers are always open (item 58): 'open' means it has room, and
     * there's no "closed".
     */
    public function updateStatus(Request $request, EvacuationCenter $center)
    {
        $request->validate([
            'status' => ['required', 'in:open,full'],
        ]);

        $center->update(['status' => $request->status]);

        return back()->with('success', $center->isFull()
            ? "{$center->name} is marked full."
            : "{$center->name} has room again.");
    }

    /**
     * View-only for the admin panel — creating an entry happens
     * exclusively through the responder app now (see
     * Api\EvacuationCenterController::storeEvacuee), gated to MSWD
     * accounts specifically since they're the ones physically at the
     * center. This just lets office staff review what's been logged.
     */
    public function showLog(EvacuationCenter $center)
    {
        $evacuees = $center->evacuees()
            ->with('loggedBy')
            ->orderByDesc('created_at')
            ->get();

        return view('evacuation-log', compact('center', 'evacuees'));
    }
}