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
        // 'auditable' is eager-loaded too — AuditLog::getActorNameAttribute()
        // reads it to recover a citizen's name for rows the admin guard
        // never authenticated (see that accessor's doc comment).
        $query = AuditLog::with(['user', 'auditable'])->orderByDesc('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Admin vs. Resident/Guest — every admin-panel action carries a
        // user_id (the admin guard authenticated it); every citizen/guest
        // mobile-API action never does, regardless of which model it
        // touched. See AuditLog::getIsAdminActionAttribute().
        if ($request->filled('actor')) {
            if ($request->actor === 'admin') {
                $query->whereNotNull('user_id');
            } elseif ($request->actor === 'resident') {
                $query->whereNull('user_id');
            }
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