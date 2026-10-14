<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sign-up codes by text message (SMS gateway, App\Services\SmsService).
 *
 *   mobile_verified_at    when the resident entered a code that was texted
 *                         to their mobile number. email_verified_at is the
 *                         same thing for a code sent by email. Either one
 *                         means the sign-up is finished.
 *   verification_otp_via  where the current code went: 'email' or 'sms'.
 *
 * Runs by itself on deploy (php artisan migrate --force). The same change
 * by hand, in phpMyAdmin → SQL:
 *
 *   ALTER TABLE citizens
 *     ADD COLUMN mobile_verified_at TIMESTAMP NULL AFTER email_verified_at,
 *     ADD COLUMN verification_otp_via VARCHAR(10) NULL AFTER verification_otp_expires_at;
 *
 * If the columns were already added by hand, this skips them instead of
 * failing (a failed migration would stop the server from starting).
 */
return new class extends Migration
{
    public function up(): void
    {
        $addMobileVerifiedAt = ! Schema::hasColumn('citizens', 'mobile_verified_at');
        $addOtpVia           = ! Schema::hasColumn('citizens', 'verification_otp_via');

        if (! $addMobileVerifiedAt && ! $addOtpVia) {
            return;
        }

        Schema::table('citizens', function (Blueprint $table) use ($addMobileVerifiedAt, $addOtpVia) {
            if ($addMobileVerifiedAt) {
                $table->timestamp('mobile_verified_at')->nullable()->after('email_verified_at');
            }
            if ($addOtpVia) {
                $table->string('verification_otp_via', 10)->nullable()->after('verification_otp_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('citizens', function (Blueprint $table) {
            $table->dropColumn(['mobile_verified_at', 'verification_otp_via']);
        });
    }
};