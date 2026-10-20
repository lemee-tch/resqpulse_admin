<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Item 50: every broadcast clears from the residents' and responders' apps
 * by itself 24 hours after it's sent (Alert::CLEARS_AFTER_HOURS). The admin
 * no longer picks 1 to 30 days ("Clears from the apps after") on Alerts &
 * Broadcast.
 *
 * Alerts already sent with a longer time now clear 24 hours after they
 * were sent too. One sent more than a day ago leaves the apps right away;
 * it stays in Broadcast History, "Cleared by itself". Alerts an admin
 * already cleared are left as they are.
 *
 * Runs by itself on deploy (php artisan migrate --force). The same change
 * by hand, in phpMyAdmin → SQL:
 *
 *   UPDATE alerts SET expires_at = DATE_ADD(created_at, INTERVAL 24 HOUR)
 *     WHERE cleared_at IS NULL AND created_at IS NOT NULL
 *       AND (expires_at IS NULL
 *            OR expires_at > DATE_ADD(created_at, INTERVAL 24 HOUR));
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('alerts')
            ->whereNull('cleared_at')
            ->whereNotNull('created_at')
            ->select(['id', 'created_at', 'expires_at'])
            ->chunkById(200, function ($alerts) {
                foreach ($alerts as $alert) {
                    $clears = Carbon::parse($alert->created_at)->addHours(24);

                    if ($alert->expires_at === null || Carbon::parse($alert->expires_at)->gt($clears)) {
                        DB::table('alerts')->where('id', $alert->id)->update(['expires_at' => $clears]);
                    }
                }
            });
    }

    /** The times the admin picked before aren't kept, so nothing goes back. */
    public function down(): void
    {
        //
    }
};
