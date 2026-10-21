<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * Pins the behavior of AdvanceEventStatus middleware.
 *
 * The middleware runs on the main authenticated group, after auth
 * and device.trusted. On a request that binds an Event in
 * RegistrationClosed whose event_date + start_time has passed, it
 * calls EventWorkflow::start(). Otherwise it passes through
 * silently. Idempotent — a second request is a no-op.
 *
 * /events/{id} is used as the trigger route because it is the
 * simplest authenticated route that binds an Event.
 *
 * Helpers are prefixed advance_ to avoid Pest function collisions.
 */
function advance_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function advance_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => advance_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'Advance Test Run',
        'event_date' => today()->toDateString(),
        'status' => 'registration_closed',
        'registration_form_saved_at' => now(),
    ], $overrides));
}

it('advances a registration_closed event to ongoing when start moment has passed', function () {
    Carbon::setTestNow('2026-10-14 12:00:00');

    $event = advance_event([
        'event_date' => '2026-10-14',
        'start_time' => '08:00',
    ]);
    $staff = advance_staff();

    $this->actingAs($staff)
        ->get("/events/{$event->id}")
        ->assertOk();

    expect($event->fresh()->status)->toBe(EventStatus::Ongoing);

    Carbon::setTestNow();
});

it('does not advance when start moment is in the future', function () {
    Carbon::setTestNow('2026-10-14 06:00:00');

    $event = advance_event([
        'event_date' => '2026-10-14',
        'start_time' => '08:00',
    ]);
    $staff = advance_staff();

    $this->actingAs($staff)
        ->get("/events/{$event->id}")
        ->assertOk();

    expect($event->fresh()->status)->toBe(EventStatus::RegistrationClosed);

    Carbon::setTestNow();
});

it('falls back to midnight when start_time is null', function () {
    Carbon::setTestNow('2026-10-14 12:00:00');

    $event = advance_event([
        'event_date' => '2026-10-14',
        'start_time' => null,
    ]);
    $staff = advance_staff();

    $this->actingAs($staff)
        ->get("/events/{$event->id}")
        ->assertOk();

    expect($event->fresh()->status)->toBe(EventStatus::Ongoing);

    Carbon::setTestNow();
});

it('does not advance when event_date is future and start_time is null', function () {
    Carbon::setTestNow('2026-10-14 12:00:00');

    $event = advance_event([
        'event_date' => '2026-10-15',
        'start_time' => null,
    ]);
    $staff = advance_staff();

    $this->actingAs($staff)
        ->get("/events/{$event->id}")
        ->assertOk();

    expect($event->fresh()->status)->toBe(EventStatus::RegistrationClosed);

    Carbon::setTestNow();
});

it('does not advance a draft event', function () {
    Carbon::setTestNow('2026-10-14 12:00:00');

    $event = advance_event([
        'event_date' => '2026-10-14',
        'start_time' => '08:00',
        'status' => 'draft',
    ]);
    $staff = advance_staff();

    $this->actingAs($staff)
        ->get("/events/{$event->id}")
        ->assertOk();

    expect($event->fresh()->status)->toBe(EventStatus::Draft);

    Carbon::setTestNow();
});

it('starts an open event while keeping on-site registration open', function () {
    Carbon::setTestNow('2026-10-14 12:00:00');

    $event = advance_event([
        'event_date' => '2026-10-14',
        'start_time' => '08:00',
        'status' => 'registration_open',
    ]);
    $staff = advance_staff();

    $this->actingAs($staff)
        ->get("/events/{$event->id}")
        ->assertOk();

    expect($event->fresh()->status)->toBe(EventStatus::Ongoing)
        ->and($event->fresh()->acceptsPublicRegistration())->toBeTrue();

    Carbon::setTestNow();
});

it('does not advance an ongoing event', function () {
    Carbon::setTestNow('2026-10-14 12:00:00');

    $event = advance_event([
        'event_date' => '2026-10-14',
        'start_time' => '08:00',
        'status' => 'ongoing',
    ]);
    $staff = advance_staff();

    $this->actingAs($staff)
        ->get("/events/{$event->id}")
        ->assertOk();

    expect($event->fresh()->status)->toBe(EventStatus::Ongoing);

    Carbon::setTestNow();
});

it('does not advance a completed event', function () {
    Carbon::setTestNow('2026-10-14 12:00:00');

    $event = advance_event([
        'event_date' => '2026-10-14',
        'start_time' => '08:00',
        'status' => 'completed',
    ]);
    $staff = advance_staff();

    $this->actingAs($staff)
        ->get("/events/{$event->id}")
        ->assertOk();

    expect($event->fresh()->status)->toBe(EventStatus::Completed);

    Carbon::setTestNow();
});

it('is idempotent — a second request does not throw', function () {
    Carbon::setTestNow('2026-10-14 12:00:00');

    $event = advance_event([
        'event_date' => '2026-10-14',
        'start_time' => '08:00',
    ]);
    $staff = advance_staff();

    $this->actingAs($staff)->get("/events/{$event->id}")->assertOk();
    $this->actingAs($staff)->get("/events/{$event->id}")->assertOk();

    expect($event->fresh()->status)->toBe(EventStatus::Ongoing);

    Carbon::setTestNow();
});

it('does not advance an event when the request is unauthenticated', function () {
    Carbon::setTestNow('2026-10-14 12:00:00');

    $event = advance_event([
        'event_date' => '2026-10-14',
        'start_time' => '08:00',
    ]);

    // The middleware runs after auth — an unauthenticated request
    // never reaches it. The route redirects to login.
    $this->get("/events/{$event->id}")->assertRedirect('/login');

    expect($event->fresh()->status)->toBe(EventStatus::RegistrationClosed);

    Carbon::setTestNow();
});
