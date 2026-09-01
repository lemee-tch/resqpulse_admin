<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Responder;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Manages the pre-built, one-account-per-agency responder logins created
 * by ResponderSeeder. There is no "add account" here on purpose — the
 * agency list is fixed (PNP, BFP, SARS, HCU, MSWD, one row each, enforced
 * by a unique constraint on `agency`); this page only lets an admin
 * update contact details, reset the shared password, or toggle an
 * agency's account active/inactive.
 */
class ResponderAccountController extends Controller
{
    public function index()
    {
        $responders = Responder::orderBy('agency')->get();

        return view('responder-accounts', compact('responders'));
    }

    public function update(Request $request, Responder $responder)
    {
        $validated = $request->validate([
            'email'        => ['required', 'email', Rule::unique('responders', 'email')->ignore($responder->id)],
            'mobile'       => ['nullable', 'string', Rule::unique('responders', 'mobile')->ignore($responder->id)],
            'unit_station' => ['nullable', 'string', 'max:255'],
        ]);

        $responder->update($validated);

        AuditLogService::log('updated', "Updated contact details for the {$responder->agency} responder account.", $responder);

        return back()->with('success', "{$responder->agency} account details updated.");
    }

    /**
     * Resetting the shared password signs out every device currently
     * using it — same as it would for an individual account. The admin
     * is then responsible for handing the new password to the agency.
     */
    public function resetPassword(Request $request, Responder $responder)
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:6'],
        ]);

        // Plain text on purpose — Responder::$password is cast as
        // 'hashed', so Eloquent hashes it automatically on save. Calling
        // Hash::make() here too double-hashes it and breaks login with
        // a "does not use the Bcrypt algorithm" error.
        $responder->update(['password' => $validated['password']]);
        $responder->tokens()->delete();

        AuditLogService::log('updated', "Reset the shared login password for the {$responder->agency} responder account.", $responder);

        return back()->with('success', "New password set for {$responder->agency}. Every signed-in device was logged out — share the new password with the team.");
    }

    public function toggleStatus(Responder $responder)
    {
        $newStatus = $responder->status === 'active' ? 'inactive' : 'active';
        $responder->update(['status' => $newStatus]);

        // Deactivating should immediately cut off every device using
        // this shared login, not just block future logins.
        if ($newStatus === 'inactive') {
            $responder->tokens()->delete();
        }

        AuditLogService::log('updated', "Set the {$responder->agency} responder account to {$newStatus}.", $responder);

        return back()->with('success', "{$responder->agency} account is now {$newStatus}.");
    }
}