<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\RegistrationVerificationChallenge as Challenge;
use App\Models\User;
use App\Services\RegistrationVerification\RegistrationVerificationService as Verification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Tests\TestCase;

uses(RefreshDatabase::class);

require_once __DIR__.'/../../Support/PublicVerification.php';

/**
 * Covers the Firebase-mediated phone verification path in
 * RegistrationVerificationService::verifyPhoneWithFirebase().
 *
 * The Firebase Admin SDK is stubbed: verifyIdToken() returns a canned
 * token whose `phone_number` claim we control. The test exercises the
 * security-critical check — the verified phone must match the phone
 * the participant submitted — without touching real Firebase.
 */

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

function fb_event(): Event
{
    return Event::create([
        'created_by' => User::factory()->create(['role' => 'staff', 'email_two_factor_enabled' => false])->id,
        'event_type' => 'community_run',
        'event_name' => 'Firebase Test Run',
        'event_date' => now()->addWeek(),
        'status' => 'registration_open',
        'registration_slug' => bin2hex(random_bytes(4)),
        'registration_start' => now(),
        'registration_form_saved_at' => now(),
        'registration_common_field_requirements' => ['email' => false, 'contact_number' => true],
    ]);
}

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function fb_payload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'age' => 28,
        'address' => 'Manila',
        'contact_number' => '09171234567',
        'responses' => [],
    ], $overrides);
}

/**
 * @return array{0: string, 1: Challenge}
 */
function fb_challenge(Event $event, string $submittedPhone = '09171234567'): array
{
    $result = app(Verification::class)->begin(
        $event,
        fb_payload(['contact_number' => $submittedPhone]),
        null,
    );

    return [$result['token'], Challenge::latest('id')->firstOrFail()];
}

// ---------------------------------------------------------------------------
// Firebase SDK stubs
// ---------------------------------------------------------------------------

/**
 * A stub for Kreait\Firebase\Contract\Auth that returns a token whose
 * phone_number claim is the given value. Pass null for a token with no
 * phone_number claim at all.
 */
function fb_stub(?string $verifiedPhone): FirebaseAuth
{
    $claims = new \Lcobucci\JWT\Token\DataSet(
        $verifiedPhone === null ? [] : ['phone_number' => $verifiedPhone],
        '',
    );

    $token = \Mockery::mock(\Lcobucci\JWT\UnencryptedToken::class);
    $token->shouldReceive('claims')->andReturn($claims);

    $auth = \Mockery::mock(FirebaseAuth::class);
    $auth->shouldReceive('verifyIdToken')->andReturn($token);

    return $auth;
}

function fb_stub_failing(): FirebaseAuth
{
    $auth = \Mockery::mock(FirebaseAuth::class);
    $auth->shouldReceive('verifyIdToken')->andThrow(new \RuntimeException('simulated failure'));

    return $auth;
}

// ---------------------------------------------------------------------------
// Happy path — verified phone matches the submitted phone
// ---------------------------------------------------------------------------

test('a valid token with a matching phone verifies the challenge', function () {
    $event = fb_event();
    [$token, $challenge] = fb_challenge($event, '09171234567');

    $this->app->instance(FirebaseAuth::class, fb_stub('+639171234567'));

    $error = app(Verification::class)->verifyPhoneWithFirebase($event, $token, 'id-token-xyz');

    expect($error)->toBeNull();
    expect($challenge->refresh()->phone_verified_at)->not->toBeNull();
});

test('a valid token with a phone matching across formats verifies', function () {
    $event = fb_event();
    [$token, $challenge] = fb_challenge($event, '639171234567');

    // Firebase always returns E.164. Submitted was without the +
    $this->app->instance(FirebaseAuth::class, fb_stub('+639171234567'));

    $error = app(Verification::class)->verifyPhoneWithFirebase($event, $token, 'id-token-xyz');

    expect($error)->toBeNull();
    expect($challenge->refresh()->phone_verified_at)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Mismatch — the token is valid for a different phone
// ---------------------------------------------------------------------------

test('a valid token whose phone does not match the submitted phone is rejected', function () {
    $event = fb_event();
    [$token, $challenge] = fb_challenge($event, '09171234567');

    $this->app->instance(FirebaseAuth::class, fb_stub('+639179999999'));

    $error = app(Verification::class)->verifyPhoneWithFirebase($event, $token, 'id-token-xyz');

    expect($error)->toBe('The verified phone number does not match the one you submitted.');
    expect($challenge->refresh()->phone_verified_at)->toBeNull();
    expect($challenge->phone_attempts)->toBe(1);
});

test('a token with no phone_number claim is rejected', function () {
    $event = fb_event();
    [$token, $challenge] = fb_challenge($event);

    $this->app->instance(FirebaseAuth::class, fb_stub(null));

    $error = app(Verification::class)->verifyPhoneWithFirebase($event, $token, 'id-token-xyz');

    expect($error)->toBe('The verified phone number is missing from the token.');
    expect($challenge->refresh()->phone_verified_at)->toBeNull();
    expect($challenge->phone_attempts)->toBe(1);
});

// ---------------------------------------------------------------------------
// Invalid token
// ---------------------------------------------------------------------------

test('an invalid token increments attempts and is rejected', function () {
    $event = fb_event();
    [$token, $challenge] = fb_challenge($event);

    $this->app->instance(FirebaseAuth::class, fb_stub_failing());

    $error = app(Verification::class)->verifyPhoneWithFirebase($event, $token, 'bad-token');

    expect($error)->toBe('We could not verify that token. Please try again.');
    expect($challenge->refresh()->phone_verified_at)->toBeNull();
    expect($challenge->phone_attempts)->toBe(1);
});

// ---------------------------------------------------------------------------
// Guard: phone already verified
// ---------------------------------------------------------------------------

test('a phone already verified cannot be verified again', function () {
    $event = fb_event();
    [$token, $challenge] = fb_challenge($event);

    $challenge->forceFill(['phone_verified_at' => now()])->save();

    $this->app->instance(FirebaseAuth::class, fb_stub('+639171234567'));

    $error = app(Verification::class)->verifyPhoneWithFirebase($event, $token, 'id-token-xyz');

    expect($error)->toBe('This contact is already verified.');
});
