<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CitizenRejected;
use App\Models\AuditLog;
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

        $idPhoto = $citizen->valid_id_path;
        $citizen->delete();

        // The account is gone, so its ID photo has nothing left to belong to.
        $this->deleteIdPhoto($idPhoto);

        return back()->with('success', "{$name}'s account was rejected and removed. A notification was sent to {$email}.");
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