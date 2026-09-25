<?php

use App\Models\User;

/**
 * Covers the date rules on event_date:
 *
 *   SCH-06 — event_date cannot be in the past
 *   SCH-07 — event_date cannot be more than 2 years in the future
 *
 * See docs/event-creation-issues.md.
 */

function date_payload(array $overrides = []): array
{
    return array_merge([
        'event_type' => 'community_run',
        'event_name' => 'Test Run',
        'description' => '',
        'event_date' => now()->addMonth()->toDateString(),
        'start_time' => '18:00',
        'end_time' => '20:00',
        'distance_value' => 5,
        'distance_unit' => 'km',
        'course_url' => '',
        'venue' => 'Test Venue',
        'venue_address' => '123 Test Street',
        'venue_latitude' => 14.5995,
        'venue_longitude' => 120.9842,
        'rsvp_required' => false,
        'partners' => [],
    ], $overrides);
}

function date_staff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

// SCH-06 — no past dates

test('an event with today\'s date is accepted', function () {
    $user = date_staff();

    $response = $this->actingAs($user)->post(route('events.store'), date_payload([
        'event_date' => now()->toDateString(),
    ]));

    $response->assertRedirect(route('events.index'));
    $response->assertSessionHasNoErrors();
});

test('an event tomorrow is accepted', function () {
    $user = date_staff();

    $response = $this->actingAs($user)->post(route('events.store'), date_payload([
        'event_date' => now()->addDay()->toDateString(),
    ]));

    $response->assertRedirect(route('events.index'));
    $response->assertSessionHasNoErrors();
});

test('an event yesterday is rejected', function () {
    $user = date_staff();

    $response = $this->actingAs($user)->post(route('events.store'), date_payload([
        'event_date' => now()->subDay()->toDateString(),
    ]));

    $response->assertSessionHasErrors('event_date');
});

test('an event in the past by years is rejected', function () {
    $user = date_staff();

    $response = $this->actingAs($user)->post(route('events.store'), date_payload([
        'event_date' => now()->subYears(5)->toDateString(),
    ]));

    $response->assertSessionHasErrors('event_date');
});

// SCH-07 — no dates more than 2 years out

test('an event just inside 2 years is accepted', function () {
    $user = date_staff();

    $response = $this->actingAs($user)->post(route('events.store'), date_payload([
        'event_date' => now()->addYears(2)->subDay()->toDateString(),
    ]));

    $response->assertRedirect(route('events.index'));
    $response->assertSessionHasNoErrors();
});

test('an event just past 2 years is rejected', function () {
    $user = date_staff();

    $response = $this->actingAs($user)->post(route('events.store'), date_payload([
        'event_date' => now()->addYears(2)->addDay()->toDateString(),
    ]));

    $response->assertSessionHasErrors('event_date');
});

test('an event 3 years out is rejected', function () {
    $user = date_staff();

    $response = $this->actingAs($user)->post(route('events.store'), date_payload([
        'event_date' => now()->addYears(3)->toDateString(),
    ]));

    $response->assertSessionHasErrors('event_date');
});

test('an event 10 years out is rejected', function () {
    $user = date_staff();

    $response = $this->actingAs($user)->post(route('events.store'), date_payload([
        'event_date' => now()->addYears(10)->toDateString(),
    ]));

    $response->assertSessionHasErrors('event_date');
});
