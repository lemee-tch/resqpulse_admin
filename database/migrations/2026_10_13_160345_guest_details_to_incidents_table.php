<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guests (no account) must give their full name and mobile number before
 * they can send an SOS or a report, so MDRRMO can call them back to
 * confirm and pranks can be traced to someone. A guest has no citizens
 * row, so the details are kept on the incident itself.
 *
 * Runs on deploy (php artisan migrate --force, see nixpacks.toml). If the
 * database is updated by hand in phpMyAdmin instead, the same change is:
 *
 *   ALTER TABLE incidents
 *     ADD COLUMN guest_name VARCHAR(120) NULL AFTER citizen_id,
 *     ADD COLUMN guest_mobile VARCHAR(20) NULL AFTER guest_name;
 *
 * Api\IncidentController checks that these columns exist before writing
 * to them, so an SOS never fails if this hasn't run yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            // Skipped if the columns were already added by hand.
            if (! Schema::hasColumn('incidents', 'guest_name')) {
                $table->string('guest_name', 120)->nullable()->after('citizen_id');
            }
            if (! Schema::hasColumn('incidents', 'guest_mobile')) {
                $table->string('guest_mobile', 20)->nullable()->after('guest_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn(['guest_name', 'guest_mobile']);
        });
    }
};