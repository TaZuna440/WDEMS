<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Services\TwoFactor\EmailTwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $user = $request->user();

        $devices = collect($user->trusted_devices ?? [])
            ->filter(fn ($d) => isset($d['id']))
            ->map(fn ($d) => [
                'id' => $d['id'],
                'label' => $d['label'] ?? 'Unknown browser',
                'ip' => $d['ip'] ?? null,
                'added_at' => $d['added_at'] ?? null,
            ])
            ->values()
            ->all();

        return Inertia::render('settings/security', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'emailTwoFactorEnabled' => (bool) $user->email_two_factor_enabled,
            'trustedDevices' => $devices,
        ]);
    }

    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }

    public function toggleTwoFactor(TwoFactorAuthenticationRequest $request): RedirectResponse
    {
        $user = $request->user();
        $enabling = ! $user->email_two_factor_enabled;

        $user->forceFill(['email_two_factor_enabled' => $enabling])->save();

        // When turning OFF, clear all trusted devices — no reason to keep them
        if (! $enabling) {
            app(EmailTwoFactorService::class)->revokeAllDevices($user);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $enabling
                ? __('Email 2FA enabled. You will be challenged on new devices.')
                : __('Email 2FA disabled.'),
        ]);

        return back();
    }

    public function revokeDevice(TwoFactorAuthenticationRequest $request, string $deviceId): RedirectResponse
    {
        app(EmailTwoFactorService::class)
            ->revokeDeviceById($request->user(), $deviceId);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Device revoked.')]);

        return back();
    }
}
