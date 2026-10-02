<?php

use App\Models\Event;
use App\Models\User;

/**
 * Covers the unique-name guard on event create and update.
 *
 * Two events created by the same user cannot share a name while either
 * is still active (not completed, not cancelled). This prevents the
 * "same event created twice" duplicate that produced two Orca
 * Community Run rows on 2026-09-26.
 *
 * Helpers prefixed dup_ to avoid collisions with sibling test files.
 */

function dup_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function dup_payload(array $overrides = []): array
{
    return array_merge([
        'event_type' => 'community_run',
        'event_name' => 'Orca Community Run',
        'event_date' => now()->addWeek()->toDateString(),
        'start_time' => '06:00',
        'distance_value' => 5,
        'distance_unit' => 'km',
        'venue' => 'BGC',
        'venue_address' => 'Bonifacio Global City, Taguig',
    ], $overrides);
}

// ---------------------------------------------------------------------------
// The bug that motivated this file
// ---------------------------------------------------------------------------

test('a second event with the same name by the same user is rejected', function () {
    $staff = dup_staff();

    $this->actingAs($staff)->post('/events', dup_payload());

    $response = $this->actingAs($staff)->post('/events', dup_payload([
        'event_date' => now()->addDays(10)->toDateString(),
    ]));

    $response->assertSessionHasErrors('event_name');
    expect(Event::where('event_name', 'Orca Community Run')->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Normalization
// ---------------------------------------------------------------------------

test('the name comparison is case-insensitive', function () {
    $staff = dup_staff();

    $this->actingAs($staff)->post('/events', dup_payload());

    $this->actingAs($staff)->post('/events', dup_payload([
        'event_name' => 'orca community run',
    ]))->assertSessionHasErrors('event_name');
});

test('the name comparison trims leading and trailing whitespace', function () {
    $staff = dup_staff();

    $this->actingAs($staff)->post('/events', dup_payload());

    $this->actingAs($staff)->post('/events', dup_payload([
        'event_name' => '  Orca Community Run  ',
    ]))->assertSessionHasErrors('event_name');
});

// ---------------------------------------------------------------------------
// Scope: per-user, not global
// ---------------------------------------------------------------------------

test('a different user can use the same event name', function () {
    $staffA = dup_staff();
    $staffB = dup_staff();

    $this->actingAs($staffA)->post('/events', dup_payload());
    $this->actingAs($staffB)->post('/events', dup_payload());

    expect(Event::where('event_name', 'Orca Community Run')->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// Scope: only non-terminal events block
// ---------------------------------------------------------------------------

test('a completed event does not block a new event with the same name', function () {
    $staff = dup_staff();

    Event::create([
        'created_by' => $staff->id,
        'event_type' => 'community_run',
        'event_name' => 'Orca Community Run',
        'event_date' => now()->subMonth()->toDateString(),
        'status' => 'completed',
    ]);

    $this->actingAs($staff)->post('/events', dup_payload())
        ->assertSessionHasNoErrors();

    expect(Event::where('event_name', 'Orca Community Run')->count())->toBe(2);
});

test('a cancelled event does not block a new event with the same name', function () {
    $staff = dup_staff();

    Event::create([
        'created_by' => $staff->id,
        'event_type' => 'community_run',
        'event_name' => 'Orca Community Run',
        'event_date' => now()->addWeek()->toDateString(),
        'status' => 'cancelled',
    ]);

    $this->actingAs($staff)->post('/events', dup_payload())
        ->assertSessionHasNoErrors();
});

// ---------------------------------------------------------------------------
// Update path: the current event must not reject against itself
// ---------------------------------------------------------------------------

test('editing an event does not reject against its own name', function () {
    $staff = dup_staff();

    $this->actingAs($staff)->post('/events', dup_payload());

    $event = Event::where('event_name', 'Orca Community Run')->firstOrFail();

    $this->actingAs($staff)
        ->put("/events/{$event->id}", dup_payload(['description' => 'Updated.']))
        ->assertSessionHasNoErrors();
});

test('editing an event to collide with another active event is rejected', function () {
    $staff = dup_staff();

    $this->actingAs($staff)->post('/events', dup_payload());

    $second = Event::create([
        'created_by' => $staff->id,
        'event_type' => 'community_run',
        'event_name' => 'Other Run',
        'event_date' => now()->addDays(3)->toDateString(),
        'status' => 'draft',
    ]);

    $this->actingAs($staff)
        ->put("/events/{$second->id}", dup_payload())
        ->assertSessionHasErrors('event_name');
});
