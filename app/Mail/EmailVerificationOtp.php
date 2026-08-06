<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerificationOtp extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public string $fullName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify your ResQPulse account',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.email-verification-otp',
        );
    }
}