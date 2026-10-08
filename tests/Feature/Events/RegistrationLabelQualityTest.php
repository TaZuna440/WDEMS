<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers the short-label quality rules added in FIX-025:
 *   - min length 2
 *   - must contain a letter
 *   - vowel required for 2+ character labels
 *   - consonant required for 3+ character labels
 *   - short-label exemption list
 */

function labelq_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

function labelq_event(): Event
{
    return Event::create([
        'created_by' => labelq_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'Test Run',
        'event_date' => now()->addWeek()->toDateString(),
        'status' => 'draft',
    ]);
}

/**
 * @return array<string, mixed>
 */
function labelq_payload(string $label): array
{
    return [
        'fields' => [
            [
                'label' => $label,
                'field_type' => 'text',
                'options' => [],
                'is_required' => false,
                'validation_rules' => null,
            ],
        ],
    ];
}

test('it rejects labels with no letters at all', function (string $label) {
    $user = labelq_staff();
    $event = labelq_event();

    $response = $this->actingAs($user)->put(
        route('events.registration-form.update', $event),
        labelq_payload($label),
    );

    $response->assertSessionHasErrors('fields.0.label');
})->with([
    '5',
    '4565',
    '123',
    '99999',
    '5.5',
]);

test('it rejects 2-character labels with no vowel', function (string $label) {
    $user = labelq_staff();
    $event = labelq_event();

    $response = $this->actingAs($user)->put(
        route('events.registration-form.update', $event),
        labelq_payload($label),
    );

    $response->assertSessionHasErrors('fields.0.label');
})->with([
    'sf',
    'bb',
    'cc',
    'zz',
]);

test('it rejects 3+ character labels with no vowel', function (string $label) {
    $user = labelq_staff();
    $event = labelq_event();

    $response = $this->actingAs($user)->put(
        route('events.registration-form.update', $event),
        labelq_payload($label),
    );

    $response->assertSessionHasErrors('fields.0.label');
})->with([
    'gfdgfdfgfd',
    'sfx',
    'bcdfg',
    'zxcvb',
]);

test('it rejects 3+ character labels with no consonant', function (string $label) {
    $user = labelq_staff();
    $event = labelq_event();

    $response = $this->actingAs($user)->put(
        route('events.registration-form.update', $event),
        labelq_payload($label),
    );

    $response->assertSessionHasErrors('fields.0.label');
})->with([
    'aaa',
    'aeiou',
    'ououo',
]);

test('it allows legitimate short labels from the exemption list', function (string $label) {
    $user = labelq_staff();
    $event = labelq_event();

    $response = $this->actingAs($user)->put(
        route('events.registration-form.update', $event),
        labelq_payload($label),
    );

    $response->assertSessionDoesntHaveErrors('fields.0.label');
})->with([
    'KM',
    'KG',
    'HR',
    'ML',
    'CM',
    'ID',
    'No',
    'OK',
    'km',
    'kg',
]);

test('it allows short labels with a vowel and consonant', function (string $label) {
    $user = labelq_staff();
    $event = labelq_event();

    $response = $this->actingAs($user)->put(
        route('events.registration-form.update', $event),
        labelq_payload($label),
    );

    $response->assertSessionDoesntHaveErrors('fields.0.label');
})->with([
    'ab',
    'ok',
]);

test('it allows short vowel-only labels at exactly 2 characters', function (string $label) {
    $user = labelq_staff();
    $event = labelq_event();

    $response = $this->actingAs($user)->put(
        route('events.registration-form.update', $event),
        labelq_payload($label),
    );

    $response->assertSessionDoesntHaveErrors('fields.0.label');
})->with([
    'aa',
    'ee',
]);

test('it still allows full-length event-appropriate labels', function (string $label) {
    $user = labelq_staff();
    $event = labelq_event();

    $response = $this->actingAs($user)->put(
        route('events.registration-form.update', $event),
        labelq_payload($label),
    );

    $response->assertSessionDoesntHaveErrors('fields.0.label');
})->with([
    'Jersey size',
    'Shirt size',
    'Blood type',
    'Medical notes',
    'Emergency contact',
]);
