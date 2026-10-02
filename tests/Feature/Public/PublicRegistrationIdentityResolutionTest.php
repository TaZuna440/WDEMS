<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;

/**
 * Covers the Phase 5 controller rewrite: PublicRegistrationController
 * ::store() now resolves participants through Participant::resolveFrom
 * instead of the pre-identity firstOrCreate/create branches.
 *
 * Key behavioral change: a second submission sharing an identity key
 * with an earlier registration on the same event is now rejected
 * with a field-anchored validation error, not a 500. Closes
 * ISSUE-009.
 *
 * Helpers prefixed resolution_ to avoid Pest function collisions with
 * sibling test files.
 */

function resolution_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

/**
 * @param array<string, mixed> $overrides
 */
function resolution_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => resolution_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'Resolution Test Run',
        'event_date' => now()->addWeek()->toDateString(),
        'status' => 'registration_open',
        'registration_slug' => 'resolvet',
        'registration_start' => now(),
        'registration_form_saved_at' => now(),
        'registration_common_field_requirements' => [
            'email' => false,
            'contact_number' => true,
        ],
    ], $overrides));
}

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function resolution_payload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria@example.com',
        'contact_number' => '09171234567',
        'age' => 28,
        'address' => 'Manila',
        'responses' => [],
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Reuse across events
// ---------------------------------------------------------------------------

test('an existing participant is reused across events', function () {
    $eventA = resolution_event(['registration_slug' => 'resolvaa']);
    $eventB = resolution_event(['registration_slug' => 'resolvbb']);

    $this->post("/r/{$eventA->registration_slug}", resolution_payload());
    $this->post("/r/{$eventB->registration_slug}", resolution_payload());

    expect(Registration::count())->toBe(2);
    expect(Participant::where('email', 'maria@example.com')->count())->toBe(1);
});

test('an existing phone-only participant is reused across events', function () {
    $eventA = resolution_event(['registration_slug' => 'resolvaa']);
    $eventB = resolution_event(['registration_slug' => 'resolvbb']);

    $payload = resolution_payload(['email' => '']);

    $this->post("/r/{$eventA->registration_slug}", $payload);
    $this->post("/r/{$eventB->registration_slug}", $payload);

    expect(Registration::count())->toBe(2);
    expect(Participant::count())->toBe(1);
    expect(Participant::first()->contact_number_normalized)->toBe('09171234567');
});

// ---------------------------------------------------------------------------
// Duplicate within one event — validation error, not 500
// ---------------------------------------------------------------------------

test('a duplicate email for the same event is rejected', function () {
    $event = resolution_event();

    $this->post("/r/{$event->registration_slug}", resolution_payload());

    $response = $this->post("/r/{$event->registration_slug}", resolution_payload());

    $response->assertSessionHasErrors('email');
    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
});

test('a duplicate phone for the same event is rejected when email is absent', function () {
    $event = resolution_event();

    $payload = resolution_payload(['email' => '']);

    $this->post("/r/{$event->registration_slug}", $payload);

    $response = $this->post("/r/{$event->registration_slug}", $payload);

    $response->assertSessionHasErrors('contact_number');
    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// The specific case from ISSUE-009
// ---------------------------------------------------------------------------

test('the ISSUE-009 shape returns a validation error, not a 500', function () {
    // Event configured with email optional — the exact shape that
    // produced a 500 before Phase 5: two submissions with the same
    // phone and no email. The old create() branch hit the UNIQUE
    // constraint on contact_number_normalized. Now the pre-check
    // finds the existing participant and rejects with a field error.
    $event = resolution_event([
        'registration_common_field_requirements' => [
            'email' => false,
            'contact_number' => true,
        ],
    ]);

    $payload = resolution_payload(['email' => '']);

    $this->post("/r/{$event->registration_slug}", $payload);

    $response = $this->post("/r/{$event->registration_slug}", $payload);

    $response->assertSessionHasErrors('contact_number');
    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
    expect(Participant::whereNull('email')->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// First-write-wins at the controller layer
// ---------------------------------------------------------------------------

test('an existing participant record is not updated by a later submission', function () {
    $eventA = resolution_event(['registration_slug' => 'resolvaa']);
    $eventB = resolution_event(['registration_slug' => 'resolvbb']);

    $this->post("/r/{$eventA->registration_slug}", resolution_payload([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'age' => 28,
    ]));

    // Second submission for a different event with a changed name.
    // resolveFrom matches on email and discards the incoming attrs.
    $this->post("/r/{$eventB->registration_slug}", resolution_payload([
        'first_name' => 'Maria D.',
        'last_name' => 'Santos',
        'age' => 45,
    ]));

    $participant = Participant::where('email', 'maria@example.com')->first();

    expect($participant->first_name)->toBe('Maria');
    expect($participant->age)->toBe(28);
});
