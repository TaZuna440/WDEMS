<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

/**
 * Pins the behavior of EventController::finalize().
 *
 * Finalization moves an event from Ongoing to Completed. It is
 * password-gated at the route via RequirePassword middleware, but
 * the tests set the session key directly so the middleware passes
 * through — the middleware itself is exercised, not mocked away.
 *
 * Terminal: a completed event cannot be reopened. A second POST
 * returns 403 because the status is no longer Ongoing.
 *
 * The `AdvanceEventStatus` middleware runs on this route. Events
 * with a past start moment and status `registration_closed` get
 * advanced to `ongoing` before the controller runs. Any test whose
 * subject is a non-ongoing state must use a future event_date so
 * the middleware stays out of the way.
 *
 * Helpers are prefixed finalize_ to avoid Pest function collisions.
 */

function finalize_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

function finalize_admin(): User
{
    return User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);
}

/**
 * @param array<string, mixed> $overrides
 */
function finalize_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => finalize_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'Finalize Test Run',
        'event_date' => today()->toDateString(),
        'status' => 'ongoing',
        'registration_form_saved_at' => now(),
    ], $overrides));
}

it('lets staff finalize an ongoing event', function () {
    $event = finalize_event(['status' => 'ongoing']);
    $staff = finalize_staff();

    $this->actingAs($staff)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post("/events/{$event->id}/finalize")
        ->assertRedirect();

    expect($event->fresh()->status)->toBe(EventStatus::Completed);
});

it('lets admin finalize an ongoing event', function () {
    $event = finalize_event(['status' => 'ongoing']);
    $admin = finalize_admin();

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post("/events/{$event->id}/finalize")
        ->assertRedirect();

    expect($event->fresh()->status)->toBe(EventStatus::Completed);
});

it('rejects finalize on a draft event', function () {
    $event = finalize_event(['status' => 'draft']);
    $staff = finalize_staff();

    $this->actingAs($staff)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post("/events/{$event->id}/finalize")
        ->assertForbidden();

    expect($event->fresh()->status)->toBe(EventStatus::Draft);
});

it('rejects finalize on a registration_open event', function () {
    $event = finalize_event(['status' => 'registration_open']);
    $staff = finalize_staff();

    $this->actingAs($staff)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post("/events/{$event->id}/finalize")
        ->assertForbidden();

    expect($event->fresh()->status)->toBe(EventStatus::RegistrationOpen);
});

it('rejects finalize on a registration_closed event', function () {
    // Future event_date so AdvanceEventStatus middleware does not
    // fire and advance the event to ongoing before the controller
    // sees it. The subject here is the controller's status guard,
    // not the middleware.
    $event = finalize_event([
        'status' => 'registration_closed',
        'event_date' => today()->addDay()->toDateString(),
    ]);
    $staff = finalize_staff();

    $this->actingAs($staff)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post("/events/{$event->id}/finalize")
        ->assertForbidden();

    expect($event->fresh()->status)->toBe(EventStatus::RegistrationClosed);
});

it('rejects a second finalize on a completed event', function () {
    $event = finalize_event(['status' => 'ongoing']);
    $staff = finalize_staff();

    $this->actingAs($staff)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post("/events/{$event->id}/finalize")
        ->assertRedirect();

    expect($event->fresh()->status)->toBe(EventStatus::Completed);

    // Status is now Completed. The guard rejects a second finalize.
    $this->actingAs($staff)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post("/events/{$event->id}/finalize")
        ->assertForbidden();
});

it('rejects finalize on a cancelled event', function () {
    $event = finalize_event(['status' => 'cancelled']);
    $staff = finalize_staff();

    $this->actingAs($staff)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post("/events/{$event->id}/finalize")
        ->assertForbidden();

    expect($event->fresh()->status)->toBe(EventStatus::Cancelled);
});

it('redirects unauthenticated requests to login', function () {
    $event = finalize_event(['status' => 'ongoing']);

    $this->post("/events/{$event->id}/finalize")
        ->assertRedirect('/login');

    expect($event->fresh()->status)->toBe(EventStatus::Ongoing);
});

it('flashes a success message after finalize', function () {
    $event = finalize_event(['status' => 'ongoing']);
    $staff = finalize_staff();

    $this->actingAs($staff)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post("/events/{$event->id}/finalize")
        ->assertSessionHas('success');
});

it('logs event.finalized with the actor and event id', function () {
    Log::spy();

    $event = finalize_event(['status' => 'ongoing']);
    $staff = finalize_staff();

    $this->actingAs($staff)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post("/events/{$event->id}/finalize")
        ->assertRedirect();

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context) use ($event, $staff) {
            return $message === 'event.finalized'
                && $context['event_id'] === $event->id
                && $context['actor_id'] === $staff->id;
        })
        ->once();
});
