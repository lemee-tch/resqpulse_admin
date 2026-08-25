<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the reset_otp / reset_otp_expires_at columns to `responders`,
 * mirroring the citizens.reset_otp columns added by
 * 2026_06_24_081957_add_reset_password.php. Needed for the responder
 * app's new "Forgot Password" flow (ResponderAuthController::
 * forgotPassword / resetPassword).
 *
 * No CLI/artisan access on this deployment — since this can't actually
 * be run with `php artisan migrate`, apply the equivalent SQL manually
 * via phpMyAdmin on the `responders` table:
 *
 *   ALTER TABLE responders
 *     ADD COLUMN reset_otp VARCHAR(255) NULL AFTER verification_otp_expires_at,
 *     ADD COLUMN reset_otp_expires_at TIMESTAMP NULL AFTER reset_otp;
 *
 * This file is kept in the migrations folder anyway so the schema
 * history stays accurate/documented even though it was applied by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('responders', function (Blueprint $table) {
            $table->string('reset_otp')->nullable();
            $table->timestamp('reset_otp_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('responders', function (Blueprint $table) {
            $table->dropColumn(['reset_otp', 'reset_otp_expires_at']);
        });
    }
};