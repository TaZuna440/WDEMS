<?php

namespace App\Services\EventDeletion;

use App\Mail\RegistrationDeletionOtpMail;
use App\Models\Registration;
use App\Models\User;
use App\Services\Otp\OtpCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Email OTP for staff registration deletion.
 *
 * Fork of EventDeletionOtpService, keyed on registration_id instead
 * of event_id. Same constants, same storage shape, same flow. Uses
 * OtpCode for generate/hash/match — the shared helper that the event
 * version duplicates inline.
 *
 * Storage:
 *   key:   registration-delete-otp:{user_id}:{registration_id}
 *   value: { code_hash, attempts, issued_at, expires_at }
 *   ttl:   10 minutes
 *
 *   key:   registration-delete-otp-resend:{user_id}:{registration_id}
 *   value: unix timestamp
 *   ttl:   60 seconds
 *
 * Codes are never stored in plaintext — only HMAC-SHA256 under
 * APP_KEY. Codes are never logged.
 */
class RegistrationDeletionOtpService
{
    public const CODE_TTL_SECONDS = 600;       // 10 minutes
    public const RESEND_COOLDOWN_SECONDS = 60; // 1 minute
    public const MAX_ATTEMPTS = 5;

    /**
     * Generate, store, and email a fresh OTP for the given user/
     * registration pair.
     *
     * Returns the TTL in seconds.
     *
     * @throws OtpResendTooSoonException
     */
    public function issue(User $user, Registration $registration): int
    {
        if (Cache::has($this->resendKey($user, $registration))) {
            throw new OtpResendTooSoonException();
        }

        $code = OtpCode::generate();

        Cache::put(
            $this->otpKey($user, $registration),
            [
                'code_hash' => OtpCode::hash($code),
                'attempts' => 0,
                'issued_at' => now()->timestamp,
                'expires_at' => now()->addSeconds(self::CODE_TTL_SECONDS)->timestamp,
            ],
            self::CODE_TTL_SECONDS,
        );

        Cache::put(
            $this->resendKey($user, $registration),
            now()->timestamp,
            self::RESEND_COOLDOWN_SECONDS,
        );

        Mail::to($user->email)->send(
            new RegistrationDeletionOtpMail(
                code: $code,
                participantName: $registration->participant?->fullName() ?? 'the participant',
                eventName: $registration->event?->event_name ?? 'the event',
                expiresInMinutes: (int) (self::CODE_TTL_SECONDS / 60),
            )
        );

        return self::CODE_TTL_SECONDS;
    }

    public function verify(User $user, Registration $registration, string $code): OtpVerificationResult
    {
        $payload = Cache::get($this->otpKey($user, $registration));

        if (! is_array($payload)) {
            return OtpVerificationResult::NotFound;
        }

        if (($payload['expires_at'] ?? 0) < now()->timestamp) {
            $this->forgetOtp($user, $registration);
            return OtpVerificationResult::Expired;
        }

        $attempts = (int) ($payload['attempts'] ?? 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->forgetOtp($user, $registration);
            return OtpVerificationResult::Locked;
        }

        if (! OtpCode::matches($code, (string) ($payload['code_hash'] ?? ''))) {
            $attempts++;
            $remainingTtl = max(1, (int) ($payload['expires_at'] - now()->timestamp));

            if ($attempts >= self::MAX_ATTEMPTS) {
                $this->forgetOtp($user, $registration);
                return OtpVerificationResult::Locked;
            }

            Cache::put(
                $this->otpKey($user, $registration),
                array_merge($payload, ['attempts' => $attempts]),
                $remainingTtl,
            );

            return OtpVerificationResult::Invalid;
        }

        // Success — single use. Clear OTP and resend cooldown.
        $this->forgetOtp($user, $registration);
        Cache::forget($this->resendKey($user, $registration));

        return OtpVerificationResult::Verified;
    }

    /**
     * Forget the OTP and the resend cooldown. Called after a failed
     * mail send so the user is not locked out by an OTP that was
     * never delivered.
     */
    public function invalidate(User $user, Registration $registration): void
    {
        $this->forgetOtp($user, $registration);
        Cache::forget($this->resendKey($user, $registration));
    }

    private function otpKey(User $user, Registration $registration): string
    {
        return "registration-delete-otp:{$user->id}:{$registration->id}";
    }

    private function resendKey(User $user, Registration $registration): string
    {
        return "registration-delete-otp-resend:{$user->id}:{$registration->id}";
    }

    private function forgetOtp(User $user, Registration $registration): void
    {
        Cache::forget($this->otpKey($user, $registration));
    }
}
