<?php

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;

/**
 * Covers the participant name-quality rules added to
 * PublicRegistrationRequest.
 *
 * First and last names must pass the same four checks the wizard
 * already applies to event_name: start with letter or digit, contain
 * at least one vowel, contain at least one consonant, no 3+ identical
 * characters in a row. The rules come from the shared
 * HumanNameQualityRules trait.
 *
 * Helpers prefixed pname_ to avoid Pest collisions.
 */

function pname_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

function pname_event(): Event
{
    return Event::create([
        'created_by' => pname_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'Name Validation Run',
        'event_date' => now()->addWeek()->toDateString(),
        'status' => 'registration_open',
        'registration_slug' => 'pname'.random_int(1000, 9999),
        'registration_start' => now(),
        'registration_form_saved_at' => now(),
    ]);
}

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function pname_payload(array $overrides = []): array
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
// Rejections — first_name
// ---------------------------------------------------------------------------

test('a first name with no vowel is rejected', function () {
    $event = pname_event();

    $response = $this->post("/r/{$event->registration_slug}", pname_payload([
        'first_name' => 'sdfghjkl',
    ]));

    $response->assertSessionHasErrors('first_name');
    expect(Registration::where('event_id', $event->id)->count())->toBe(0);
});

test('a first name with no consonant is rejected', function () {
    $event = pname_event();

    $response = $this->post("/r/{$event->registration_slug}", pname_payload([
        'first_name' => 'aeiou',
    ]));

    $response->assertSessionHasErrors('first_name');
});

test('a first name with three identical characters in a row is rejected', function () {
    $event = pname_event();

    $response = $this->post("/r/{$event->registration_slug}", pname_payload([
        'first_name' => 'Maaaria',
    ]));

    $response->assertSessionHasErrors('first_name');
});

test('a first name starting with a non-alphanumeric character is rejected', function () {
    $event = pname_event();

    $response = $this->post("/r/{$event->registration_slug}", pname_payload([
        'first_name' => '-Maria',
    ]));

    $response->assertSessionHasErrors('first_name');
});

// ---------------------------------------------------------------------------
// Rejections — last_name
// ---------------------------------------------------------------------------

test('a last name with no vowel is rejected', function () {
    $event = pname_event();

    $response = $this->post("/r/{$event->registration_slug}", pname_payload([
        'last_name' => 'sdfghjkl',
    ]));

    $response->assertSessionHasErrors('last_name');
    expect(Registration::where('event_id', $event->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Acceptance
// ---------------------------------------------------------------------------

test('a valid first and last name pair is accepted', function () {
    $event = pname_event();

    $response = $this->post("/r/{$event->registration_slug}", pname_payload());

    $response->assertSessionHasNoErrors();
    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
});

test('a short two-letter name is accepted', function () {
    $event = pname_event();

    $response = $this->post("/r/{$event->registration_slug}", pname_payload([
        'first_name' => 'Al',
        'last_name' => 'Bo',
    ]));

    $response->assertSessionHasNoErrors();
    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
});

test('a hyphenated name is accepted', function () {
    $event = pname_event();

    $response = $this->post("/r/{$event->registration_slug}", pname_payload([
        'first_name' => 'Jean-Luc',
        'last_name' => 'Dela-Cruz',
    ]));

    $response->assertSessionHasNoErrors();
});
