<?php

use App\Mail\LoginVerificationCodeMail;
use App\Models\User;
use App\Services\TwoFactor\EmailTwoFactorResult;
use App\Services\TwoFactor\EmailTwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Mail::fake();
});

function makeStaff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_two_factor_enabled' => true,
    ]);
}

function otpCaptureCode(): string
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

it('issues a code, sends mail, and returns the TTL', function () {
    $user = makeStaff();
    $service = app(EmailTwoFactorService::class);

    $ttl = $service->issue($user);

    expect($ttl)->toBe(EmailTwoFactorService::CODE_TTL_SECONDS);
    Mail::assertSent(LoginVerificationCodeMail::class);

    $payload = Cache::get("email-2fa-otp:{$user->id}");
    expect($payload)->toBeArray();
    expect($payload['code_hash'])->toBeString();
    expect($payload['attempts'])->toBe(0);
});

it('does not store the plaintext code in cache', function () {
    $user = makeStaff();
    $service = app(EmailTwoFactorService::class);
    $service->issue($user);

    $code = otpCaptureCode();
    $payload = Cache::get("email-2fa-otp:{$user->id}");

    expect($payload['code_hash'])->not->toBe($code);
    expect(str_contains((string) $payload['code_hash'], $code))->toBeFalse();
});

it('blocks a second issue within the cooldown window', function () {
    $user = makeStaff();
    $service = app(EmailTwoFactorService::class);
    $service->issue($user);

    expect(fn () => $service->issue($user))
        ->toThrow(RuntimeException::class, 'Please wait before requesting another code.');
});

it('returns Verified and clears cache for the correct code', function () {
    $user = makeStaff();
    $service = app(EmailTwoFactorService::class);
    $service->issue($user);
    $code = otpCaptureCode();

    $result = $service->verify($user, $code);

    expect($result)->toBe(EmailTwoFactorResult::Verified);
    expect(Cache::has("email-2fa-otp:{$user->id}"))->toBeFalse();
    expect(Cache::has("email-2fa-resend:{$user->id}"))->toBeFalse();
});

it('returns Invalid and increments attempts for a wrong code', function () {
    $user = makeStaff();
    $service = app(EmailTwoFactorService::class);
    $service->issue($user);

    $result = $service->verify($user, '000000');

    expect($result)->toBe(EmailTwoFactorResult::Invalid);
    $payload = Cache::get("email-2fa-otp:{$user->id}");
    expect($payload['attempts'])->toBe(1);
});

it('locks after five wrong attempts and clears the code', function () {
    $user = makeStaff();
    $service = app(EmailTwoFactorService::class);
    $service->issue($user);

    for ($i = 0; $i < 4; $i++) {
        expect($service->verify($user, '000000'))
            ->toBe(EmailTwoFactorResult::Invalid);
    }

    expect($service->verify($user, '000000'))
        ->toBe(EmailTwoFactorResult::Locked);

    expect(Cache::has("email-2fa-otp:{$user->id}"))->toBeFalse();
});

it('returns NotFound when no active code exists', function () {
    $user = makeStaff();
    $service = app(EmailTwoFactorService::class);

    expect($service->verify($user, '123456'))
        ->toBe(EmailTwoFactorResult::NotFound);
});

it('returns Expired when the payload is past its expiry', function () {
    $user = makeStaff();
    $service = app(EmailTwoFactorService::class);
    $service->issue($user);

    $key = "email-2fa-otp:{$user->id}";
    $payload = Cache::get($key);
    $payload['expires_at'] = now()->subMinute()->timestamp;
    Cache::put($key, $payload, 600);

    expect($service->verify($user, '123456'))
        ->toBe(EmailTwoFactorResult::Expired);
    expect(Cache::has($key))->toBeFalse();
});

it('reports active code state correctly', function () {
    $user = makeStaff();
    $service = app(EmailTwoFactorService::class);

    expect($service->hasActiveCode($user))->toBeFalse();

    $service->issue($user);
    expect($service->hasActiveCode($user))->toBeTrue();
});

it('reports resend countdown seconds', function () {
    $user = makeStaff();
    $service = app(EmailTwoFactorService::class);

    expect($service->secondsUntilResend($user))->toBe(0);

    $service->issue($user);
    $remaining = $service->secondsUntilResend($user);

    expect($remaining)->toBeGreaterThan(0);
    expect($remaining)->toBeLessThanOrEqual(EmailTwoFactorService::RESEND_COOLDOWN_SECONDS);
});

it('adds a trusted device entry to the user', function () {
    $user = makeStaff();
    $service = app(EmailTwoFactorService::class);

    $token = $service->trustDevice($user, 'Chrome', '203.0.113.42');

    expect($token)->toBeString()->toHaveLength(64);

    $devices = $user->fresh()->trusted_devices;
    expect($devices)->toHaveCount(1);
    expect($devices[0]['label'])->toBe('Chrome');
    expect($devices[0]['ip'])->toBe('203.0.113.42');
    expect($devices[0]['id'])->toBeString();
});

it('replaces an existing device with the same label and IP', function () {
    $user = makeStaff();
    $service = app(EmailTwoFactorService::class);

    $service->trustDevice($user, 'Chrome', '203.0.113.42');
    $service->trustDevice($user, 'Chrome', '203.0.113.42');

    expect($user->fresh()->trusted_devices)->toHaveCount(1);
});

it('matches a trusted device by the plaintext token', function () {
    $user = makeStaff();
    $service = app(EmailTwoFactorService::class);

    $token = $service->trustDevice($user, 'Chrome', '203.0.113.42');

    expect($service->hasTrustedDevice($user->fresh(), $token))->toBeTrue();
    expect($service->hasTrustedDevice($user->fresh(), 'a-different-token'))->toBeFalse();
    expect($service->hasTrustedDevice($user->fresh(), ''))->toBeFalse();
});

it('revokes a specific device by id', function () {
    $user = makeStaff();
    $service = app(EmailTwoFactorService::class);

    $service->trustDevice($user, 'Chrome', '203.0.113.1');
    $service->trustDevice($user, 'Safari', '203.0.113.2');

    $devices = $user->fresh()->trusted_devices;
    expect($devices)->toHaveCount(2);

    $service->revokeDeviceById($user->fresh(), $devices[0]['id']);

    $remaining = $user->fresh()->trusted_devices;
    expect($remaining)->toHaveCount(1);
    expect($remaining[0]['label'])->toBe('Safari');
});

it('revokes all devices', function () {
    $user = makeStaff();
    $service = app(EmailTwoFactorService::class);

    $service->trustDevice($user, 'Chrome', '203.0.113.1');
    $service->trustDevice($user, 'Safari', '203.0.113.2');
    $service->revokeAllDevices($user->fresh());

    expect($user->fresh()->trusted_devices)->toBe([]);
});

it('clears all cache keys for the user', function () {
    $user = makeStaff();
    $service = app(EmailTwoFactorService::class);
    $service->issue($user);

    $service->clear($user);

    expect(Cache::has("email-2fa-otp:{$user->id}"))->toBeFalse();
    expect(Cache::has("email-2fa-resend:{$user->id}"))->toBeFalse();
});
