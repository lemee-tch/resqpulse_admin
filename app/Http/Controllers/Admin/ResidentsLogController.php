<?php
// app/Http/Controllers/Admin/ResidentsLogController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Citizen;
use Illuminate\Http\Request;

class ResidentsLogController extends Controller
{
    /**
     * Who registered, and when — reads straight from the citizens table
     * (created_at = registration time) rather than the audit_logs table,
     * so every citizen shows up here even ones who registered before
     * AuthController::register() started writing an audit-log row for
     * it. Deliberately separate from Residents Verification (which is
     * only about the pending/verified ID-review workflow) and from the
     * shared Audit Log page (which mixes in admin actions, logins,
     * reports, guest activity, etc.) — this page is just the
     * registration record.
     */
    public function index(Request $request)
    {
        $query = Citizen::orderByDesc('created_at');

        if ($request->filled('barangay')) {
            $query->where('barangay', $request->barangay);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%");
            });
        }

        $citizens = $query->paginate(30)->withQueryString();

        // Distinct, sorted barangay list for the filter dropdown —
        // pulled from actual registrations rather than the full
        // Rosales barangay list, so it only ever shows options that
        // return results.
        $barangays = Citizen::whereNotNull('barangay')
            ->distinct()
            ->orderBy('barangay')
            ->pluck('barangay');

        return view('residents-log', compact('citizens', 'barangays'));
    }
}