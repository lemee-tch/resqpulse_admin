<?php
// app/Http/Controllers/Admin/AuditLogController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        // Admin actions only — every admin-panel action carries a
        // user_id (the admin guard authenticated it). Resident/guest
        // mobile-API activity (logins, reports, profile edits,
        // registration — never carries a user_id, see
        // AuditLog::getIsAdminActionAttribute()) lives on its own tab
        // on the Residents Verification page now
        // (Admin\CitizenVerificationController::index()'s
        // $residentLogs), not here, so it isn't shown twice in two
        // different places.
        $query = AuditLog::with('user')->whereNotNull('user_id')->orderByDesc('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        $logs = $query->paginate(30)->withQueryString();
        $adminUsers = User::orderBy('name')->get(['id', 'name']);

        return view('audit-log', compact('logs', 'adminUsers'));
    }
}