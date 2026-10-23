<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use App\Services\EventWorkflow;

/**
 * Pins the running-event lifecycle guard behavior for church events.
 *
 * Six of the seven can*() guards in Event are running-only and return
 * false for a church event regardless of the underlying EventStatus:
 *
 *   - canEditRegistrationForm
 *   - canOpenRegistration
 *   - canCloseRegistration
 *   - canFinalize
 *   - canRecordAttendance
 *   - canViewAttendance
 *
 * The seventh guard -- canEdit -- applies to both kinds with a
 * per-kind rule. Church events are editable when church_status is
 * Upcoming or Ongoing; not editable when Completed or Cancelled.
 * Running events keep their original editable states (Draft,
 * RegistrationOpen, RegistrationClosed).
 *
 * This is the model-layer half of ISSUE-014 (H1, kind leakage). The
 * controller-layer half is EventController's existing guard checks +
 * the church_status transition validation added by A.3b.
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

test('church events cannot transition through the running workflow service', function () {
    $event = kindguard_church_event();
    $workflow = app(EventWorkflow::class);

    expect($workflow->canTransition($event, EventStatus::RegistrationOpen))->toBeFalse();
    expect(fn () => $workflow->openRegistration($event))->toThrow(InvalidArgumentException::class);
    expect($event->fresh()->status->value)->toBe('draft');
});

test('church events do not auto-advance through running statuses', function () {
    $event = kindguard_church_event(['status' => 'registration_closed', 'event_date' => today()->subDay()]);

    $this->actingAs(kindguard_user())->get(route('events.show', $event))->assertOk();

    expect($event->fresh()->status->value)->toBe('registration_closed')
        ->and($event->fresh()->church_status->value)->toBe('upcoming');
});

test('church events cannot expose running registration or monitor pages', function () {
    $event = kindguard_church_event(['status' => 'registration_open', 'registration_slug' => 'church-test']);

    $this->get('/r/church-test')->assertNotFound();
    $this->get('/r/church-test/registered')->assertNotFound();
    $this->actingAs(kindguard_user())->get(route('registration-monitor.show', $event))->assertNotFound();
    $this->get(route('registration-monitor.export', $event))->assertNotFound();
});

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
// Church events: the six running-only guards return false regardless of status
// ---------------------------------------------------------------------------

test('church event: canEdit follows church_status, not running status', function () {
    // Editable states for the church lifecycle.
    expect(kindguard_church_event(['church_status' => 'upcoming'])->canEdit())->toBeTrue();
    expect(kindguard_church_event(['church_status' => 'ongoing'])->canEdit())->toBeTrue();

    // Terminal church states -- not editable.
    expect(kindguard_church_event(['church_status' => 'completed'])->canEdit())->toBeFalse();
    expect(kindguard_church_event(['church_status' => 'cancelled'])->canEdit())->toBeFalse();
});

test('church event: canEdit does not fall through to the running status column', function () {
    // Even with a running status that would normally be editable
    // (draft), a completed church event must NOT be editable. And
    // even with a running status that would normally be locked
    // (completed), an upcoming church event MUST be editable.
    $completedChurchWithRunningDraft = kindguard_church_event([
        'church_status' => 'completed',
        'status' => 'draft',
    ]);
    $upcomingChurchWithRunningCompleted = kindguard_church_event([
        'church_status' => 'upcoming',
        'status' => 'completed',
    ]);

    expect($completedChurchWithRunningDraft->canEdit())->toBeFalse();
    expect($upcomingChurchWithRunningCompleted->canEdit())->toBeTrue();
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

test('running event: canEdit ignores church_status', function () {
    // A running event with a populated church_status (unexpected but
    // possible via a data import) must still follow the running rule.
    $event = kindguard_running_event([
        'status' => 'draft',
        'church_status' => 'completed',
    ]);
    expect($event->canEdit())->toBeTrue();
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
    expect(kindguard_running_event([
        'status' => 'ongoing',
        'event_date' => today(),
        'start_time' => now()->subHours(2)->format('H:i'),
    ])->canFinalize())->toBeTrue();
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
