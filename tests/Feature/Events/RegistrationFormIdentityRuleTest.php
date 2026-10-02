<?php

use App\Models\Event;
use App\Models\RegistrationField;
use App\Models\User;

/**
 * Covers the form-builder rejection of a save where both identity
 * fields (email and contact_number) are toggled optional.
 *
 * The public submission route has its own check
 * (PublicRegistrationRequest::withValidator) so no form configured
 * this way could ever dedupe a submission. The builder rejects the
 * configuration up front.
 */

function rfidentity_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

function rfidentity_event(): Event
{
    return Event::create([
        'created_by' => rfidentity_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'RF Identity Test',
        'event_date' => now()->addWeek()->toDateString(),
        'status' => 'draft',
    ]);
}

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function rfidentity_payload(array $overrides = []): array
{
    return array_merge([
        'fields' => [],
        'common_field_requirements' => [],
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Accepted configurations
// ---------------------------------------------------------------------------

test('both identity fields required is accepted', function () {
    $staff = rfidentity_staff();
    $event = rfidentity_event();

    $response = $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        rfidentity_payload([
            'common_field_requirements' => [
                'email' => true,
                'contact_number' => true,
            ],
        ]),
    );

    $response->assertSessionHasNoErrors();
});

test('email required, phone optional is accepted', function () {
    $staff = rfidentity_staff();
    $event = rfidentity_event();

    $response = $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        rfidentity_payload([
            'common_field_requirements' => [
                'email' => true,
                'contact_number' => false,
            ],
        ]),
    );

    $response->assertSessionHasNoErrors();
});

test('phone required, email optional is accepted', function () {
    $staff = rfidentity_staff();
    $event = rfidentity_event();

    $response = $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        rfidentity_payload([
            'common_field_requirements' => [
                'email' => false,
                'contact_number' => true,
            ],
        ]),
    );

    $response->assertSessionHasNoErrors();
});

test('a payload with no requirements map is accepted', function () {
    $staff = rfidentity_staff();
    $event = rfidentity_event();

    $response = $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        ['fields' => []],
    );

    $response->assertSessionHasNoErrors();
});

// ---------------------------------------------------------------------------
// Rejected configuration
// ---------------------------------------------------------------------------

test('both identity fields optional is rejected', function () {
    $staff = rfidentity_staff();
    $event = rfidentity_event();

    $response = $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        rfidentity_payload([
            'common_field_requirements' => [
                'email' => false,
                'contact_number' => false,
            ],
        ]),
    );

    $response->assertSessionHasErrors('common_field_requirements');
    expect($event->fresh()->registration_form_saved_at)->toBeNull();
});

test('both identity fields optional with custom fields present is rejected', function () {
    $staff = rfidentity_staff();
    $event = rfidentity_event();

    $response = $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        rfidentity_payload([
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
                'contact_number' => false,
            ],
        ]),
    );

    $response->assertSessionHasErrors('common_field_requirements');
    expect(RegistrationField::where('event_id', $event->id)->count())->toBe(0);
});
