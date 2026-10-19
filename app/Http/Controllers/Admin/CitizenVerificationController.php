<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CitizenRejected;
use App\Models\AuditLog;
use App\Models\Citizen;
use App\Services\AuditLogService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class CitizenVerificationController extends Controller
{
    public function index()
    {
        $this->pruneStaleUnverified();

        // Finished sign-ups only — the code was entered, by email or by text.
        $citizens = Citizen::contactVerified()
            ->orderByDesc('created_at')
            ->get();

        // "All Registered" tab — every resident/guest action (logins,
        // incident/SOS reports, profile edits, registration itself),
        // same rows the shared Audit Log page shows under its
        // actor=resident filter (AuditLogController::index()), just
        // surfaced here too since this is where residents are managed.
        // whereNull('user_id') is what separates a resident/guest mobile
        // API action from an admin-panel one — see
        // AuditLog::getIsAdminActionAttribute().
        $residentLogs = AuditLog::with(['auditable'])
            ->whereNull('user_id')
            ->orderByDesc('created_at')
            ->paginate(30, ['*'], 'log_page');

        return view('citizen-verification', compact('citizens', 'residentLogs'));
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

    /**
     * Rejects the ID: the resident gets the reason (email, or a text for a
     * resident who signed up with a mobile number) and the account is
     * removed.
     *
     * The reason is one from the list, or "Other" with the reason the admin
     * typed in rejection_reason_other (panel revision: Reject Action > add
     * "Other" and a field to type the reason).
     */
    public function reject(Request $request, Citizen $citizen, SmsService $sms)
    {
        $request->validate([
            'rejection_reason'       => ['required', 'string', 'max:255'],
            'rejection_reason_other' => ['nullable', 'required_if:rejection_reason,Other', 'string', 'min:5', 'max:255'],
        ], [
            'rejection_reason.required'          => 'Choose a reason for rejecting the ID.',
            'rejection_reason_other.required_if' => 'Type the reason for rejecting the ID, or choose one from the list.',
            'rejection_reason_other.min'         => 'The reason is too short. Say what\'s wrong with the ID.',
            'rejection_reason_other.max'         => 'Keep the reason under 255 characters.',
        ]);

        $name = $citizen->full_name;
        $email = $citizen->email;
        $mobile = $citizen->mobile;
        $reason = $request->input('rejection_reason') === 'Other'
            ? trim((string) preg_replace('/\s+/u', ' ', (string) $request->input('rejection_reason_other')))
            : (string) $request->input('rejection_reason');

        // A typed reason may already end with a full stop.
        AuditLogService::log('rejected', "Rejected citizen {$name}'s ID — " . rtrim($reason, '. ') . '.');

        // Revoke any active login tokens before deleting, so any session
        // currently logged in on the phone gets immediately invalidated.
        $citizen->tokens()->delete();

        // Send the notification BEFORE deleting — once gone, there's no
        // model left to pull the name/email from. A resident who signed up
        // with a mobile number has no email, so they get a text instead.
        if (! $citizen->isMobileAccount()) {
            Mail::to($email)->send(new CitizenRejected($name, $reason));
            $notified = "A notification was sent to {$email}.";
        } elseif ($sms->sendMessage((string) $mobile, $this->rejectionText($reason))) {
            $notified = "A text message was sent to {$mobile}.";
        } else {
            $notified = "They have no email and the text to {$mobile} couldn't be sent, so they weren't notified.";
        }

        $idPhoto = $citizen->valid_id_path;
        $citizen->delete();

        // The account is gone, so its ID photo has nothing left to belong to.
        $this->deleteIdPhoto($idPhoto);

        return back()->with('success', "{$name}'s account was rejected and removed. {$notified}");
    }

    /**
     * The text for a rejected mobile-number sign-up. The reason is cut so
     * the whole text stays within two SMS parts.
     */
    private function rejectionText(string $reason): string
    {
        $reason = rtrim(\Illuminate\Support\Str::limit(trim($reason), 120, '...'), '.');

        return "ResQPulse: Your sign-up was not approved. Reason: {$reason}. "
            . 'You may sign up again with a clear photo of a valid ID.';
    }

    /**
     * Sends a resident's ID photo to a logged-in admin. Used by the
     * Proof of Residency thumbnails and the ID preview on this page
     * (route citizen-verification.id-photo, behind the auth middleware).
     *
     * Sign-up saves ID photos on the private 'local' disk
     * (storage/app/private), which has no public link — so the page's old
     * Storage::url() links never loaded. Older sign-ups may still be on the
     * 'public' disk, so both are checked.
     */
    public function idPhoto(Citizen $citizen)
    {
        $path = $citizen->valid_id_path;

        if ($path) {
            foreach (['local', 'public'] as $disk) {
                try {
                    if (Storage::disk($disk)->exists($path)) {
                        return Storage::disk($disk)->response($path, null, [
                            'Cache-Control'          => 'private, max-age=300',
                            'X-Content-Type-Options' => 'nosniff',
                        ]);
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        // The page shows "Image unavailable" instead of a broken image.
        abort(404);
    }

    /**
     * Opportunistic cleanup of registrations whose code was never entered
     * (by email or by text) within a day. Runs at most once per hour,
     * triggered by whoever next opens this page — no CLI/cron access needed.
     */
    private function pruneStaleUnverified(): void
    {
        if (Cache::has('citizens_pruned_recently')) {
            return;
        }

        Cache::put('citizens_pruned_recently', true, now()->addHour());

        $stale = Citizen::contactUnverified()
            ->where('created_at', '<', now()->subDay())
            ->get();

        foreach ($stale as $citizen) {
            $this->deleteIdPhoto($citizen->valid_id_path);
            $citizen->delete();
        }
    }

    /**
     * Deletes an ID photo from wherever it is. This used to look only on
     * the 'public' disk, but sign-up saves on 'local', so photos of removed
     * sign-ups stayed on the server.
     */
    private function deleteIdPhoto(?string $path): void
    {
        if (! $path) {
            return;
        }

        foreach (['local', 'public'] as $disk) {
            try {
                Storage::disk($disk)->delete($path);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}