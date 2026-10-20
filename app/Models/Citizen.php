<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Citizen extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'full_name', 'first_name', 'middle_name', 'last_name', 'suffix',
        'mobile', 'email', 'password', 'google_id', 'avatar_url',
        'municipality', 'barangay', 'street', 'zone', 'valid_id_path',
        'verification_status', 'rejection_reason', 'verified_at',
        'reset_otp', 'reset_otp_expires_at',
        'verification_otp', 'verification_otp_expires_at', 'verification_otp_via',
        'email_verified_at', 'mobile_verified_at', 'fcm_token',
    ];

    // Never sent to the apps. The sign-up and password-reset codes used to
    // be included whenever a citizen was returned as JSON — in the sign-up
    // reply itself (so the code could be read without the email or text),
    // and to the responder app inside every incident. Admin pages still
    // read these normally.
    protected $hidden = [
        'password', 'remember_token',
        'verification_otp', 'verification_otp_expires_at', 'verification_otp_via',
        'reset_otp', 'reset_otp_expires_at',
        'valid_id_path',
        // The phone's push token and the Google account id went to the
        // responders app with every incident the resident reported (item 47).
        'fcm_token', 'google_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'mobile_verified_at' => 'datetime',
            'verified_at'        => 'datetime',
            'password'           => 'hashed',
        ];
    }

    /**
     * Sign-up finished: the resident entered their code, whether it came
     * by email (email_verified_at) or by text (mobile_verified_at).
     * Not the same as verification_status, which is the admin's ID check.
     */
    public function scopeContactVerified(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNotNull('email_verified_at')->orWhereNotNull('mobile_verified_at');
        });
    }

    /** Sign-up started but the code was never entered. */
    public function scopeContactUnverified(Builder $query): Builder
    {
        return $query->whereNull('email_verified_at')->whereNull('mobile_verified_at');
    }

    public function hasVerifiedContact(): bool
    {
        return $this->email_verified_at !== null || $this->mobile_verified_at !== null;
    }

    /**
     * Signed up with "Mobile Number": no email — the mobile number is how
     * they log in, get codes and reset their password. Residents who signed
     * up with Email log in with the email; their mobile number is there so
     * MDRRMO can call them.
     */
    public function isMobileAccount(): bool
    {
        return trim((string) $this->email) === '';
    }

    /**
     * Matches a mobile number however it was typed — 09171234567,
     * +63 917 123 4567, 639171234567 — against how it was saved.
     * Matches nothing for something that isn't a PH mobile number.
     */
    public function scopeWhereMobileNumber(Builder $query, ?string $raw): Builder
    {
        $number = \App\Services\SmsService::normalizeNumber($raw); // 639XXXXXXXXX

        if ($number === null) {
            return $query->whereRaw('1 = 0');
        }

        $local = substr($number, 2); // 9XXXXXXXXX

        return $query->whereIn('mobile', ['0' . $local, $number, '+' . $number, $local]);
    }

    /** The account for what was typed in "Email or Mobile Number". */
    public static function findByLogin(?string $login): ?self
    {
        $login = trim((string) $login);

        if ($login === '') {
            return null;
        }

        return str_contains($login, '@')
            ? static::where('email', $login)->first()
            : static::whereMobileNumber($login)->first();
    }
}