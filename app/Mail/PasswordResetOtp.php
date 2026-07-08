<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetOtp extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $otp, public string $fullName) {}

    public function build()
    {
        return $this->subject('Your ResQPulse Password Reset Code')
            ->view('emails.password-reset-otp');
    }
}