<?php

use App\Mail\RegistrationVerificationOtpMail;
use App\Models\EmailDeviceVerification;
use App\Services\RegistrationVerification\RegistrationVerificationService as Verification;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\post;

// Helpers verificationEvent(), verificationPayload(), startVerification()
// are defined in RegistrationVerificationTest.php.

beforeEach(function () {
    Mail::fake();
});

test('successful email otp records device trust', function () {
    $event = verificationEvent(true, false);
    [$challenge, $token, $url] = startVerification($this, $event);
    $this->withCookie(Verification::DEVICE_TRUST_COOKIE, 'device-aaa');

    $code = Mail::sent(RegistrationVerificationOtpMail::class)->last()->code;
    $this->post($url.'/email', ['code' => $code])->assertSessionHasNoErrors();

    expect(EmailDeviceVerification::where('email', 'maria@example.com')
        ->where('device_token_hash', hash('sha256', 'device-aaa'))
        ->exists())->toBeTrue();
});

test('trusted device skips the email code on a later registration', function () {
    $event = verificationEvent(true, false);
    [$challenge, $token, $url] = startVerification($this, $event);
    $this->withCookie(Verification::DEVICE_TRUST_COOKIE, 'device-bbb');

    $code = Mail::sent(RegistrationVerificationOtpMail::class)->last()->code;
    $this->post($url.'/email', ['code' => $code])->assertSessionHasNoErrors();

    // Second event, same email, same device cookie.
    $event2 = verificationEvent(true, false);
    Mail::fake();
    [$challenge2, $token2, $url2] = startVerification($this, $event2);

    // No code mailed; the challenge is already email-verified and
    // email is adopted as the chosen channel.
    expect(Mail::sent(RegistrationVerificationOtpMail::class))->toBeEmpty();
    $challenge2->refresh();
    expect($challenge2->email_verified_at)->not->toBeNull();
    expect($challenge2->chosen_channel)->toBe('email');
});

test('a different device still receives an email code', function () {
    $event = verificationEvent(true, false);
    [$challenge, $token, $url] = startVerification($this, $event);
    $this->withCookie(Verification::DEVICE_TRUST_COOKIE, 'device-ccc');

    $code = Mail::sent(RegistrationVerificationOtpMail::class)->last()->code;
    $this->post($url.'/email', ['code' => $code])->assertSessionHasNoErrors();

    // Same email, different device.
    $event2 = verificationEvent(true, false);
    $this->withCookie(Verification::DEVICE_TRUST_COOKIE, 'device-ddd');
    Mail::fake();
    [$challenge2, $token2, $url2] = startVerification($this, $event2);

    expect(Mail::sent(RegistrationVerificationOtpMail::class))->not->toBeEmpty();
    expect($challenge2->refresh()->email_verified_at)->toBeNull();
});

test('expired trust falls back to the email code', function () {
    $event = verificationEvent(true, false);
    [$challenge, $token, $url] = startVerification($this, $event);
    $this->withCookie(Verification::DEVICE_TRUST_COOKIE, 'device-eee');

    $code = Mail::sent(RegistrationVerificationOtpMail::class)->last()->code;
    $this->post($url.'/email', ['code' => $code])->assertSessionHasNoErrors();

    $this->travel(8)->days();

    $event2 = verificationEvent(true, false);
    Mail::fake();
    [$challenge2, $token2, $url2] = startVerification($this, $event2);

    expect(Mail::sent(RegistrationVerificationOtpMail::class))->not->toBeEmpty();
    expect($challenge2->refresh()->email_verified_at)->toBeNull();
});
