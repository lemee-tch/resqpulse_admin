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
}