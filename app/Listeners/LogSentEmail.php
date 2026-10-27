<?php

namespace App\Listeners;

use App\Services\Audit\AuditLogger;
use Illuminate\Mail\Events\MessageSent;

class LogSentEmail
{
    public function handle(MessageSent $event): void
    {
        $recipients = array_map(
            fn ($address): string => $address->getAddress(),
            $event->message->getTo(),
        );

        app(AuditLogger::class)->logSystem('email.sent', [
            'to' => $recipients,
            'subject' => $event->message->getSubject(),
        ]);
    }
}
