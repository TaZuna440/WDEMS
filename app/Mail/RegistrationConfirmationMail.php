<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Delivers the participant's attendance code by email at the moment
 * of registration finalization.
 *
 * The code is a bearer lookup key, not an OTP. It does not expire,
 * it cannot be regenerated, and it is not used for authentication.
 * The mailable is sent once, from finalize(), wrapped in
 * DB::afterCommit() so a transport failure never rolls back the
 * registration.
 *
 * Marked #[\SensitiveParameter] on the constructor for the same
 * reason the OTP mailable is: the code is participant-identifying
 * and should not appear in stack traces.
 */
class RegistrationConfirmationMail extends Mailable
{
    public function __construct(
        #[\SensitiveParameter] public readonly string $code,
        public readonly string $eventName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'WDEMS — Your '.$this->eventName.' attendance code',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.registration-confirmation');
    }
}
