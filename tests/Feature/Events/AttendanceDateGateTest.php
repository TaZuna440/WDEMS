<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;

/**
 * Covers Phase 6 of the participant-identity-and-monitoring plan:
 * the attendance date gate and the /attendance directory page.
 *
 * canRecordAttendanceToday() composes canRecordAttendance() with
 * event_date <= today. Both show() and mark() are gated.
 *
 * Helpers prefixed attgate_ to avoid Pest collisions.
 */

function attgate_staff(): User
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
function attgate_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => attgate_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'Attgate Test Run',
        'event_date' => today()->toDateString(),
        'status' => 'registration_closed',
    ], $overrides));
}

function attgate_registration(Event $event): Registration
{
    $participant = Participant::create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'age' => 28,
        'email' => 'maria'.uniqid().'@example.com',
    ]);

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
// Model gate — canRecordAttendanceToday()
// ---------------------------------------------------------------------------

test('a today event in registration_closed can record attendance today', function () {
    expect(attgate_event()->canRecordAttendanceToday())->toBeTrue();
});

test('a today event in registration_open can record attendance today', function () {
    expect(
        attgate_event(['status' => 'registration_open'])->canRecordAttendanceToday()
    )->toBeTrue();
});

test('a past event can record attendance today', function () {
    expect(
        attgate_event(['event_date' => today()->subDays(3)->toDateString()])
            ->canRecordAttendanceToday()
    )->toBeTrue();
});

test('a future event cannot record attendance today', function () {
    expect(
        attgate_event(['event_date' => today()->addDay()->toDateString()])
            ->canRecordAttendanceToday()
    )->toBeFalse();
});

test('a draft event cannot record attendance today', function () {
    expect(
        attgate_event(['status' => 'draft'])->canRecordAttendanceToday()
    )->toBeFalse();
});

// ---------------------------------------------------------------------------
// Route gate — show()
// ---------------------------------------------------------------------------

test('the attendance page is reachable for a today event', function () {
    $staff = attgate_staff();
    $event = attgate_event();

    $this->actingAs($staff)
        ->get("/events/{$event->id}/attendance")
        ->assertOk();
});

test('the attendance page is forbidden for a future event', function () {
    $staff = attgate_staff();
    $event = attgate_event(['event_date' => today()->addDay()->toDateString()]);

    $this->actingAs($staff)
        ->get("/events/{$event->id}/attendance")
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Route gate — mark()
// ---------------------------------------------------------------------------

test('marking attendance is forbidden for a future event', function () {
    $staff = attgate_staff();
    $event = attgate_event(['event_date' => today()->addDay()->toDateString()]);
    $registration = attgate_registration($event);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/{$registration->id}/mark", [
            'status' => 'present',
        ])
        ->assertForbidden();
});

test('marking attendance succeeds for a today event', function () {
    $staff = attgate_staff();
    $event = attgate_event();
    $registration = attgate_registration($event);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/{$registration->id}/mark", [
            'status' => 'present',
        ])
        ->assertRedirect();

    expect($registration->fresh()->attendance)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Directory page — /attendance
// ---------------------------------------------------------------------------

test('the directory lists today and recent events', function () {
    $staff = attgate_staff();
    attgate_event([
        'event_name' => 'Today Run',
        'event_date' => today()->toDateString(),
    ]);
    attgate_event([
        'event_name' => 'Recent Run',
        'event_date' => today()->subDays(3)->toDateString(),
    ]);

    $this->actingAs($staff)
        ->get('/attendance')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendance/index')
            ->has('today', 1)
            ->has('recent', 1)
            ->where('today.0.event_name', 'Today Run')
            ->where('recent.0.event_name', 'Recent Run')
        );
});

test('the directory does not list future events', function () {
    $staff = attgate_staff();
    attgate_event(['event_date' => today()->addDay()->toDateString()]);

    $this->actingAs($staff)
        ->get('/attendance')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendance/index')
            ->has('today', 0)
            ->has('recent', 0)
        );
});

test('the directory does not list draft events', function () {
    $staff = attgate_staff();
    attgate_event([
        'event_date' => today()->toDateString(),
        'status' => 'draft',
    ]);

    $this->actingAs($staff)
        ->get('/attendance')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendance/index')
            ->has('today', 0)
            ->has('recent', 0)
        );
});

test('the directory does not list completed events', function () {
    $staff = attgate_staff();
    attgate_event([
        'event_date' => today()->subDays(10)->toDateString(),
        'status' => 'completed',
    ]);

    $this->actingAs($staff)
        ->get('/attendance')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendance/index')
            ->has('today', 0)
            ->has('recent', 0)
        );
});
