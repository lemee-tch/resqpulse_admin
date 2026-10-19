<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clearing past alerts (panel comment #22).
 *
 *  expires_at  when the alert clears itself from the residents' and
 *              responders' apps. The admin picks it when sending the
 *              broadcast (1 to 30 days, see Alert::CLEAR_AFTER_DAYS).
 *  cleared_at  when an admin cleared it early (Alerts & Broadcast → Clear)
 *  cleared_by  that admin (users.id)
 *
 * A cleared alert is no longer sent to the apps (GET /api/alerts) but
 * stays in Broadcast History, marked Cleared.
 *
 * Alerts sent before this change clear 7 days after they were sent, the
 * same as a new alert by default.
 *
 * Runs by itself on deploy (php artisan migrate --force). The same change
 * by hand, in phpMyAdmin → SQL:
 *
 *   ALTER TABLE alerts
 *     ADD COLUMN expires_at TIMESTAMP NULL AFTER type,
 *     ADD COLUMN cleared_at TIMESTAMP NULL AFTER expires_at,
 *     ADD COLUMN cleared_by BIGINT UNSIGNED NULL AFTER cleared_at;
 *   UPDATE alerts SET expires_at = DATE_ADD(created_at, INTERVAL 7 DAY)
 *     WHERE expires_at IS NULL;
 *
 * If the SQL was already run by hand, this migration skips what exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'expires_at' => fn (Blueprint $t) => $t->timestamp('expires_at')->nullable()->after('type'),
            'cleared_at' => fn (Blueprint $t) => $t->timestamp('cleared_at')->nullable()->after('expires_at'),
            'cleared_by' => fn (Blueprint $t) => $t->unsignedBigInteger('cleared_by')->nullable()->after('cleared_at'),
        ];

        foreach ($columns as $name => $add) {
            if (! Schema::hasColumn('alerts', $name)) {
                Schema::table('alerts', fn (Blueprint $table) => $add($table));
            }
        }

        // Alerts already sent: 7 days after they were sent.
        DB::table('alerts')->whereNull('expires_at')->chunkById(200, function ($alerts) {
            foreach ($alerts as $alert) {
                $sent = $alert->created_at ? Carbon::parse($alert->created_at) : now();

                DB::table('alerts')->where('id', $alert->id)->update([
                    'expires_at' => $sent->copy()->addDays(7),
                ]);
            }
        });
    }

    public function down(): void
    {
        foreach (['cleared_by', 'cleared_at', 'expires_at'] as $name) {
            if (Schema::hasColumn('alerts', $name)) {
                Schema::table('alerts', fn (Blueprint $table) => $table->dropColumn($name));
            }
        }
    }
};
