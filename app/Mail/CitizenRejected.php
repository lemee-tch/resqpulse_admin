<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CitizenRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $fullName, public string $reason) {}

    public function build()
    {
        return $this->subject('ResQPulse ID Verification Update')
            ->view('emails.citizen-rejected');
    }
}