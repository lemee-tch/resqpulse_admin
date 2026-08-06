<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResponderRejected extends Mailable
{
    use Queueable, SerializesModels;

    public string $responderName;
    public string $reason;

    public function __construct(string $responderName, string $reason)
    {
        $this->responderName = $responderName;
        $this->reason = $reason;
    }

    public function build()
    {
        return $this->subject('ResQPulse Responder Application — Update')
            ->view('emails.responder-rejected');
    }
}