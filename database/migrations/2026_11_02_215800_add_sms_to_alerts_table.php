<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item 56: every broadcast from Alerts & Broadcast is texted too. How it
 * went is kept with the broadcast, for Broadcast History:
 *
 *  sms_sent     mobile numbers the text went to; null = not texted
 *               (texting was off, or the broadcast is from before item 56)
 *  sms_failed   numbers the gateway didn't send it to
 *  sms_credits  about how many Semaphore credits it used
 *  sms_note     why some or all weren't texted (e.g. what Semaphore said)
 *
 * Runs by itself on deploy (php artisan migrate --force). The same columns
 * by hand, in phpMyAdmin → SQL:
 *
 *   ALTER TABLE alerts
 *     ADD sms_sent INT UNSIGNED NULL,
 *     ADD sms_failed INT UNSIGNED NULL,
 *     ADD sms_credits INT UNSIGNED NULL,
 *     ADD sms_note VARCHAR(255) NULL;
 *
 * If the columns were already added by hand, this migration skips them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            if (! Schema::hasColumn('alerts', 'sms_sent')) {
                $table->unsignedInteger('sms_sent')->nullable();
            }
            if (! Schema::hasColumn('alerts', 'sms_failed')) {
                $table->unsignedInteger('sms_failed')->nullable();
            }
            if (! Schema::hasColumn('alerts', 'sms_credits')) {
                $table->unsignedInteger('sms_credits')->nullable();
            }
            if (! Schema::hasColumn('alerts', 'sms_note')) {
                $table->string('sms_note', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            foreach (['sms_sent', 'sms_failed', 'sms_credits', 'sms_note'] as $column) {
                if (Schema::hasColumn('alerts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
