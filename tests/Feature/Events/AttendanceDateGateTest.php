<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;

/**
 * Covers Phase 6 of the participant-identity-and-monitoring plan
 * (attendance date gate) and the FIX-027 follow-up window change
 * documented in docs/attendance-redesign.md section 3.
 *
 * canRecordAttendanceNow() composes canRecordAttendance() with a
 * time-based window: opens ATTENDANCE_WINDOW_MINUTES_BEFORE before
 * the event's start_time, closes when the status leaves the allowed
 * set. If start_time is null, the window opens at midnight of
 * event_date — the same behavior as the day-granular gate this
 * replaces.
 *
 * Both show() and mark() are gated on canRecordAttendanceNow(). The
 * view gate (canViewAttendance()) is broader — it admits Completed.
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
// Model gate — canRecordAttendanceNow()
// ---------------------------------------------------------------------------

test('a today event in registration_closed can record attendance now', function () {
    expect(attgate_event()->canRecordAttendanceNow())->toBeTrue();
});

test('a today event in registration_open can record attendance now', function () {
    expect(
        attgate_event(['status' => 'registration_open'])->canRecordAttendanceNow()
    )->toBeTrue();
});

test('a past event can record attendance now', function () {
    expect(
        attgate_event(['event_date' => today()->subDays(3)->toDateString()])
            ->canRecordAttendanceNow()
    )->toBeTrue();
});

test('a future event cannot record attendance now', function () {
    expect(
        attgate_event(['event_date' => today()->addDay()->toDateString()])
            ->canRecordAttendanceNow()
    )->toBeFalse();
});

test('a draft event cannot record attendance now', function () {
    expect(
        attgate_event(['status' => 'draft'])->canRecordAttendanceNow()
    )->toBeFalse();
});

// ---------------------------------------------------------------------------
// Time window — opens ATTENDANCE_WINDOW_MINUTES_BEFORE before start_time
// ---------------------------------------------------------------------------

test('the window is open exactly one hour before start_time', function () {
    $this->travelTo(today()->setTime(5, 0));
    $event = attgate_event(['start_time' => '06:00']);

    expect($event->canRecordAttendanceNow())->toBeTrue();
});

test('the window is closed one minute before the buffer opens', function () {
    $this->travelTo(today()->setTime(4, 59));
    $event = attgate_event(['start_time' => '06:00']);

    expect($event->canRecordAttendanceNow())->toBeFalse();
});

test('the window stays open after start_time', function () {
    $this->travelTo(today()->setTime(7, 0));
    $event = attgate_event(['start_time' => '06:00']);

    expect($event->canRecordAttendanceNow())->toBeTrue();
});

test('an event with no start_time opens at midnight of event_date', function () {
    $this->travelTo(today()->setTime(0, 1));
    $event = attgate_event();

    expect($event->canRecordAttendanceNow())->toBeTrue();
});

test('an event starting just after midnight opens the previous evening', function () {
    // Event on day+1 at 00:30. Window opens day 23:30 (start_time
    // minus the 60-minute buffer). "Now" is day 23:45 — the window
    // is open even though event_date is tomorrow.
    $this->travelTo(today()->setTime(23, 45));
    $event = attgate_event([
        'event_date' => today()->addDay()->toDateString(),
        'start_time' => '00:30',
    ]);

    expect($event->canRecordAttendanceNow())->toBeTrue();
});

test('a future event with no start_time is still closed', function () {
    $this->travelTo(today()->setTime(12, 0));
    $event = attgate_event([
        'event_date' => today()->addDay()->toDateString(),
    ]);

    expect($event->canRecordAttendanceNow())->toBeFalse();
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

test('marking attendance is forbidden before the window opens', function () {
    $this->travelTo(today()->setTime(4, 0));
    $staff = attgate_staff();
    $event = attgate_event(['start_time' => '06:00']);
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
