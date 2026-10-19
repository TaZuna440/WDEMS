<?php

use App\Http\Requests\PublicRegistrationRequest;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../../Support/PublicVerification.php';

/**
 * Birthdate swap coverage (2026-10-19).
 *
 * The public registration form now collects `birthday` (a bounded
 * date picker, age 3-120) instead of free-typed `age`. The server
 * accepts both — legacy challenges and direct tooling can still POST
 * `age`. These tests pin the acceptance rules, the rejection cases,
 * and the write precedence (birthday wins when both are present).
 *
 * Helper names are prefixed `birthdate_` to avoid collision with the
 * `public_*` helpers declared in RegistrationSubmissionTest.php. Both
 * files are loaded in the same process; a bare `public_event()` here
 * would redeclare and fatal.
 */

function birthdate_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

function birthdate_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => birthdate_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'Birthdate Test Run',
        'event_date' => now()->addWeek()->toDateString(),
        'status' => 'registration_open',
        'registration_slug' => 'brthdte2',
        'registration_start' => now(),
        'registration_form_saved_at' => now(),
    ], $overrides));
}

/**
 * Deliberately omits `age` and `birthday` — each test adds what it
 * needs. The "neither present" rejection test depends on both being
 * absent in the base payload.
 */
function birthdate_payload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria@example.com',
        'contact_number' => '+63 917 000 0000',
        'address' => 'Manila',
        'emergency_contact_name' => 'Jose Santos',
        'emergency_contact_relationship' => 'Father',
        'emergency_contact_number' => '+63 917 765 4321',
        'responses' => [],
    ], $overrides);
}

// =========================================================================
// Direct request validation — no HTTP, no verification.
// Asserts on the rules and the withValidator after-hook in isolation.
// =========================================================================

test('a valid birthday alone passes validation', function () {
    $event = birthdate_event();
    $payload = birthdate_payload(['birthday' => '1995-09-25']);

    $validated = PublicRegistrationRequest::validatePending($event, $payload);

    expect($validated)->toHaveKey('birthday');
    expect($validated['birthday'])->toBe('1995-09-25');
});

test('a future birthday is rejected', function () {
    $event = birthdate_event();
    $payload = birthdate_payload(['birthday' => now()->addYear()->toDateString()]);

    try {
        PublicRegistrationRequest::validatePending($event, $payload);
        expect(false)->toBeTrue('Expected ValidationException for future birthday');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('birthday');
    }
});

test('a birthday for someone under 3 years old is rejected', function () {
    $event = birthdate_event();
    $payload = birthdate_payload(['birthday' => now()->subYears(2)->toDateString()]);

    try {
        PublicRegistrationRequest::validatePending($event, $payload);
        expect(false)->toBeTrue('Expected ValidationException for under-3 birthday');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('birthday');
        expect($e->errors()['birthday'][0])->toContain('at least 3');
    }
});

test('a birthday for someone over 120 years old is rejected', function () {
    $event = birthdate_event();
    $payload = birthdate_payload(['birthday' => now()->subYears(121)->toDateString()]);

    try {
        PublicRegistrationRequest::validatePending($event, $payload);
        expect(false)->toBeTrue('Expected ValidationException for over-120 birthday');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('birthday');
    }
});

test('a submission with neither age nor birthday is rejected', function () {
    $event = birthdate_event();
    $payload = birthdate_payload();

    try {
        PublicRegistrationRequest::validatePending($event, $payload);
        expect(false)->toBeTrue('Expected ValidationException for missing demographics');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('birthday');
        expect($e->errors()['birthday'][0])->toContain('date of birth');
    }
});

test('a submission with both age and birthday passes validation', function () {
    $event = birthdate_event();
    $payload = birthdate_payload(['age' => 30, 'birthday' => '1995-09-25']);

    $validated = PublicRegistrationRequest::validatePending($event, $payload);

    expect($validated['age'])->toBe(30);
    expect($validated['birthday'])->toBe('1995-09-25');
});

test('a birthday exactly 3 years ago is accepted', function () {
    $event = birthdate_event();
    $exact = now()->subYears(3)->toDateString();
    $payload = birthdate_payload(['birthday' => $exact]);

    $validated = PublicRegistrationRequest::validatePending($event, $payload);

    expect($validated['birthday'])->toBe($exact);
});

test('a birthday exactly 120 years ago is accepted', function () {
    $event = birthdate_event();
    $exact = now()->subYears(120)->toDateString();
    $payload = birthdate_payload(['birthday' => $exact]);

    $validated = PublicRegistrationRequest::validatePending($event, $payload);

    expect($validated['birthday'])->toBe($exact);
});

// =========================================================================
// End-to-end via helpers — real submission, verify, finalize.
// =========================================================================

test('a birthday-only submission persists birthday and derives age', function () {
    $event = birthdate_event();
    $payload = birthdate_payload(['birthday' => '1995-09-25']);

    completePublicRegistration($this, $event, $payload)->assertSessionHasNoErrors();

    $participant = Participant::where('email', 'maria@example.com')->firstOrFail();

    expect($participant->birthday?->toDateString())->toBe('1995-09-25');
    expect($participant->getRawOriginal('age'))->toBeNull();

    // Accessor derives age from birthday. Pin the exact value so a
    // computation bug surfaces here rather than passing silently.
    $expected = now()->year - 1995 - (
        now()->month < 9 || (now()->month === 9 && now()->day < 25) ? 1 : 0
    );
    expect($participant->age)->toBe($expected);
});

test('when both age and birthday are submitted, birthday wins on write', function () {
    $event = birthdate_event();
    $payload = birthdate_payload(['age' => 50, 'birthday' => '1995-09-25']);

    completePublicRegistration($this, $event, $payload)->assertSessionHasNoErrors();

    $participant = Participant::where('email', 'maria@example.com')->firstOrFail();

    expect($participant->birthday?->toDateString())->toBe('1995-09-25');
    // Age column is not written when birthday is present — the
    // demographics write in finalize() picks one branch or the other,
    // never both. Writing both would leave a stale raw age on the row.
    expect($participant->getRawOriginal('age'))->toBeNull();
});

test('a legacy age-only submission still works and derives no birthday', function () {
    $event = birthdate_event();
    $payload = birthdate_payload(['age' => 28]);

    completePublicRegistration($this, $event, $payload)->assertSessionHasNoErrors();

    $participant = Participant::where('email', 'maria@example.com')->firstOrFail();

    expect($participant->birthday)->toBeNull();
    expect($participant->getRawOriginal('age'))->toBe(28);
    // Accessor falls back to the stored age column when birthday is null.
    expect($participant->age)->toBe(28);
});
