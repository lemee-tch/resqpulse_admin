<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Responder extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'full_name', 'first_name', 'middle_name', 'last_name', 'suffix',
        'badge_number', 'agency', 'unit_station',
        'mobile', 'email', 'password', 'avatar_url', 'fcm_token', 'status',
        'valid_id_path', 'verification_status', 'rejection_reason', 'verified_at',
        'reset_otp', 'reset_otp_expires_at',
        'pending_email', 'email_verification_otp', 'email_verification_otp_expires_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at'                 => 'datetime',
            'verified_at'                        => 'datetime',
            'reset_otp_expires_at'               => 'datetime',
            'email_verification_otp_expires_at'  => 'datetime',
            'password'                            => 'hashed',
        ];
    }
}