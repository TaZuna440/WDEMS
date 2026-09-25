<?php

namespace App\Http\Middleware;

use App\Services\TwoFactor\EmailTwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeviceIsTrusted
{
    public function __construct(
        private readonly EmailTwoFactorService $service,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // No user — let auth middleware handle it
        if ($user === null) {
            return $next($request);
        }

        // Admins are exempt — they own the system and control the server.
        // This is a deliberate scope decision, not an oversight.
        if ($user->isAdmin()) {
            return $next($request);
        }

        // 2FA is off for this user — no challenge needed
        if (! $user->email_two_factor_enabled) {
            return $next($request);
        }

        $token = $request->cookie(EmailTwoFactorService::DEVICE_COOKIE_NAME);

        if (is_string($token) && $token !== '' && $this->service->hasTrustedDevice($user, $token)) {
            return $next($request);
        }

        // Cookie exists but doesn't match a trusted device — clean it up
        if (is_string($token) && $token !== '') {
            Cookie::queue(Cookie::forget(EmailTwoFactorService::DEVICE_COOKIE_NAME));
        }

        return redirect()->guest(route('device.verify'));
    }
}
