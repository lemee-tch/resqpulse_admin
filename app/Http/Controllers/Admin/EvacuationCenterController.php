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
        // 'status' removed here on purpose — the Add Center form no
        // longer has a Status field (see evacuation.blade.php), so this
        // would 422 on every submission if 'status' stayed required.
        // Every new center starts 'open'; status is changed afterward
        // from the Action column's inline dropdown via updateStatus()
        // below.
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
     * The inline status dropdown in the Action column (evacuation.blade
     * .php) posts here — this was missing entirely, which is what threw
     * the "Call to undefined method" error.
     */
    public function updateStatus(Request $request, EvacuationCenter $center)
    {
        $request->validate([
            'status' => ['required', 'in:open,full,closed'],
        ]);

        $center->update(['status' => $request->status]);

        return back()->with('success', "{$center->name}'s status updated to " . ucfirst($request->status) . '.');
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