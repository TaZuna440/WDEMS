<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class RegistrationVerificationOtpMail extends Mailable
{
    public function __construct(
        #[\SensitiveParameter] public readonly string $code,
        public readonly string $eventName,
        public readonly int $expiresInMinutes = 10,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'WDEMS — Verify your registration email');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.registration-verification-otp');
    }
}
