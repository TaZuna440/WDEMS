<?php

use App\Models\Event;
use App\Models\User;

/**
 * Pins the running-event lifecycle guard behavior for church events.
 *
 * Every running-lifecycle guard in Event returns false for a church
 * event, regardless of the underlying EventStatus. Church events use
 * ChurchEventStatus and their own module paths (Services, Fellowship,
 * Discipleship); the running-event edit, registration, attendance,
 * and finalize flows do not apply to them.
 *
 * This is the model-layer half of ISSUE-014 (H1, kind leakage). The
 * controller-layer half is EventController's existing guard checks +
 * the A.3 church_status transition validation.
 *
 * The mirror tests -- running events returning their expected values
 * at each status -- confirm the guards were not over-zealously
 * narrowed.
 *
 * The booted() hook tests confirm Event::create without event_kind
 * produces an in-memory model whose event_kind is 'running', matching
 * what the DB default writes. Without the hook, an in-memory model
 * reads event_kind = null and every guard returns false -- a
 * regression caught by AttendanceDateGateTest before the hook shipped.
 *
 * Helpers prefixed kindguard_ to avoid Pest collisions.
 */

function kindguard_user(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function kindguard_church_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => kindguard_user()->id,
        'event_kind' => Event::KIND_CHURCH,
        'church_status' => 'upcoming',
        'event_type' => 'community_run',
        'event_name' => 'Church Guard Test '.uniqid(),
        'event_date' => today()->toDateString(),
        'status' => 'draft',
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function kindguard_running_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => kindguard_user()->id,
        'event_type' => 'community_run',
        'event_name' => 'Running Guard Test '.uniqid(),
        'event_date' => today()->toDateString(),
        'status' => 'draft',
    ], $overrides));
}

// ---------------------------------------------------------------------------
// Church events: every running-lifecycle guard returns false
// ---------------------------------------------------------------------------

test('church event: canEdit is false at every status', function () {
    foreach (['draft', 'registration_open', 'registration_closed'] as $status) {
        $event = kindguard_church_event(['status' => $status]);
        expect($event->canEdit())->toBeFalse("status={$status}");
    }
});

test('church event: canEditRegistrationForm is false even in draft', function () {
    $event = kindguard_church_event(['status' => 'draft']);
    expect($event->canEditRegistrationForm())->toBeFalse();
});

test('church event: canOpenRegistration is false even with saved form', function () {
    $event = kindguard_church_event([
        'status' => 'draft',
        'registration_form_saved_at' => now(),
    ]);
    expect($event->canOpenRegistration())->toBeFalse();
});

test('church event: canCloseRegistration is false in registration_open', function () {
    $event = kindguard_church_event(['status' => 'registration_open']);
    expect($event->canCloseRegistration())->toBeFalse();
});

test('church event: canFinalize is false in ongoing', function () {
    $event = kindguard_church_event(['status' => 'ongoing']);
    expect($event->canFinalize())->toBeFalse();
});

test('church event: canRecordAttendance is false at every status', function () {
    foreach (['registration_open', 'registration_closed', 'ongoing'] as $status) {
        $event = kindguard_church_event(['status' => $status]);
        expect($event->canRecordAttendance())->toBeFalse("status={$status}");
    }
});

test('church event: canRecordAttendanceNow is false even inside the time window', function () {
    $this->travelTo(today()->setTime(7, 0));
    $event = kindguard_church_event([
        'status' => 'ongoing',
        'start_time' => '06:00',
    ]);
    expect($event->canRecordAttendanceNow())->toBeFalse();
});

test('church event: canViewAttendance is false at every status', function () {
    foreach (['registration_open', 'registration_closed', 'ongoing', 'completed'] as $status) {
        $event = kindguard_church_event([
            'status' => $status,
            'event_date' => today()->subDays(3)->toDateString(),
        ]);
        expect($event->canViewAttendance())->toBeFalse("status={$status}");
    }
});

// ---------------------------------------------------------------------------
// Running events: guards still behave as they did before A.2
// ---------------------------------------------------------------------------

test('running event: canEdit is true in the three editable statuses', function () {
    foreach (['draft', 'registration_open', 'registration_closed'] as $status) {
        $event = kindguard_running_event(['status' => $status]);
        expect($event->canEdit())->toBeTrue("status={$status}");
    }
});

test('running event: canEdit is false in terminal and ongoing statuses', function () {
    foreach (['ongoing', 'completed', 'cancelled'] as $status) {
        $event = kindguard_running_event(['status' => $status]);
        expect($event->canEdit())->toBeFalse("status={$status}");
    }
});

test('running event: canOpenRegistration requires a saved form', function () {
    $withForm = kindguard_running_event([
        'status' => 'draft',
        'registration_form_saved_at' => now(),
    ]);
    $withoutForm = kindguard_running_event(['status' => 'draft']);

    expect($withForm->canOpenRegistration())->toBeTrue();
    expect($withoutForm->canOpenRegistration())->toBeFalse();
});

test('running event: canFinalize is true only in ongoing', function () {
    expect(kindguard_running_event(['status' => 'ongoing'])->canFinalize())->toBeTrue();
    expect(kindguard_running_event(['status' => 'draft'])->canFinalize())->toBeFalse();
    expect(kindguard_running_event(['status' => 'completed'])->canFinalize())->toBeFalse();
});

test('running event: canRecordAttendanceNow behaves as before', function () {
    $this->travelTo(today()->setTime(7, 0));
    $event = kindguard_running_event([
        'status' => 'ongoing',
        'start_time' => '06:00',
    ]);
    expect($event->canRecordAttendanceNow())->toBeTrue();
});

// ---------------------------------------------------------------------------
// booted() hook: event_kind defaults to 'running' on in-memory create
// ---------------------------------------------------------------------------

test('creating an event without event_kind produces in-memory kind = running', function () {
    $event = Event::create([
        'created_by' => kindguard_user()->id,
        'event_type' => 'community_run',
        'event_name' => 'No Kind Test',
        'event_date' => today()->toDateString(),
        'status' => 'draft',
    ]);

    expect($event->event_kind)->toBe(Event::KIND_RUNNING);
    expect($event->isRunningEvent())->toBeTrue();
});

test('creating a church event with explicit event_kind preserves it', function () {
    $event = kindguard_church_event();
    expect($event->event_kind)->toBe(Event::KIND_CHURCH);
    expect($event->isChurchEvent())->toBeTrue();
});
