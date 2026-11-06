<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item 64: when a resident agreed to the Terms and Conditions and the
 * Privacy Policy, and to which version. The Data Privacy Act needs consent
 * to be shown "by written, electronic or recorded means"; the app's
 * sign-up check box sends it (Api\AuthController::register()).
 *
 *  privacy_consent_at       when they ticked the box and signed up
 *  privacy_consent_version  the documents' version, e.g. "2026-11-05"
 *                           (App\Support\Legal::VERSION)
 *
 * Accounts made before this item have none recorded.
 *
 * Runs by itself on deploy (php artisan migrate --force). The same columns
 * by hand, in phpMyAdmin → SQL:
 *
 *   ALTER TABLE citizens
 *     ADD privacy_consent_at TIMESTAMP NULL AFTER verified_at,
 *     ADD privacy_consent_version VARCHAR(20) NULL AFTER privacy_consent_at;
 *
 * If the columns were already added by hand, this migration skips them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citizens', function (Blueprint $table) {
            if (! Schema::hasColumn('citizens', 'privacy_consent_at')) {
                $table->timestamp('privacy_consent_at')->nullable()->after('verified_at');
            }
            if (! Schema::hasColumn('citizens', 'privacy_consent_version')) {
                $table->string('privacy_consent_version', 20)->nullable()->after('privacy_consent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('citizens', function (Blueprint $table) {
            foreach (['privacy_consent_at', 'privacy_consent_version'] as $column) {
                if (Schema::hasColumn('citizens', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
