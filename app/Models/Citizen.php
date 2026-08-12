<?php

namespace App\Models;

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
        'verification_otp', 'verification_otp_expires_at',
        'email_verified_at','fcm_token',
    ];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}