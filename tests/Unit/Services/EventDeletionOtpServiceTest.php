<?php

use App\Mail\EventDeletionOtpMail;
use App\Models\Event;
use App\Models\User;
use App\Services\EventDeletion\EventDeletionOtpService;
use App\Services\EventDeletion\OtpResendTooSoonException;
use App\Services\EventDeletion\OtpVerificationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Mail::fake();
});

function otp_ctx(): array
{
    $staff = User::factory()->create(['role' => 'staff']);
    $event = Event::create([
        'created_by' => $staff->id,
        'event_type' => 'community_run',
        'event_name' => 'OTP Test Event',
        'event_date' => now()->toDateString(),
        'status' => 'draft',
    ]);

    return [$staff, $event, app(EventDeletionOtpService::class)];
}

it('issues a code, returns TTL, and stores a hash', function () {
    [$user, $event, $service] = otp_ctx();

    $ttl = $service->issue($user, $event);

    expect($ttl)->toBe(600);
    Mail::assertSent(EventDeletionOtpMail::class);

    $payload = Cache::get("event-delete-otp:{$user->id}:{$event->id}");
    expect($payload)->toBeArray();
    expect($payload['code_hash'])->toBeString();
    expect($payload['attempts'])->toBe(0);
});

it('does not store the plaintext code in cache', function () {
    [$user, $event, $service] = otp_ctx();

    $service->issue($user, $event);

    $payload = Cache::get("event-delete-otp:{$user->id}:{$event->id}");
    $joined = implode('|', array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $payload));

    Mail::assertSent(EventDeletionOtpMail::class, function (EventDeletionOtpMail $mail) use ($joined) {
        expect(str_contains($joined, $mail->code))->toBeFalse();
        return true;
    });
});

it('refuses a resend within the cooldown', function () {
    [$user, $event, $service] = otp_ctx();
    $service->issue($user, $event);

    expect(fn () => $service->issue($user, $event))
        ->toThrow(OtpResendTooSoonException::class);
});

it('verifies the correct code and clears the OTP key', function () {
    [$user, $event, $service] = otp_ctx();

    $captured = null;
    $service->issue($user, $event);
    Mail::assertSent(EventDeletionOtpMail::class, function (EventDeletionOtpMail $mail) use (&$captured) {
        $captured = $mail->code;
        return true;
    });

    $result = $service->verify($user, $event, $captured);

    expect($result)->toBe(OtpVerificationResult::Verified);
    expect(Cache::has("event-delete-otp:{$user->id}:{$event->id}"))->toBeFalse();
});

it('rejects a wrong code and increments attempts', function () {
    [$user, $event, $service] = otp_ctx();
    $service->issue($user, $event);

    $result = $service->verify($user, $event, '000000');

    expect($result)->toBe(OtpVerificationResult::Invalid);
    $payload = Cache::get("event-delete-otp:{$user->id}:{$event->id}");
    expect($payload['attempts'])->toBe(1);
});

it('returns NotFound when there is no active OTP', function () {
    [$user, $event, $service] = otp_ctx();

    expect($service->verify($user, $event, '123456'))
        ->toBe(OtpVerificationResult::NotFound);
});

it('returns Expired when the payload has expired', function () {
    [$user, $event, $service] = otp_ctx();
    $service->issue($user, $event);

    $key = "event-delete-otp:{$user->id}:{$event->id}";
    $payload = Cache::get($key);
    $payload['expires_at'] = now()->subMinute()->timestamp;
    Cache::put($key, $payload, 600);

    $result = $service->verify($user, $event, '123456');

    expect($result)->toBe(OtpVerificationResult::Expired);
    expect(Cache::has($key))->toBeFalse();
});

it('locks after 5 wrong attempts', function () {
    [$user, $event, $service] = otp_ctx();
    $service->issue($user, $event);

    for ($i = 0; $i < 4; $i++) {
        expect($service->verify($user, $event, '000000'))
            ->toBe(OtpVerificationResult::Invalid);
    }

    expect($service->verify($user, $event, '000000'))
        ->toBe(OtpVerificationResult::Locked);

    expect(Cache::has("event-delete-otp:{$user->id}:{$event->id}"))->toBeFalse();
});

it('invalidates both keys', function () {
    [$user, $event, $service] = otp_ctx();
    $service->issue($user, $event);

    $service->invalidate($user, $event);

    expect(Cache::has("event-delete-otp:{$user->id}:{$event->id}"))->toBeFalse();
    expect(Cache::has("event-delete-otp-resend:{$user->id}:{$event->id}"))->toBeFalse();
});
