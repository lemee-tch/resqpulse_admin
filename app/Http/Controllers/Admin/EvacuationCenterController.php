<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EvacuationCenter;
use Illuminate\Http\Request;

class EvacuationCenterController extends Controller
{
    public function index()
    {
        $centers = EvacuationCenter::orderBy('name')->get();

        return view('evacuation', compact('centers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'barangay'  => ['required', 'string', 'max:255'],
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'capacity'  => ['required', 'integer', 'min:1'],
            'status'    => ['required', 'in:open,full,closed'],
        ]);

        EvacuationCenter::create($validated + ['occupancy' => 0]);

        return back()->with('success', 'Evacuation center added.');
    }
}