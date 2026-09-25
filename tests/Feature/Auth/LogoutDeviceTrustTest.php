<?php

use App\Models\User;
use App\Services\TwoFactor\EmailTwoFactorService;

it('does not forget the device trust cookie on logout', function () {
    $staff = User::factory()->create([
        'role' => 'staff',
        'email_two_factor_enabled' => true,
    ]);

    $response = $this->actingAs($staff)
        ->withCookie(EmailTwoFactorService::DEVICE_COOKIE_NAME, 'trusted-token-value')
        ->post(route('logout'));

    // Trust survives logout. The middleware's per-user device list is the
    // source of truth — a cookie left behind by a previous user is useless
    // to another user because hasTrustedDevice() checks the current user's
    // trusted_devices array. Revoking a device (Settings → Security) or
    // waiting 30 days are the intended ways to lose trust.
    $response->assertCookieMissing(EmailTwoFactorService::DEVICE_COOKIE_NAME);
});
