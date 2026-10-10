<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\RegistrationField;
use App\Models\RegistrationFieldResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Covers the two per-registration signals added to the monitor feed
 * after the initial Phase 7 ship: `has_shared_phone` and
 * `custom_fields`. See the Status addendum in
 * docs/registration-monitoring.md.
 *
 * has_shared_phone semantics: the identity model enforces a UNIQUE
 * constraint on contact_number_normalized, so two participants cannot
 * share a normalized PH mobile. The reachable shared case is a RAW
 * contact_number string that does not normalize — typically a
 * landline shared by family members. The tests below construct that
 * case explicitly.
 *
 * Helpers prefixed rmx_ to avoid Pest function collisions.
 */

function rmx_staff(): User
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
function rmx_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => rmx_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'RMX Test Run',
        'event_date' => today()->toDateString(),
        'status' => 'registration_open',
        'registration_form_saved_at' => now(),
        'registration_slug' => 'rmxabc'.uniqid(),
        'registration_start' => now(),
    ], $overrides));
}

/**
 * @param array<string, mixed> $overrides
 */
function rmx_participant(array $overrides = []): Participant
{
    return Participant::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'age' => 28,
        'email' => 'p'.uniqid().'@example.com',
    ], $overrides));
}

function rmx_registration(Event $event, Participant $participant): Registration
{
    return Registration::create([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'registration_date' => now(),
        'registration_status' => 'confirmed',
        'source' => 'form',
        'registered_at' => now(),
    ]);
}

// ---------------------------------------------------------------------------
// Same-phone detection
// ---------------------------------------------------------------------------

test('has_shared_phone is true when two participants typed the same raw landline', function () {
    $staff = rmx_staff();
    $event = rmx_event();

    // Two participants typed the same landline. Landlines do not
    // normalize, so contact_number_normalized is null on both —
    // MySQL allows two NULLs under the UNIQUE index, and the raw
    // strings are identical. This is the reachable shared-phone
    // case: a family that shares a home phone at the registration
    // desk.
    $a = rmx_participant(['contact_number' => '02-8123-4567']);
    $b = rmx_participant(['contact_number' => '02-8123-4567']);

    rmx_registration($event, $a);
    rmx_registration($event, $b);

    $this->actingAs($staff)
        ->get("/registrations/monitor/{$event->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('registrations/monitor/show')
            ->has('registrations', 2)
            ->where('registrations.0.has_shared_phone', true)
            ->where('registrations.1.has_shared_phone', true)
        );
});

test('has_shared_phone is false when participants have different raw phones', function () {
    $staff = rmx_staff();
    $event = rmx_event();

    $a = rmx_participant(['contact_number' => '02-8123-4567']);
    $b = rmx_participant(['contact_number' => '02-8123-9999']);

    rmx_registration($event, $a);
    rmx_registration($event, $b);

    $this->actingAs($staff)
        ->get("/registrations/monitor/{$event->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('registrations.0.has_shared_phone', false)
            ->where('registrations.1.has_shared_phone', false)
        );
});

test('has_shared_phone is false when only one participant has a raw phone', function () {
    $staff = rmx_staff();
    $event = rmx_event();

    $a = rmx_participant(['contact_number' => '02-8123-4567']);
    $b = rmx_participant(['contact_number' => null]);

    rmx_registration($event, $a);
    rmx_registration($event, $b);

    $this->actingAs($staff)
        ->get("/registrations/monitor/{$event->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('registrations.0.has_shared_phone', false)
            ->where('registrations.1.has_shared_phone', false)
        );
});

// ---------------------------------------------------------------------------
// Custom field summaries
// ---------------------------------------------------------------------------

test('custom_fields are included in the registration payload', function () {
    $staff = rmx_staff();
    $event = rmx_event();

    $field = RegistrationField::create([
        'event_id' => $event->id,
        'label' => 'Shirt Size',
        'field_type' => 'select',
        'options' => ['Small', 'Medium', 'Large'],
        'is_required' => false,
        'display_order' => 0,
    ]);

    $participant = rmx_participant();
    $registration = rmx_registration($event, $participant);

    RegistrationFieldResponse::create([
        'registration_id' => $registration->id,
        'registration_field_id' => $field->id,
        'value' => 'Medium',
    ]);

    $this->actingAs($staff)
        ->get("/registrations/monitor/{$event->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('registrations', 1)
            ->has('registrations.0.custom_fields', 1)
            ->where('registrations.0.custom_fields.0.label', 'Shirt Size')
            ->where('registrations.0.custom_fields.0.value', 'Medium')
        );
});

test('custom_fields are ordered by field display_order', function () {
    $staff = rmx_staff();
    $event = rmx_event();

    $later = RegistrationField::create([
        'event_id' => $event->id,
        'label' => 'Emergency Contact',
        'field_type' => 'text',
        'options' => null,
        'is_required' => false,
        'display_order' => 10,
    ]);

    $earlier = RegistrationField::create([
        'event_id' => $event->id,
        'label' => 'Shirt Size',
        'field_type' => 'select',
        'options' => ['Small', 'Large'],
        'is_required' => false,
        'display_order' => 0,
    ]);

    $participant = rmx_participant();
    $registration = rmx_registration($event, $participant);

    // Insert in reverse display_order to prove the sort is real.
    RegistrationFieldResponse::create([
        'registration_id' => $registration->id,
        'registration_field_id' => $later->id,
        'value' => 'Mom',
    ]);
    RegistrationFieldResponse::create([
        'registration_id' => $registration->id,
        'registration_field_id' => $earlier->id,
        'value' => 'Large',
    ]);

    $this->actingAs($staff)
        ->get("/registrations/monitor/{$event->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('registrations.0.custom_fields.0.label', 'Shirt Size')
            ->where('registrations.0.custom_fields.1.label', 'Emergency Contact')
        );
});

test('checkbox responses are decoded into arrays', function () {
    $staff = rmx_staff();
    $event = rmx_event();

    $field = RegistrationField::create([
        'event_id' => $event->id,
        'label' => 'Dietary Restrictions',
        'field_type' => 'checkbox',
        'options' => ['Vegetarian', 'Vegan', 'No nuts'],
        'is_required' => false,
        'display_order' => 0,
    ]);

    $participant = rmx_participant();
    $registration = rmx_registration($event, $participant);

    RegistrationFieldResponse::create([
        'registration_id' => $registration->id,
        'registration_field_id' => $field->id,
        'value' => json_encode(['Vegetarian', 'No nuts']),
    ]);

    $this->actingAs($staff)
        ->get("/registrations/monitor/{$event->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('registrations.0.custom_fields.0.value', ['Vegetarian', 'No nuts'])
        );
});

test('custom_fields is empty when the registration has no responses', function () {
    $staff = rmx_staff();
    $event = rmx_event();

    $participant = rmx_participant();
    rmx_registration($event, $participant);

    $this->actingAs($staff)
        ->get("/registrations/monitor/{$event->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('registrations.0.custom_fields', 0)
        );
});
