<?php

use App\Models\Event;
use App\Models\RegistrationField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers the registration form batch endpoint:
 *   GET  /events/{event}/registration-form  — renders the builder
 *   PUT  /events/{event}/registration-form  — replaces the form
 *
 * Also covers the validation contract — caps, label quality, label
 * collisions, choice-field options — and the lock-at-Open-Registration
 * rule.
 */

function regform_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

function regform_event(string $status = 'draft'): Event
{
    return Event::create([
        'created_by' => regform_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'Test Run',
        'event_date' => now()->addWeek()->toDateString(),
        'status' => $status,
    ]);
}

/**
 * @param array<int, array<string, mixed>> $fields
 * @return array<string, mixed>
 */
function regform_payload(array $fields): array
{
    return ['fields' => $fields];
}

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function regform_field(array $overrides = []): array
{
    return array_merge([
        'label' => 'Shirt Size',
        'field_type' => 'select',
        'options' => ['Small', 'Medium', 'Large'],
        'is_required' => false,
        'validation_rules' => null,
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Show
// ---------------------------------------------------------------------------

test('it renders the builder for a draft event', function () {
    $staff = regform_staff();
    $event = regform_event();

    $response = $this->actingAs($staff)
        ->get("/events/{$event->id}/registration-form");

    $response->assertOk();
});

// ---------------------------------------------------------------------------
// Save — happy paths
// ---------------------------------------------------------------------------

test('it saves an empty form (common fields only)', function () {
    $staff = regform_staff();
    $event = regform_event();

    $response = $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        regform_payload([]),
    );

    $response->assertRedirect(route('events.show', $event));
    $response->assertSessionHasNoErrors();

    expect($event->fresh()->registrationFields)->toHaveCount(0);
    expect($event->fresh()->registration_form_saved_at)->not->toBeNull();
});

test('it saves one custom text field', function () {
    $staff = regform_staff();
    $event = regform_event();

    $response = $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        regform_payload([
            regform_field([
                'label' => 'Emergency Contact',
                'field_type' => 'text',
                'options' => [],
            ]),
        ]),
    );

    $response->assertRedirect(route('events.show', $event));
    $response->assertSessionHasNoErrors();

    $fields = $event->fresh()->registrationFields;
    expect($fields)->toHaveCount(1);
    expect($fields->first()->label)->toBe('Emergency Contact');
    expect($fields->first()->field_type)->toBe('text');
    expect($fields->first()->display_order)->toBe(0);
});

test('it saves a select field with options', function () {
    $staff = regform_staff();
    $event = regform_event();

    $response = $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        regform_payload([regform_field()]),
    );

    $response->assertSessionHasNoErrors();

    $field = $event->fresh()->registrationFields->first();
    expect($field->options)->toBe(['Small', 'Medium', 'Large']);
});

test('it assigns display_order from array position', function () {
    $staff = regform_staff();
    $event = regform_event();

    $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        regform_payload([
            regform_field(['label' => 'First Field', 'field_type' => 'text', 'options' => []]),
            regform_field(['label' => 'Second Field', 'field_type' => 'text', 'options' => []]),
            regform_field(['label' => 'Third Field', 'field_type' => 'text', 'options' => []]),
        ]),
    );

    $fields = $event->fresh()->registrationFields->sortBy('display_order')->values();

    expect($fields)->toHaveCount(3);
    expect($fields[0]->label)->toBe('First Field');
    expect($fields[0]->display_order)->toBe(0);
    expect($fields[1]->label)->toBe('Second Field');
    expect($fields[1]->display_order)->toBe(1);
    expect($fields[2]->label)->toBe('Third Field');
    expect($fields[2]->display_order)->toBe(2);
});

test('it replaces the entire form on a second save', function () {
    $staff = regform_staff();
    $event = regform_event();

    $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        regform_payload([
            regform_field(['label' => 'Original', 'field_type' => 'text', 'options' => []]),
        ]),
    );

    $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        regform_payload([
            regform_field(['label' => 'Replacement', 'field_type' => 'text', 'options' => []]),
        ]),
    );

    $fields = $event->fresh()->registrationFields;
    expect($fields)->toHaveCount(1);
    expect($fields->first()->label)->toBe('Replacement');
});

// ---------------------------------------------------------------------------
// Save — validation failures
// ---------------------------------------------------------------------------

test('it rejects more than 5 custom fields', function () {
    $staff = regform_staff();
    $event = regform_event();

    $fields = [];
    for ($i = 0; $i < 6; $i++) {
        $fields[] = regform_field([
            'label' => "Field {$i}",
            'field_type' => 'text',
            'options' => [],
        ]);
    }

    $response = $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        regform_payload($fields),
    );

    $response->assertSessionHasErrors('fields');
});

test('it rejects a keyboard-mashing label', function () {
    $staff = regform_staff();
    $event = regform_event();

    $response = $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        regform_payload([
            regform_field([
                'label' => 'gfdgfdfgfd',
                'field_type' => 'text',
                'options' => [],
            ]),
        ]),
    );

    $response->assertSessionHasErrors('fields.0.label');
});

test('it accepts a short label like KM', function () {
    $staff = regform_staff();
    $event = regform_event();

    $response = $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        regform_payload([
            regform_field(['label' => 'KM', 'field_type' => 'text', 'options' => []]),
        ]),
    );

    $response->assertSessionHasNoErrors();
});

test('it rejects duplicate labels', function () {
    $staff = regform_staff();
    $event = regform_event();

    $response = $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        regform_payload([
            regform_field(['label' => 'Shirt Size', 'field_type' => 'text', 'options' => []]),
            regform_field(['label' => 'Shirt Size', 'field_type' => 'text', 'options' => []]),
        ]),
    );

    $response->assertSessionHasErrors('fields.1.label');
});

test('it rejects a label that collides with a common field', function () {
    $staff = regform_staff();
    $event = regform_event();

    $response = $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        regform_payload([
            regform_field(['label' => 'Email', 'field_type' => 'text', 'options' => []]),
        ]),
    );

    $response->assertSessionHasErrors('fields.0.label');
});

test('it rejects a label that collides with a common field via normalization', function () {
    $staff = regform_staff();
    $event = regform_event();

    $response = $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        regform_payload([
            regform_field(['label' => 'first_name', 'field_type' => 'text', 'options' => []]),
        ]),
    );

    $response->assertSessionHasErrors('fields.0.label');
});

test('it rejects a select field with fewer than 2 choices', function () {
    $staff = regform_staff();
    $event = regform_event();

    $response = $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        regform_payload([
            regform_field(['options' => ['Only One']]),
        ]),
    );

    $response->assertSessionHasErrors('fields.0.options');
});

test('it rejects a text field that carries options', function () {
    $staff = regform_staff();
    $event = regform_event();

    $response = $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        regform_payload([
            regform_field([
                'label' => 'Notes',
                'field_type' => 'text',
                'options' => ['a', 'b'],
            ]),
        ]),
    );

    $response->assertSessionHasErrors('fields.0.options');
});

test('it rejects more than 10 choices on a single field', function () {
    $staff = regform_staff();
    $event = regform_event();

    $options = [];
    for ($i = 0; $i < 11; $i++) {
        $options[] = "Choice {$i}";
    }

    $response = $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        regform_payload([
            regform_field(['options' => $options]),
        ]),
    );

    $response->assertSessionHasErrors('fields.0.options');
});

test('it rejects an empty choice value', function () {
    $staff = regform_staff();
    $event = regform_event();

    $response = $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        regform_payload([
            regform_field(['options' => ['Good', '']]),
        ]),
    );

    $response->assertSessionHasErrors('fields.0.options.1');
});

// ---------------------------------------------------------------------------
// Lock — form cannot be edited after registration opens
// ---------------------------------------------------------------------------

test('it rejects save when the event is not Draft', function () {
    $staff = regform_staff();
    $event = regform_event('registration_open');

    $response = $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        regform_payload([
            regform_field(['label' => 'Shirt Size', 'field_type' => 'text', 'options' => []]),
        ]),
    );

    $response->assertForbidden();
    expect(RegistrationField::where('event_id', $event->id)->count())->toBe(0);
});

test('it does not stamp registration_form_saved_at on a rejected save', function () {
    $staff = regform_staff();
    $event = regform_event();

    $this->actingAs($staff)->put(
        "/events/{$event->id}/registration-form",
        regform_payload([
            regform_field([
                'label' => 'gfdgfdfgfd',
                'field_type' => 'text',
                'options' => [],
            ]),
        ]),
    );

    expect($event->fresh()->registration_form_saved_at)->toBeNull();
});
