<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactor\EmailTwoFactorResult;
use App\Services\TwoFactor\EmailTwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class DeviceVerificationController extends Controller
{
    public function __construct(
        private readonly EmailTwoFactorService $service,
    ) {
    }

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // Already trusted — nothing to do here
        $token = $request->cookie(EmailTwoFactorService::DEVICE_COOKIE_NAME);
        if (is_string($token) && $token !== '' && $this->service->hasTrustedDevice($user, $token)) {
            return redirect()->intended($user->homePath());
        }

        return Inertia::render('auth/verify-device', [
            'email' => $this->maskEmail($user->email),
            'autoSend' => ! $this->service->hasActiveCode($user),
            'initialCountdown' => $this->service->secondsUntilResend($user),
        ]);
    }

    public function sendCode(Request $request): RedirectResponse
    {
        try {
            $this->service->issue($request->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('device.verification.send_failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'code' => 'We could not send the code. Please try again in a moment.',
            ]);
        }

        return back()->with('status', 'code-sent');
    }

    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
            'remember' => ['boolean'],
        ]);

        $user = $request->user();
        $result = $this->service->verify($user, $validated['code']);

        return match ($result) {
            EmailTwoFactorResult::Verified => $this->handleVerified($request),
            EmailTwoFactorResult::Invalid => back()->withErrors([
                'code' => 'The verification code is incorrect.',
            ]),
            EmailTwoFactorResult::Expired => back()->withErrors([
                'code' => 'The code has expired. Please request a new one.',
            ]),
            EmailTwoFactorResult::Locked => back()->withErrors([
                'code' => 'Too many incorrect attempts. Please request a new code later.',
            ]),
            EmailTwoFactorResult::NotFound => back()->withErrors([
                'code' => 'No active code found. Please request a new one.',
            ]),
            EmailTwoFactorResult::TooSoon => back()->withErrors([
                'code' => 'Please wait before requesting another code.',
            ]),
        };
    }

    private function handleVerified(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Default is to remember the device (checkbox pre-checked on the frontend)
        $remember = $request->boolean('remember', true);

        if ($remember) {
            $token = $this->service->trustDevice(
                $user,
                $this->deviceLabel($request),
                $request->ip(),
            );

            Cookie::queue(
                EmailTwoFactorService::DEVICE_COOKIE_NAME,
                $token,
                EmailTwoFactorService::DEVICE_TTL_MINUTES,
                null,
                null,
                $request->secure(),
                true,
                false,
                'lax',
            );
        }

        return redirect()->intended($user->homePath());
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return $email;
        }

        [$name, $domain] = $parts;
        $visible = substr($name, 0, 1);

        return $visible.str_repeat('*', max(1, strlen($name) - 1)).'@'.$domain;
    }

    private function deviceLabel(Request $request): string
    {
        $ua = (string) $request->userAgent();

        return match (true) {
            str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'Edg') => 'Edge',
            str_contains($ua, 'Chrome') => 'Chrome',
            str_contains($ua, 'Safari') => 'Safari',
            default => 'Unknown browser',
        };
    }
}
