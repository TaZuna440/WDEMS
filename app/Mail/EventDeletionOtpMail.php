<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventDeletionOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $eventName,
        public readonly int $expiresInMinutes = 10,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'WDEMS — Event Deletion Verification Code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.event-deletion-otp',
        );
    }
}
