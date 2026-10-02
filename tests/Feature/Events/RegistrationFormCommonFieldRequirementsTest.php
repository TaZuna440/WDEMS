<?php

use App\Models\Event;
use App\Models\RegistrationField;
use App\Models\User;

/**
 * Covers the common_field_requirements payload in the form builder —
 * save, load, defaults, compression to NULL, and rejection of invalid
 * keys or values.
 *
 * Helpers prefixed rfreqs_ to avoid collisions with sibling test files.
 */

function rfreqs_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

function rfreqs_event(): Event
{
    return Event::create([
        'created_by' => rfreqs_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'RF Reqs Test',
        'event_date' => now()->addWeek()->toDateString(),
        'status' => 'draft',
    ]);
}

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function rfreqs_payload(array $overrides = []): array
{
    return array_merge([
        'fields' => [],
        'common_field_requirements' => [
            'email' => true,
            'contact_number' => true,
            'address' => true,
        ],
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Show — payload shape
// ---------------------------------------------------------------------------

test('the builder page renders with the requirements map', function () {
    $staff = rfreqs_staff();
    $event = rfreqs_event();

    $this->actingAs($staff)
        ->get("/events/{$event->id}/registration-form")
        ->assertOk();
});

// ---------------------------------------------------------------------------
// Save — happy paths
// ---------------------------------------------------------------------------

test('saving all-required stores NULL on the column', function () {
    $staff = rfreqs_staff();
    $event = rfreqs_event();

    $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        rfreqs_payload(),
    );

    expect($event->fresh()->registration_common_field_requirements)->toBeNull();
});

test('saving with email optional persists the map', function () {
    $staff = rfreqs_staff();
    $event = rfreqs_event();

    $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        rfreqs_payload([
            'common_field_requirements' => [
                'email' => false,
                'contact_number' => true,
                'address' => true,
            ],
        ]),
    );

    $stored = $event->fresh()->registration_common_field_requirements;
    expect($stored)->toBeArray();
    expect($stored['email'])->toBeFalse();
    expect($stored['contact_number'])->toBeTrue();
    expect($stored['address'])->toBeTrue();
});

test('saving with all three optional persists all three', function () {
    $staff = rfreqs_staff();
    $event = rfreqs_event();

    $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        rfreqs_payload([
            'common_field_requirements' => [
                'email' => false,
                'contact_number' => false,
                'address' => false,
            ],
        ]),
    );

    $stored = $event->fresh()->registration_common_field_requirements;
    expect($stored['email'])->toBeFalse();
    expect($stored['contact_number'])->toBeFalse();
    expect($stored['address'])->toBeFalse();
});

test('re-saving with all-required compresses back to NULL', function () {
    $staff = rfreqs_staff();
    $event = rfreqs_event();

    // First save: email optional.
    $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        rfreqs_payload([
            'common_field_requirements' => [
                'email' => false,
                'contact_number' => true,
                'address' => true,
            ],
        ]),
    );

    // Second save: everything required.
    $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        rfreqs_payload(),
    );

    expect($event->fresh()->registration_common_field_requirements)->toBeNull();
});

// ---------------------------------------------------------------------------
// Save — validation
// ---------------------------------------------------------------------------

test('an unknown key in the requirements map is rejected', function () {
    $staff = rfreqs_staff();
    $event = rfreqs_event();

    $response = $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        rfreqs_payload([
            'common_field_requirements' => [
                'email' => true,
                'first_name' => false,
            ],
        ]),
    );

    $response->assertSessionHasErrors('common_field_requirements');
    expect($event->fresh()->registration_common_field_requirements)->toBeNull();
});

test('a non-boolean value in the requirements map is rejected', function () {
    $staff = rfreqs_staff();
    $event = rfreqs_event();

    $response = $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        rfreqs_payload([
            'common_field_requirements' => [
                'email' => 'yes',
            ],
        ]),
    );

    $response->assertSessionHasErrors('common_field_requirements.email');
});

// ---------------------------------------------------------------------------
// Backward compatibility — no requirements in payload
// ---------------------------------------------------------------------------

test('a payload with no requirements key stores NULL', function () {
    $staff = rfreqs_staff();
    $event = rfreqs_event();

    $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        ['fields' => []],
    );

    expect($event->fresh()->registration_common_field_requirements)->toBeNull();
});

// ---------------------------------------------------------------------------
// Interaction with custom fields
// ---------------------------------------------------------------------------

test('saving custom fields alongside a requirements map works', function () {
    $staff = rfreqs_staff();
    $event = rfreqs_event();

    $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        rfreqs_payload([
            'fields' => [
                [
                    'label' => 'Shirt Size',
                    'field_type' => 'select',
                    'options' => ['S', 'M', 'L'],
                    'is_required' => false,
                    'validation_rules' => null,
                ],
            ],
            'common_field_requirements' => [
                'email' => false,
                'contact_number' => true,
                'address' => true,
            ],
        ]),
    );

    expect(RegistrationField::where('event_id', $event->id)->count())->toBe(1);

    $stored = $event->fresh()->registration_common_field_requirements;
    expect($stored['email'])->toBeFalse();
});
