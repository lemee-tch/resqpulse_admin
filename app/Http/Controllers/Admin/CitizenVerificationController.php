<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CitizenRejected;
use App\Models\Citizen;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class CitizenVerificationController extends Controller
{
    public function index()
    {
        $this->pruneStaleUnverified();

        $citizens = Citizen::whereNotNull('email_verified_at')
            ->orderByDesc('created_at')
            ->get();

        return view('citizen-verification', compact('citizens'));
    }

    public function approve(Citizen $citizen)
    {
        $citizen->update([
            'verification_status' => 'verified',
            'verified_at' => now(),
            'rejection_reason' => null,
        ]);

        AuditLogService::log('approved', "Verified citizen {$citizen->full_name}.", $citizen);

        return back()->with('success', "{$citizen->full_name} has been verified.");
    }

    public function reject(Request $request, Citizen $citizen)
    {
        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:255'],
        ]);

        $name = $citizen->full_name;
        $email = $citizen->email;
        $reason = $request->rejection_reason;

        AuditLogService::log('rejected', "Rejected citizen {$name}'s ID — {$reason}.");

        // Revoke any active login tokens before deleting, so any session
        // currently logged in on the phone gets immediately invalidated.
        $citizen->tokens()->delete();

        // Send the notification BEFORE deleting — once gone, there's no
        // model left to pull the name/email from.
        Mail::to($email)->send(new CitizenRejected($name, $reason));

        $citizen->delete();

        return back()->with('success', "{$name}'s account was rejected and removed. A notification was sent to {$email}.");
    }

    /**
     * Opportunistic cleanup of registrations that never completed email
     * verification. Runs at most once per hour, triggered by whoever next
     * opens this page — no CLI/cron access needed.
     */
    private function pruneStaleUnverified(): void
    {
        if (Cache::has('citizens_pruned_recently')) {
            return;
        }

        Cache::put('citizens_pruned_recently', true, now()->addHour());

        $stale = Citizen::whereNull('email_verified_at')
            ->where('created_at', '<', now()->subDay())
            ->get();

        foreach ($stale as $citizen) {
            if ($citizen->valid_id_path) {
                Storage::disk('public')->delete($citizen->valid_id_path);
            }
            $citizen->delete();
        }
    }
}