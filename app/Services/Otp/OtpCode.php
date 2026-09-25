<?php

namespace App\Services\Otp;

final class OtpCode
{
    /**
     * Generate a cryptographically secure 6-digit code.
     */
    public static function generate(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Hash a code using the app key — never store plaintext.
     */
    public static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    /**
     * Constant-time comparison between a user-supplied code and a stored hash.
     */
    public static function matches(string $input, string $hash): bool
    {
        return hash_equals($hash, self::hash($input));
    }
}
