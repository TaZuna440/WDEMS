<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email OTP for staff-initiated registration deletion.
 *
 * Same shape as EventDeletionOtpMail, with one extra field —
 * `participantName` — so the recipient can see whose registration
 * the code covers. The event name is also included for context.
 *
 * Never logged. Never stored. The code exists only in the email and
 * in the HMAC hash inside the cache payload.
 */
class RegistrationDeletionOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $participantName,
        public readonly string $eventName,
        public readonly int $expiresInMinutes = 10,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'WDEMS — Registration Deletion Verification Code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.registration-deletion-otp',
        );
    }
}
