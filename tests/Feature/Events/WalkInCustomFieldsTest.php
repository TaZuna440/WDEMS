<?php

use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationField;
use App\Models\RegistrationFieldResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers Phase A part 2 of docs/attendance-redesign.md: custom field
 * capture on the walk-in dialog.
 *
 * The walk-in endpoint now accepts a `responses` map keyed by
 * registration_field_id, validates it the same way the public form
 * does, and writes RegistrationFieldResponse rows inside the same
 * transaction. These tests exercise all eight field types plus the
 * validation failure paths.
 *
 * Helpers are prefixed wicf_ (walk-in custom field) to avoid Pest
 * function collisions with sibling test files.
 */

function wicf_staff(): User
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
function wicf_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => wicf_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'WICF Test Run',
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
 */
function wicf_field(Event $event, array $overrides = []): RegistrationField
{
    return RegistrationField::create(array_merge([
        'event_id' => $event->id,
        'label' => 'Shirt Size',
        'field_type' => 'select',
        'options' => ['Small', 'Medium', 'Large'],
        'is_required' => false,
        'display_order' => 0,
    ], $overrides));
}

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function wicf_payload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Walk',
        'last_name' => 'In',
        'age' => 30,
        'email' => 'walkin'.uniqid().'@example.com',
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Field type round-trips
// ---------------------------------------------------------------------------

test('walk-in writes a text custom field response', function () {
    $staff = wicf_staff();
    $event = wicf_event();
    $field = wicf_field($event, [
        'label' => 'Emergency Contact',
        'field_type' => 'text',
        'options' => null,
    ]);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", wicf_payload([
            'responses' => [$field->id => 'Mom'],
        ]))
        ->assertRedirect();

    $registration = Registration::where('event_id', $event->id)->firstOrFail();
    $response = RegistrationFieldResponse::where('registration_id', $registration->id)
        ->where('registration_field_id', $field->id)
        ->first();

    expect($response)->not->toBeNull();
    expect($response->value)->toBe('Mom');
});

test('walk-in writes a textarea custom field response', function () {
    $staff = wicf_staff();
    $event = wicf_event();
    $field = wicf_field($event, [
        'label' => 'Notes',
        'field_type' => 'textarea',
        'options' => null,
    ]);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", wicf_payload([
            'responses' => [$field->id => 'Allergic to peanuts.'],
        ]))
        ->assertRedirect();

    $registration = Registration::where('event_id', $event->id)->firstOrFail();
    $response = RegistrationFieldResponse::where('registration_id', $registration->id)
        ->where('registration_field_id', $field->id)
        ->first();

    expect($response->value)->toBe('Allergic to peanuts.');
});

test('walk-in writes a number custom field response', function () {
    $staff = wicf_staff();
    $event = wicf_event();
    $field = wicf_field($event, [
        'label' => 'Weight (kg)',
        'field_type' => 'number',
        'options' => null,
    ]);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", wicf_payload([
            'responses' => [$field->id => '72'],
        ]))
        ->assertRedirect();

    $registration = Registration::where('event_id', $event->id)->firstOrFail();
    $response = RegistrationFieldResponse::where('registration_id', $registration->id)
        ->where('registration_field_id', $field->id)
        ->first();

    expect($response->value)->toBe('72');
});

test('walk-in writes an email custom field response', function () {
    $staff = wicf_staff();
    $event = wicf_event();
    $field = wicf_field($event, [
        'label' => 'Guardian Email',
        'field_type' => 'email',
        'options' => null,
    ]);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", wicf_payload([
            'responses' => [$field->id => 'guardian@example.com'],
        ]))
        ->assertRedirect();

    $registration = Registration::where('event_id', $event->id)->firstOrFail();
    $response = RegistrationFieldResponse::where('registration_id', $registration->id)
        ->where('registration_field_id', $field->id)
        ->first();

    expect($response->value)->toBe('guardian@example.com');
});

test('walk-in writes a date custom field response', function () {
    $staff = wicf_staff();
    $event = wicf_event();
    $field = wicf_field($event, [
        'label' => 'Last Tetanus Shot',
        'field_type' => 'date',
        'options' => null,
    ]);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", wicf_payload([
            'responses' => [$field->id => '2025-06-15'],
        ]))
        ->assertRedirect();

    $registration = Registration::where('event_id', $event->id)->firstOrFail();
    $response = RegistrationFieldResponse::where('registration_id', $registration->id)
        ->where('registration_field_id', $field->id)
        ->first();

    expect($response->value)->toBe('2025-06-15');
});

test('walk-in writes a select custom field response', function () {
    $staff = wicf_staff();
    $event = wicf_event();
    $field = wicf_field($event);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", wicf_payload([
            'responses' => [$field->id => 'Medium'],
        ]))
        ->assertRedirect();

    $registration = Registration::where('event_id', $event->id)->firstOrFail();
    $response = RegistrationFieldResponse::where('registration_id', $registration->id)
        ->where('registration_field_id', $field->id)
        ->first();

    expect($response->value)->toBe('Medium');
});

test('walk-in writes a radio custom field response', function () {
    $staff = wicf_staff();
    $event = wicf_event();
    $field = wicf_field($event, [
        'label' => 'Blood Type',
        'field_type' => 'radio',
        'options' => ['A', 'B', 'O'],
    ]);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", wicf_payload([
            'responses' => [$field->id => 'O'],
        ]))
        ->assertRedirect();

    $registration = Registration::where('event_id', $event->id)->firstOrFail();
    $response = RegistrationFieldResponse::where('registration_id', $registration->id)
        ->where('registration_field_id', $field->id)
        ->first();

    expect($response->value)->toBe('O');
});

test('walk-in JSON-encodes a checkbox array response', function () {
    $staff = wicf_staff();
    $event = wicf_event();
    $field = wicf_field($event, [
        'label' => 'Dietary Restrictions',
        'field_type' => 'checkbox',
        'options' => ['Vegetarian', 'Vegan', 'Gluten-free', 'No nuts'],
    ]);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", wicf_payload([
            'responses' => [$field->id => ['Vegetarian', 'No nuts']],
        ]))
        ->assertRedirect();

    $registration = Registration::where('event_id', $event->id)->firstOrFail();
    $response = RegistrationFieldResponse::where('registration_id', $registration->id)
        ->where('registration_field_id', $field->id)
        ->first();

    expect($response->value)->toBe(json_encode(['Vegetarian', 'No nuts']));
});

// ---------------------------------------------------------------------------
// Validation failures
// ---------------------------------------------------------------------------

test('walk-in rejects a missing required custom field', function () {
    $staff = wicf_staff();
    $event = wicf_event();
    $field = wicf_field($event, ['is_required' => true]);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", wicf_payload())
        ->assertSessionHasErrors("responses.{$field->id}");

    expect(Registration::where('event_id', $event->id)->count())->toBe(0);
});

test('walk-in rejects an invalid select choice', function () {
    $staff = wicf_staff();
    $event = wicf_event();
    $field = wicf_field($event);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", wicf_payload([
            'responses' => [$field->id => 'Huge'],
        ]))
        ->assertSessionHasErrors("responses.{$field->id}");

    expect(Registration::where('event_id', $event->id)->count())->toBe(0);
});

test('walk-in rejects a response key for another events field', function () {
    $staff = wicf_staff();
    $event = wicf_event();
    $otherEvent = wicf_event(['event_name' => 'Other WICF Run']);
    $otherField = wicf_field($otherEvent);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", wicf_payload([
            'responses' => [$otherField->id => 'Small'],
        ]))
        ->assertSessionHasErrors("responses.{$otherField->id}");

    expect(Registration::where('event_id', $event->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Source labeling
// ---------------------------------------------------------------------------

test('walk-in preserves source = walk_in when custom fields are present', function () {
    $staff = wicf_staff();
    $event = wicf_event();
    $field = wicf_field($event);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", wicf_payload([
            'responses' => [$field->id => 'Large'],
        ]))
        ->assertRedirect();

    $registration = Registration::where('event_id', $event->id)->firstOrFail();

    expect($registration->source)->toBe('walk_in');
});
