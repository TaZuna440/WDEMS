<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Mail\RegistrationConfirmationMail;
use App\Models\Registration;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RegistrationConfirmationService
{
    public function sendAfterCommit(Registration $registration): void
    {
        if ($registration->registration_status !== RegistrationStatus::Confirmed
            || $registration->attendance_code === null
            || $registration->confirmation_email_sent_at !== null) {
            return;
        }

        $email = $registration->submitted_email ?? $registration->participant?->email;
        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $registrationId = $registration->id;
        $attendanceCode = $registration->attendance_code;
        $eventName = $registration->event->event_name;

        DB::afterCommit(function () use ($registrationId, $email, $attendanceCode, $eventName): void {
            $row = Registration::query()->find($registrationId);
            if ($row === null
                || $row->registration_status !== RegistrationStatus::Confirmed
                || $row->confirmation_email_sent_at !== null) {
                return;
            }

            try {
                Mail::to($email)->send(new RegistrationConfirmationMail($attendanceCode, $eventName));
            } catch (\Throwable $exception) {
                Log::error('Registration confirmation email delivery failed.', [
                    'registration_id' => $registrationId,
                    'exception' => $exception::class,
                ]);
                app(AuditLogger::class)->logSystem('email.failed', [
                    'to' => $email,
                    'error' => $exception->getMessage(),
                ]);

                return;
            }

            $row->forceFill(['confirmation_email_sent_at' => now()])->saveQuietly();
        });
    }
}
