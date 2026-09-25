<?php

namespace App\Services\TwoFactor;

use App\Mail\LoginVerificationCodeMail;
use App\Models\User;
use App\Services\Otp\OtpCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailTwoFactorService
{
    public const CODE_TTL_SECONDS = 600;
    public const RESEND_COOLDOWN_SECONDS = 60;
    public const MAX_ATTEMPTS = 5;

    public const DEVICE_COOKIE_NAME = 'wdems_trusted_device';
    public const DEVICE_TTL_MINUTES = 60 * 24 * 30; // 30 days
    public const MAX_TRUSTED_DEVICES = 10;

    public function issue(User $user): int
    {
        if (Cache::has($this->resendKey($user))) {
            throw new \RuntimeException('Please wait before requesting another code.');
        }

        $code = OtpCode::generate();

        Cache::put(
            $this->otpKey($user),
            [
                'code_hash' => OtpCode::hash($code),
                'attempts' => 0,
                'expires_at' => now()->addSeconds(self::CODE_TTL_SECONDS)->timestamp,
            ],
            self::CODE_TTL_SECONDS,
        );

        Cache::put(
            $this->resendKey($user),
            now()->timestamp,
            self::RESEND_COOLDOWN_SECONDS,
        );

        Mail::to($user->email)->send(new LoginVerificationCodeMail(
            code: $code,
            expiresInMinutes: (int) (self::CODE_TTL_SECONDS / 60),
        ));

        return self::CODE_TTL_SECONDS;
    }

    public function verify(User $user, string $code): EmailTwoFactorResult
    {
        $payload = Cache::get($this->otpKey($user));

        if (! is_array($payload)) {
            return EmailTwoFactorResult::NotFound;
        }

        if (($payload['expires_at'] ?? 0) < now()->timestamp) {
            Cache::forget($this->otpKey($user));
            return EmailTwoFactorResult::Expired;
        }

        $attempts = (int) ($payload['attempts'] ?? 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::forget($this->otpKey($user));
            return EmailTwoFactorResult::Locked;
        }

        if (! OtpCode::matches($code, $payload['code_hash'])) {
            $attempts++;
            $remainingTtl = max(1, (int) ($payload['expires_at'] - now()->timestamp));

            if ($attempts >= self::MAX_ATTEMPTS) {
                Cache::forget($this->otpKey($user));
                return EmailTwoFactorResult::Locked;
            }

            Cache::put(
                $this->otpKey($user),
                array_merge($payload, ['attempts' => $attempts]),
                $remainingTtl,
            );

            return EmailTwoFactorResult::Invalid;
        }

        Cache::forget($this->otpKey($user));
        Cache::forget($this->resendKey($user));

        return EmailTwoFactorResult::Verified;
    }

    public function clear(User $user): void
    {
        Cache::forget($this->otpKey($user));
        Cache::forget($this->resendKey($user));
    }

    public function hasActiveCode(User $user): bool
    {
        return Cache::has($this->otpKey($user));
    }

    public function secondsUntilResend(User $user): int
    {
        $timestamp = Cache::get($this->resendKey($user));

        if (! $timestamp) {
            return 0;
        }

        $remaining = (int) $timestamp + self::RESEND_COOLDOWN_SECONDS - now()->timestamp;

        return max(0, $remaining);
    }

    /**
     * Generate a fresh device token, store its hash on the user, return
     * the plaintext token for the cookie.
     *
     * Re-trusting the same label+IP replaces the old entry rather than
     * adding a duplicate.
     */
    public function trustDevice(User $user, string $label, ?string $ip): string
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $devices = $user->trusted_devices ?? [];

        $devices = array_values(array_filter(
            $devices,
            fn ($d) => ! (
                ($d['label'] ?? null) === $label
                && ($d['ip'] ?? null) === $ip
            ),
        ));

        if (count($devices) >= self::MAX_TRUSTED_DEVICES) {
            $devices = array_slice($devices, -(self::MAX_TRUSTED_DEVICES - 1));
        }

        $devices[] = [
            'id' => (string) Str::uuid(),
            'token' => $tokenHash,
            'label' => $label,
            'ip' => $ip,
            'added_at' => now()->toIso8601String(),
        ];

        $user->forceFill(['trusted_devices' => $devices])->save();

        return $token;
    }

    public function revokeDeviceById(User $user, string $deviceId): void
    {
        $devices = collect($user->trusted_devices ?? [])
            ->reject(fn ($d) => ($d['id'] ?? null) === $deviceId)
            ->values()
            ->all();

        $user->forceFill(['trusted_devices' => $devices])->save();
    }

    public function revokeAllDevices(User $user): void
    {
        $user->forceFill(['trusted_devices' => []])->save();
    }

    public function hasTrustedDevice(User $user, string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $tokenHash = hash('sha256', $token);

        return collect($user->trusted_devices ?? [])
            ->contains(fn ($d) => ($d['token'] ?? null) === $tokenHash);
    }

    private function otpKey(User $user): string
    {
        return "email-2fa-otp:{$user->id}";
    }

    private function resendKey(User $user): string
    {
        return "email-2fa-resend:{$user->id}";
    }
}
