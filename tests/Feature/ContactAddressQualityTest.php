<?php

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers FIX-027: contact_number and address quality rules.
 *
 * The public registration form and the walk-in dialog both use the
 * ContactAndAddressQualityRules trait to reject keyboard mashing
 * ("fgfdgfdgdfg", "dfdfdsfdsfdsfd") while admitting real phone
 * numbers and addresses. These tests exercise both entry points.
 *
 * Landline note: this file locks in the design decision that
 * landlines are accepted by the format check but do not normalize
 * to the PH-mobile identity key. A landline submission with a
 * valid email satisfies the at-least-one identity rule through the
 * email. See docs/participant-identity.md §9.
 *
 * Helpers are prefixed cq_ (contact quality) to avoid Pest function
 * collisions with sibling test files.
 */

function cq_staff(): User
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
function cq_public_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => cq_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'CQ Public Run',
        'event_date' => now()->addWeek()->toDateString(),
        'status' => 'registration_open',
        'registration_slug' => 'cqpubabc',
        'registration_start' => now(),
        'registration_form_saved_at' => now(),
    ], $overrides));
}

/**
 * Walk-in event with contact_number and address marked optional.
 * The quality rules still fire when a value is present — the
 * nullable prefix only allows a blank.
 *
 * @param array<string, mixed> $overrides
 */
function cq_walkin_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => cq_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'CQ Walk-in Run',
        'event_date' => today()->toDateString(),
        'status' => 'registration_closed',
        'registration_form_saved_at' => now(),
        'registration_common_field_requirements' => [
            'email' => true,
            'contact_number' => false,
            'address' => false,
        ],
    ], $overrides));
}

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function cq_public_payload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria@example.com',
        'contact_number' => '+63 917 000 0000',
        'age' => 28,
        'address' => 'Manila',
        'responses' => [],
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Public form — contact_number quality
// ---------------------------------------------------------------------------

test('public: a phone number containing letters is rejected', function () {
    $event = cq_public_event();

    $response = $this->post(
        "/r/{$event->registration_slug}",
        cq_public_payload(['contact_number' => 'fgfdgfdgdfg']),
    );

    $response->assertSessionHasErrors('contact_number');
    expect(Registration::where('event_id', $event->id)->count())->toBe(0);
});

test('public: a phone number with too few digits is rejected', function () {
    $event = cq_public_event();

    $response = $this->post(
        "/r/{$event->registration_slug}",
        cq_public_payload(['contact_number' => '123']),
    );

    $response->assertSessionHasErrors('contact_number');
    expect(Registration::where('event_id', $event->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Public form — address quality
// ---------------------------------------------------------------------------

test('public: an address with no vowels is rejected', function () {
    $event = cq_public_event();

    $response = $this->post(
        "/r/{$event->registration_slug}",
        cq_public_payload(['address' => 'dfdfdsfdsfdsfd']),
    );

    $response->assertSessionHasErrors('address');
    expect(Registration::where('event_id', $event->id)->count())->toBe(0);
});

test('public: an address with no letters is rejected', function () {
    $event = cq_public_event();

    $response = $this->post(
        "/r/{$event->registration_slug}",
        cq_public_payload(['address' => '88888888']),
    );

    $response->assertSessionHasErrors('address');
    expect(Registration::where('event_id', $event->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Public form — landline is accepted format-wise (identity still via email)
// ---------------------------------------------------------------------------

test('public: a valid landline is accepted when email is also present', function () {
    $event = cq_public_event();

    $response = $this->post(
        "/r/{$event->registration_slug}",
        cq_public_payload(['contact_number' => '02-8123-4567']),
    );

    $response->assertSessionHasNoErrors();
    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Walk-in dialog — same rules, same trait, same behavior
// ---------------------------------------------------------------------------

test('walk-in: a phone number containing letters is rejected', function () {
    $staff = cq_staff();
    $event = cq_walkin_event();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", [
            'first_name' => 'Walk',
            'last_name' => 'In',
            'age' => 30,
            'email' => 'walkin@example.com',
            'contact_number' => 'fgfdgfdgdfg',
        ])
        ->assertSessionHasErrors('contact_number');

    expect(Registration::where('event_id', $event->id)->count())->toBe(0);
});

test('walk-in: an address with no vowels is rejected', function () {
    $staff = cq_staff();
    $event = cq_walkin_event();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", [
            'first_name' => 'Walk',
            'last_name' => 'In',
            'age' => 30,
            'email' => 'walkin@example.com',
            'address' => 'dfdfdsfdsfdsfd',
        ])
        ->assertSessionHasErrors('address');

    expect(Registration::where('event_id', $event->id)->count())->toBe(0);
});

test('walk-in: a valid landline is accepted when email is also present', function () {
    $staff = cq_staff();
    $event = cq_walkin_event();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", [
            'first_name' => 'Walk',
            'last_name' => 'In',
            'age' => 30,
            'email' => 'walkin@example.com',
            'contact_number' => '02-8123-4567',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
});
