<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the columns needed to actually verify a new login email before
 * it takes effect (see Admin\ResponderAccountController::sendEmailOtp /
 * update()). Deliberately separate from the existing reset_otp /
 * reset_otp_expires_at columns (added for the responder app's
 * self-service password reset, which was later removed as unused/dead
 * — see ResponderAuthController) rather than repurposing them: reusing
 * a "password reset" column for "email verification" would collide if
 * both were ever in flight for the same account at once, and the name
 * would be misleading either way.
 *
 * `pending_email` holds the NEW address until its code is confirmed —
 * the responder's actual `email` (their real login) never changes until
 * verification succeeds, so a wrong/undeliverable new address can't
 * accidentally lock the team out mid-edit.
 *
 * No CLI/artisan access on this deployment — apply via phpMyAdmin SQL:
 *
 *   ALTER TABLE responders
 *     ADD COLUMN pending_email VARCHAR(255) NULL AFTER email,
 *     ADD COLUMN email_verification_otp VARCHAR(255) NULL AFTER pending_email,
 *     ADD COLUMN email_verification_otp_expires_at TIMESTAMP NULL AFTER email_verification_otp;
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('responders', function (Blueprint $table) {
            $table->string('pending_email')->nullable()->after('email');
            $table->string('email_verification_otp')->nullable()->after('pending_email');
            $table->timestamp('email_verification_otp_expires_at')->nullable()->after('email_verification_otp');
        });
    }

    public function down(): void
    {
        Schema::table('responders', function (Blueprint $table) {
            $table->dropColumn(['pending_email', 'email_verification_otp', 'email_verification_otp_expires_at']);
        });
    }
};