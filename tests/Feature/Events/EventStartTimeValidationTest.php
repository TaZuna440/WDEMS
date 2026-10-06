<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Date;

/**
 * Covers the start-time-in-the-future check added to
 * EventValidationRules::validateEventStartIsInFuture().
 *
 * `after_or_equal:today` on event_date allows today. This rule
 * rejects the case where the combined date + time has already
 * passed — e.g. today at 09:00 when it is 14:00.
 *
 * Helpers prefixed start_future_ to avoid Pest collisions.
 */

function start_future_staff(): User
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
function start_future_payload(array $overrides = []): array
{
    return array_merge([
        'event_type' => 'community_run',
        'event_name' => 'Start Future Test Run',
        'event_date' => now()->addWeek()->toDateString(),
        'start_time' => '06:00',
        'distance_value' => 5,
        'distance_unit' => 'km',
        'venue' => 'BGC',
        'venue_address' => 'Bonifacio Global City, Taguig',
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Rejected — combined datetime in the past
// ---------------------------------------------------------------------------

test('today with a start_time already past is rejected', function () {
    $staff = start_future_staff();

    Date::setTestNow('2026-06-15 14:00:00');

    $response = $this->actingAs($staff)->post('/events', start_future_payload([
        'event_date' => '2026-06-15',
        'start_time' => '09:00',
    ]));

    Date::setTestNow();

    $response->assertSessionHasErrors('start_time');
    expect(Event::count())->toBe(0);
});

test('today at midnight when it is later is rejected', function () {
    $staff = start_future_staff();

    Date::setTestNow('2026-06-15 08:00:00');

    $response = $this->actingAs($staff)->post('/events', start_future_payload([
        'event_date' => '2026-06-15',
        'start_time' => '00:01',
    ]));

    Date::setTestNow();

    $response->assertSessionHasErrors('start_time');
});

// ---------------------------------------------------------------------------
// Accepted — combined datetime in the future
// ---------------------------------------------------------------------------

test('today with a start_time still in the future is accepted', function () {
    $staff = start_future_staff();

    Date::setTestNow('2026-06-15 06:00:00');

    $response = $this->actingAs($staff)->post('/events', start_future_payload([
        'event_date' => '2026-06-15',
        'start_time' => '18:00',
    ]));

    Date::setTestNow();

    $response->assertSessionHasNoErrors();
    expect(Event::count())->toBe(1);
});

test('a future date with an early start_time is accepted', function () {
    $staff = start_future_staff();

    Date::setTestNow('2026-06-15 14:00:00');

    $response = $this->actingAs($staff)->post('/events', start_future_payload([
        'event_date' => '2026-07-01',
        'start_time' => '06:00',
    ]));

    Date::setTestNow();

    $response->assertSessionHasNoErrors();
    expect(Event::count())->toBe(1);
});

// ---------------------------------------------------------------------------
// No double-reporting — other rules win first
// ---------------------------------------------------------------------------

test('a past date is rejected by the date rule, not the start-time rule', function () {
    $staff = start_future_staff();

    Date::setTestNow('2026-06-15 14:00:00');

    $response = $this->actingAs($staff)->post('/events', start_future_payload([
        'event_date' => '2026-06-01',
        'start_time' => '06:00',
    ]));

    Date::setTestNow();

    $response->assertSessionHasErrors('event_date');
    expect(session('errors')->has('start_time'))->toBeFalse();
});
