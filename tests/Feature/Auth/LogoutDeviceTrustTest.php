<?php

use App\Models\User;
use App\Services\TwoFactor\EmailTwoFactorService;

it('forgets the device trust cookie on logout', function () {
    $staff = User::factory()->create([
        'role' => 'staff',
        'email_two_factor_enabled' => true,
    ]);

    $response = $this->actingAs($staff)
        ->withCookie(EmailTwoFactorService::DEVICE_COOKIE_NAME, 'stale-token-value')
        ->post(route('logout'));

    // Cookie::forget() emits a Set-Cookie with an empty value and past expiry.
    $response->assertCookieExpired(EmailTwoFactorService::DEVICE_COOKIE_NAME);
});
