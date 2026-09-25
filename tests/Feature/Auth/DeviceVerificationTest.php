<?php

use App\Mail\LoginVerificationCodeMail;
use App\Models\User;
use App\Services\TwoFactor\EmailTwoFactorService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Cache::flush();
    Mail::fake();
});

function makeStaffUser(bool $twoFactorEnabled = true, bool $verified = true): User
{
    $user = User::factory()->create([
        'role' => 'staff',
        'email_two_factor_enabled' => $twoFactorEnabled,
    ]);

    if (! $verified) {
        $user->forceFill(['email_verified_at' => null])->save();
    }

    return $user;
}

function makeAdminUser(bool $twoFactorEnabled = true): User
{
    return User::factory()->create([
        'role' => 'admin',
        'email_two_factor_enabled' => $twoFactorEnabled,
    ]);
}

function deviceCaptureCode(): string
{
    $code = null;
    Mail::assertSent(LoginVerificationCodeMail::class, function (LoginVerificationCodeMail $mail) use (&$code) {
        $code = $mail->code;
        return true;
    });

    if ($code === null) {
        throw new RuntimeException('No OTP mail captured.');
    }

    return $code;
}

it('redirects untrusted staff to the verification page', function () {
    $staff = makeStaffUser();

    $response = $this->actingAs($staff)->get(route('dashboard'));

    $response->assertRedirect(route('device.verify'));
});

it('lets a trusted staff member through', function () {
    $staff = makeStaffUser();
    $service = app(EmailTwoFactorService::class);
    $token = $service->trustDevice($staff, 'Chrome', '127.0.0.1');

    $response = $this->actingAs($staff)
        ->withCookie(EmailTwoFactorService::DEVICE_COOKIE_NAME, $token)
        ->get(route('dashboard'));

    $response->assertOk();
});

it('lets staff with 2FA disabled through', function () {
    $staff = makeStaffUser(twoFactorEnabled: false);

    $response = $this->actingAs($staff)->get(route('dashboard'));

    $response->assertOk();
});

it('always lets admin through, even with 2FA enabled', function () {
    $admin = makeAdminUser(twoFactorEnabled: true);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();
});

it('shows the verification page without triggering a send when a code is active', function () {
    $staff = makeStaffUser();
    app(EmailTwoFactorService::class)->issue($staff);

    Mail::fake();

    $response = $this->actingAs($staff)->get(route('device.verify'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('auth/verify-device')
        ->where('autoSend', false)
    );
});

it('tells the frontend to auto-send when no code is active', function () {
    $staff = makeStaffUser();

    $response = $this->actingAs($staff)->get(route('device.verify'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('auth/verify-device')
        ->where('autoSend', true)
    );
});

it('sends a verification email when staff requests a code', function () {
    $staff = makeStaffUser();

    $response = $this->actingAs($staff)->post(route('device.verify.send'));

    $response->assertRedirect();
    Mail::assertSent(LoginVerificationCodeMail::class);
});

it('verifies a correct code, trusts the device, and redirects home', function () {
    $staff = makeStaffUser();
    $service = app(EmailTwoFactorService::class);
    $service->issue($staff);
    $code = deviceCaptureCode();

    $response = $this->actingAs($staff)->post(route('device.verify.confirm'), [
        'code' => $code,
        'remember' => true,
    ]);

    $response->assertRedirect($staff->homePath());
    $response->assertCookie(EmailTwoFactorService::DEVICE_COOKIE_NAME);
    expect($staff->fresh()->trusted_devices)->toHaveCount(1);
});

it('verifies the code but does not trust the device when remember is false', function () {
    $staff = makeStaffUser();
    $service = app(EmailTwoFactorService::class);
    $service->issue($staff);
    $code = deviceCaptureCode();

    $response = $this->actingAs($staff)->post(route('device.verify.confirm'), [
        'code' => $code,
        'remember' => false,
    ]);

    $response->assertRedirect($staff->homePath());
    expect($staff->fresh()->trusted_devices)->toBeNull();
});

it('rejects an invalid code', function () {
    $staff = makeStaffUser();
    app(EmailTwoFactorService::class)->issue($staff);

    $response = $this->actingAs($staff)->post(route('device.verify.confirm'), [
        'code' => '000000',
        'remember' => true,
    ]);

    $response->assertSessionHasErrors('code');
    expect($staff->fresh()->trusted_devices)->toBeNull();
});

it('rejects verification when no code was ever requested', function () {
    $staff = makeStaffUser();

    $response = $this->actingAs($staff)->post(route('device.verify.confirm'), [
        'code' => '123456',
        'remember' => true,
    ]);

    $response->assertSessionHasErrors('code');
});

it('rejects a code that is not 6 digits', function () {
    $staff = makeStaffUser();

    $response = $this->actingAs($staff)->post(route('device.verify.confirm'), [
        'code' => '12',
        'remember' => true,
    ]);

    $response->assertSessionHasErrors('code');
});

it('lets an already-trusted device skip the verification page', function () {
    $staff = makeStaffUser();
    $service = app(EmailTwoFactorService::class);
    $token = $service->trustDevice($staff, 'Chrome', '127.0.0.1');

    $response = $this->actingAs($staff)
        ->withCookie(EmailTwoFactorService::DEVICE_COOKIE_NAME, $token)
        ->get(route('device.verify'));

    $response->assertRedirect($staff->homePath());
});
