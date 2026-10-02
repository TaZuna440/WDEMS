<?php

use App\Models\Event;
use App\Models\User;

/**
 * Covers Event::isCommonFieldRequired() and the two constants that
 * define which common participant fields are toggleable.
 *
 * The field defaults:
 *   - first_name, last_name, age → always required, never toggleable
 *   - email, contact_number, address → default required, toggleable
 *
 * A NULL column and a missing JSON key both resolve to required.
 */

function reqs_staff(): User
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
function reqs_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => reqs_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'Reqs Test Run',
        'event_date' => now()->addWeek()->toDateString(),
        'status' => 'draft',
    ], $overrides));
}

// ---------------------------------------------------------------------------
// Always-required fields
// ---------------------------------------------------------------------------

test('first_name is always required', function () {
    expect(reqs_event()->isCommonFieldRequired('first_name'))->toBeTrue();
});

test('last_name is always required', function () {
    expect(reqs_event()->isCommonFieldRequired('last_name'))->toBeTrue();
});

test('age is always required', function () {
    expect(reqs_event()->isCommonFieldRequired('age'))->toBeTrue();
});

test('always-required fields ignore the stored requirements map', function () {
    $event = reqs_event([
        'registration_common_field_requirements' => [
            'first_name' => false,
            'age' => false,
        ],
    ]);

    expect($event->isCommonFieldRequired('first_name'))->toBeTrue();
    expect($event->isCommonFieldRequired('age'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Toggleable fields — default required
// ---------------------------------------------------------------------------

test('email defaults to required when the column is null', function () {
    expect(reqs_event()->isCommonFieldRequired('email'))->toBeTrue();
});

test('contact_number defaults to required when the column is null', function () {
    expect(reqs_event()->isCommonFieldRequired('contact_number'))->toBeTrue();
});

test('address defaults to required when the column is null', function () {
    expect(reqs_event()->isCommonFieldRequired('address'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Toggleable fields — explicit values
// ---------------------------------------------------------------------------

test('email can be toggled to optional', function () {
    $event = reqs_event([
        'registration_common_field_requirements' => ['email' => false],
    ]);

    expect($event->isCommonFieldRequired('email'))->toBeFalse();
});

test('contact_number can be toggled to optional', function () {
    $event = reqs_event([
        'registration_common_field_requirements' => ['contact_number' => false],
    ]);

    expect($event->isCommonFieldRequired('contact_number'))->toBeFalse();
});

test('address can be toggled to optional', function () {
    $event = reqs_event([
        'registration_common_field_requirements' => ['address' => false],
    ]);

    expect($event->isCommonFieldRequired('address'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Partial maps
// ---------------------------------------------------------------------------

test('a partial map falls back to required for missing keys', function () {
    $event = reqs_event([
        'registration_common_field_requirements' => [
            'email' => false,
        ],
    ]);

    expect($event->isCommonFieldRequired('email'))->toBeFalse();
    expect($event->isCommonFieldRequired('contact_number'))->toBeTrue();
    expect($event->isCommonFieldRequired('address'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Defensive: unknown field names default to required
// ---------------------------------------------------------------------------

test('an unknown field name returns true', function () {
    expect(reqs_event()->isCommonFieldRequired('nonexistent_field'))->toBeTrue();
});
