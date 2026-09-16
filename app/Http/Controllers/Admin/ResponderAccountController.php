<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\EmailVerificationOtp;
use App\Models\Responder;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class ResponderAccountController extends Controller
{
    public function index()
    {
        $responders = Responder::orderBy('agency')->get();

        return view('responder-accounts', compact('responders'));
    }

    /**
     * Step 1 of changing the login email — validates the new address and
     * actually emails a 6-digit code to it (reusing the same
     * EmailVerificationOtp mailable/template the citizen app already
     * uses). Nothing on the responder's real `email` changes yet; it's
     * parked in `pending_email` until update() below confirms the code.
     * AJAX endpoint (called from responder-accounts.blade's "Get Code"
     * button), so this returns JSON, not a redirect.
     */
    public function sendEmailOtp(Request $request, Responder $responder)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', Rule::unique('responders', 'email')->ignore($responder->id)],
        ]);

        $otp = (string) random_int(100000, 999999);

        $responder->update([
            'pending_email'                     => $validated['email'],
            'email_verification_otp'            => $otp,
            'email_verification_otp_expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($validated['email'])->send(
            new EmailVerificationOtp($otp, "{$responder->agency} Team")
        );

        return response()->json([
            'message' => "Verification code sent to {$validated['email']}.",
        ]);
    }

    /**
     * Step 2 — confirms the code and only THEN applies the email change.
     * Deliberately re-reads the new address from `pending_email` (set by
     * sendEmailOtp above) rather than trusting an `email` field in this
     * request — the only way to get a pending_email onto the account at
     * all is through step 1's own validation, so there's no path to
     * saving an unverified/unvalidated address.
     */
    public function update(Request $request, Responder $responder)
    {
        $request->validate([
            'otp' => ['required', 'string'],
        ]);

        if (! $responder->pending_email || ! $responder->email_verification_otp) {
            return back()->withErrors(['otp' => 'Request a verification code first.']);
        }

        if ($responder->email_verification_otp_expires_at === null
            || now()->greaterThan($responder->email_verification_otp_expires_at)) {
            return back()->withErrors(['otp' => 'This code has expired. Please request a new one.']);
        }

        if ($request->otp !== $responder->email_verification_otp) {
            return back()->withErrors(['otp' => 'Invalid code.']);
        }

        $newEmail = $responder->pending_email;

        $responder->update([
            'email'                              => $newEmail,
            'pending_email'                      => null,
            'email_verification_otp'             => null,
            'email_verification_otp_expires_at'  => null,
        ]);

        AuditLogService::log('updated', "Updated login email for {$responder->agency} responder account.", $responder);

        return back()->with('success', "{$responder->agency}'s login email was updated to {$newEmail}.");
    }

    public function resetPassword(Request $request, Responder $responder)
    {
        $request->validate([
            'password' => ['required', 'string', 'min:6'],
        ]);

        $responder->update([
            'password' => Hash::make($request->password),
        ]);

        // Immediately signs out every device currently using this account.
        $responder->tokens()->delete();

        AuditLogService::log('updated', "Reset password for {$responder->agency} responder account.", $responder);

        return back()->with('success', "{$responder->agency}'s password was reset. Share the new password with the team.");
    }

    public function toggleStatus(Responder $responder)
    {
        $newStatus = $responder->status === 'active' ? 'inactive' : 'active';

        $responder->update(['status' => $newStatus]);

        if ($newStatus === 'inactive') {
            // Deactivating should also kick any currently-logged-in devices.
            $responder->tokens()->delete();
        }

        AuditLogService::log('updated', "Set {$responder->agency} responder account to {$newStatus}.", $responder);

        return back()->with('success', "{$responder->agency} account is now {$newStatus}.");
    }
}