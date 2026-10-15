<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The code for changing the email or mobile number on a resident's
 * account (Api\ContactChangeController).
 *
 *   $kind 'email'   sent to the NEW email, to confirm it.
 *   $kind 'mobile'  sent to the account's email when the code for a new
 *                   mobile number ($newNumber) couldn't be texted.
 */
class ContactChangeOtp extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public string $fullName,
        public string $kind = 'email',
        public ?string $newNumber = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->kind === 'mobile'
                ? 'Confirm your new ResQPulse mobile number'
                : 'Confirm your new ResQPulse email',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contact-change-otp',
        );
    }
}
