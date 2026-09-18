<?php

namespace App\Services\EventDeletion;

use App\Mail\EventDeletionOtpMail;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class EventDeletionOtpService
{
    public const CODE_TTL_SECONDS = 600;       // 10 minutes
    public const RESEND_COOLDOWN_SECONDS = 60; // 1 minute
    public const MAX_ATTEMPTS = 5;

    /**
     * Generate, store, and email a fresh OTP for the given user/event pair.
     *
     * Returns the TTL in seconds.
     *
     * @throws OtpResendTooSoonException
     */
    public function issue(User $user, Event $event): int
    {
        if (Cache::has($this->resendKey($user, $event))) {
            throw new OtpResendTooSoonException();
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put(
            $this->otpKey($user, $event),
            [
                'code_hash' => $this->hash($code),
                'attempts' => 0,
                'issued_at' => now()->timestamp,
                'expires_at' => now()->addSeconds(self::CODE_TTL_SECONDS)->timestamp,
            ],
            self::CODE_TTL_SECONDS,
        );

        Cache::put(
            $this->resendKey($user, $event),
            now()->timestamp,
            self::RESEND_COOLDOWN_SECONDS,
        );

        Mail::to($user->email)->send(
            new EventDeletionOtpMail(
                code: $code,
                eventName: $event->event_name,
                expiresInMinutes: (int) (self::CODE_TTL_SECONDS / 60),
            )
        );

        return self::CODE_TTL_SECONDS;
    }

    public function verify(User $user, Event $event, string $code): OtpVerificationResult
    {
        $payload = Cache::get($this->otpKey($user, $event));

        if (! is_array($payload)) {
            return OtpVerificationResult::NotFound;
        }

        if (($payload['expires_at'] ?? 0) < now()->timestamp) {
            $this->forgetOtp($user, $event);
            return OtpVerificationResult::Expired;
        }

        $attempts = (int) ($payload['attempts'] ?? 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->forgetOtp($user, $event);
            return OtpVerificationResult::Locked;
        }

        if (! hash_equals($payload['code_hash'], $this->hash($code))) {
            $attempts++;
            $remainingTtl = max(1, (int) ($payload['expires_at'] - now()->timestamp));

            if ($attempts >= self::MAX_ATTEMPTS) {
                $this->forgetOtp($user, $event);
                return OtpVerificationResult::Locked;
            }

            Cache::put(
                $this->otpKey($user, $event),
                array_merge($payload, ['attempts' => $attempts]),
                $remainingTtl,
            );

            return OtpVerificationResult::Invalid;
        }

        // Success — single use. Clear OTP and resend cooldown.
        $this->forgetOtp($user, $event);
        Cache::forget($this->resendKey($user, $event));

        return OtpVerificationResult::Verified;
    }

    public function invalidate(User $user, Event $event): void
    {
        $this->forgetOtp($user, $event);
        Cache::forget($this->resendKey($user, $event));
    }

    private function otpKey(User $user, Event $event): string
    {
        return "event-delete-otp:{$user->id}:{$event->id}";
    }

    private function resendKey(User $user, Event $event): string
    {
        return "event-delete-otp-resend:{$user->id}:{$event->id}";
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function forgetOtp(User $user, Event $event): void
    {
        Cache::forget($this->otpKey($user, $event));
    }
}
