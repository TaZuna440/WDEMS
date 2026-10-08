<?php

use App\Enums\AttendanceStatus;
use App\Enums\RegistrationStatus;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers Phase 4 of the registration-development-plan: the attendance
 * rebuild. Search, filter chips, pagination, walk-in, read-only mode.
 *
 * Helpers are prefixed att2_ to avoid Pest function collisions with
 * AttendanceDateGateTest (attgate_) and RegistrationFormTest (regform_).
 */

function att2_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

/**
 * Test event with contact_number and address marked optional by
 * default. This is the shape most tests need — only the walk-in
 * identity check cares about email, and only the "no identity"
 * test needs both to be off (which it overrides).
 *
 * @param array<string, mixed> $overrides
 */
function att2_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => att2_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'Att2 Test Run',
        'event_date' => today()->toDateString(),
        'status' => 'registration_closed',
        'registration_form_saved_at' => now(),
        'registration_common_field_requirements' => [
            'email' => true,
            'contact_number' => false,
            'address' => false,
        ],
    ], $overrides));
}

/**
 * @param array<string, mixed> $overrides
 */
function att2_participant(array $overrides = []): Participant
{
    return Participant::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'age' => 28,
        'email' => 'p'.uniqid().'@example.com',
    ], $overrides));
}

/**
 * @param array<string, mixed> $overrides
 */
function att2_registration(Event $event, ?Participant $participant = null, array $overrides = []): Registration
{
    $participant ??= att2_participant();

    return Registration::create(array_merge([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'registration_date' => now(),
        'registration_status' => RegistrationStatus::Confirmed,
        'source' => 'form',
        'registered_at' => now(),
    ], $overrides));
}

// ---------------------------------------------------------------------------
// Search
// ---------------------------------------------------------------------------

test('search matches participant first name', function () {
    $staff = att2_staff();
    $event = att2_event();

    att2_registration($event, att2_participant(['first_name' => 'Maria', 'last_name' => 'Santos']));
    att2_registration($event, att2_participant(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']));

    $this->actingAs($staff)
        ->get("/events/{$event->id}/attendance?q=Maria")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/attendance')
            ->has('rows', 1)
            ->where('rows.0.participant.full_name', 'Maria Santos')
        );
});

test('search matches participant last name', function () {
    $staff = att2_staff();
    $event = att2_event();

    att2_registration($event, att2_participant(['first_name' => 'Maria', 'last_name' => 'Santos']));
    att2_registration($event, att2_participant(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']));

    $this->actingAs($staff)
        ->get("/events/{$event->id}/attendance?q=Dela")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rows', 1)
            ->where('rows.0.participant.full_name', 'Juan Dela Cruz')
        );
});

test('search matches participant email', function () {
    $staff = att2_staff();
    $event = att2_event();

    att2_registration($event, att2_participant(['email' => 'findme@example.com']));
    att2_registration($event, att2_participant(['email' => 'other@example.com']));

    $this->actingAs($staff)
        ->get("/events/{$event->id}/attendance?q=findme")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('rows', 1));
});

test('search matches participant contact number', function () {
    $staff = att2_staff();
    $event = att2_event();

    att2_registration($event, att2_participant(['contact_number' => '09171234567']));
    att2_registration($event, att2_participant(['contact_number' => '09289999999']));

    $this->actingAs($staff)
        ->get("/events/{$event->id}/attendance?q=0917")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('rows', 1));
});

// ---------------------------------------------------------------------------
// Filter chips
// ---------------------------------------------------------------------------

test('filter unmarked returns registrations without an attendance row', function () {
    $staff = att2_staff();
    $event = att2_event();

    $a = att2_registration($event);
    $b = att2_registration($event);
    Attendance::create([
        'registration_id' => $b->id,
        'attendance_status' => AttendanceStatus::Present,
        'attendance_time' => now(),
        'recorded_by' => $staff->id,
    ]);

    $this->actingAs($staff)
        ->get("/events/{$event->id}/attendance?filter=unmarked")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rows', 1)
            ->where('rows.0.id', $a->id)
        );
});

test('filter present returns only present attendance rows', function () {
    $staff = att2_staff();
    $event = att2_event();

    $present = att2_registration($event);
    $absent = att2_registration($event);

    Attendance::create([
        'registration_id' => $present->id,
        'attendance_status' => AttendanceStatus::Present,
        'attendance_time' => now(),
        'recorded_by' => $staff->id,
    ]);
    Attendance::create([
        'registration_id' => $absent->id,
        'attendance_status' => AttendanceStatus::Absent,
        'recorded_by' => $staff->id,
    ]);

    $this->actingAs($staff)
        ->get("/events/{$event->id}/attendance?filter=present")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rows', 1)
            ->where('rows.0.attendance.status', 'present')
        );
});

test('filter counts reflect current search', function () {
    $staff = att2_staff();
    $event = att2_event();

    $maria = att2_participant(['first_name' => 'Maria']);
    $maria2 = att2_participant(['first_name' => 'Maria']);

    $r1 = att2_registration($event, $maria);
    att2_registration($event, $maria2);
    Attendance::create([
        'registration_id' => $r1->id,
        'attendance_status' => AttendanceStatus::Present,
        'attendance_time' => now(),
        'recorded_by' => $staff->id,
    ]);

    att2_registration($event, att2_participant(['first_name' => 'Juan']));
    att2_registration($event, att2_participant(['first_name' => 'Pedro']));

    $this->actingAs($staff)
        ->get("/events/{$event->id}/attendance?q=Maria")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('counts.all', 2)
            ->where('counts.present', 1)
            ->where('counts.unmarked', 1)
        );
});

test('invalid filter value falls back to all', function () {
    $staff = att2_staff();
    $event = att2_event();
    att2_registration($event);
    att2_registration($event);

    $this->actingAs($staff)
        ->get("/events/{$event->id}/attendance?filter=garbage")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.filter', 'all')
            ->has('rows', 2)
        );
});

// ---------------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------------

test('pagination splits results at 50 per page', function () {
    $staff = att2_staff();
    $event = att2_event();

    for ($i = 0; $i < 51; $i++) {
        att2_registration($event);
    }

    $this->actingAs($staff)
        ->get("/events/{$event->id}/attendance")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pagination.current_page', 1)
            ->where('pagination.last_page', 2)
            ->where('pagination.total', 51)
            ->has('rows', 50)
        );

    $this->actingAs($staff)
        ->get("/events/{$event->id}/attendance?page=2")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pagination.current_page', 2)
            ->has('rows', 1)
        );
});

// ---------------------------------------------------------------------------
// Gate — canViewAttendance
// ---------------------------------------------------------------------------

test('completed events are viewable but not markable', function () {
    $staff = att2_staff();
    $event = att2_event([
        'status' => 'completed',
        'event_date' => today()->subDays(3)->toDateString(),
    ]);
    att2_registration($event);

    $this->actingAs($staff)
        ->get("/events/{$event->id}/attendance")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can_mark', false)
            ->has('rows', 1)
        );
});

test('draft events return 403 on the attendance page', function () {
    $staff = att2_staff();
    $event = att2_event(['status' => 'draft']);

    $this->actingAs($staff)
        ->get("/events/{$event->id}/attendance")
        ->assertForbidden();
});

test('future events return 403 on the attendance page', function () {
    $staff = att2_staff();
    $event = att2_event(['event_date' => today()->addDays(3)->toDateString()]);

    $this->actingAs($staff)
        ->get("/events/{$event->id}/attendance")
        ->assertForbidden();
});

test('marking attendance on a completed event returns 403', function () {
    $staff = att2_staff();
    $event = att2_event([
        'status' => 'completed',
        'event_date' => today()->subDays(3)->toDateString(),
    ]);
    $registration = att2_registration($event);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/{$registration->id}/mark", [
            'status' => 'present',
        ])
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Mark
// ---------------------------------------------------------------------------

test('marking attendance creates an attendance row', function () {
    $staff = att2_staff();
    $event = att2_event();
    $registration = att2_registration($event);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/{$registration->id}/mark", [
            'status' => 'late',
            'notes' => 'Arrived 10 minutes after start',
        ])
        ->assertRedirect();

    $attendance = $registration->fresh()->attendance;

    expect($attendance)->not->toBeNull();
    expect($attendance->attendance_status)->toBe(AttendanceStatus::Late);
    expect($attendance->notes)->toBe('Arrived 10 minutes after start');
});

test('marking attendance for a registration belonging to another event returns 404', function () {
    $staff = att2_staff();
    $eventA = att2_event();
    $eventB = att2_event();
    $registration = att2_registration($eventB);

    $this->actingAs($staff)
        ->post("/events/{$eventA->id}/attendance/{$registration->id}/mark", [
            'status' => 'present',
        ])
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// Walk-in
// ---------------------------------------------------------------------------

test('walk-in creates participant, registration, and attendance', function () {
    $staff = att2_staff();
    $event = att2_event();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", [
            'first_name' => 'Walk',
            'last_name' => 'In',
            'age' => 30,
            'email' => 'walkin@example.com',
            'mark_present' => true,
        ])
        ->assertRedirect(route('events.attendance', $event));

    $participant = Participant::where('email', 'walkin@example.com')->first();
    expect($participant)->not->toBeNull();
    expect($participant->first_name)->toBe('Walk');

    $registration = Registration::where('event_id', $event->id)
        ->where('participant_id', $participant->id)
        ->first();
    expect($registration)->not->toBeNull();
    expect($registration->source)->toBe('walk_in');

    expect($registration->attendance)->not->toBeNull();
    expect($registration->attendance->attendance_status)->toBe(AttendanceStatus::Present);
});

test('walk-in defaults to marking present when flag is omitted', function () {
    $staff = att2_staff();
    $event = att2_event();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", [
            'first_name' => 'Walk',
            'last_name' => 'In',
            'age' => 30,
            'email' => 'walkin-default@example.com',
        ])
        ->assertRedirect();

    $participant = Participant::where('email', 'walkin-default@example.com')->firstOrFail();
    $registration = Registration::where('participant_id', $participant->id)->firstOrFail();

    expect($registration->attendance)->not->toBeNull();
    expect($registration->attendance->attendance_status)->toBe(AttendanceStatus::Present);
});

test('walk-in without mark_present does not create an attendance row', function () {
    $staff = att2_staff();
    $event = att2_event();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", [
            'first_name' => 'Walk',
            'last_name' => 'In',
            'age' => 30,
            'email' => 'walkin-nomark@example.com',
            'mark_present' => false,
        ])
        ->assertRedirect();

    $participant = Participant::where('email', 'walkin-nomark@example.com')->firstOrFail();
    $registration = Registration::where('participant_id', $participant->id)->firstOrFail();

    expect($registration->attendance)->toBeNull();
});

test('walk-in rejects a person already registered for the event', function () {
    $staff = att2_staff();
    $event = att2_event();

    $existing = att2_participant(['email' => 'already@example.com']);
    att2_registration($event, $existing);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", [
            'first_name' => 'Someone',
            'last_name' => 'Else',
            'age' => 30,
            'email' => 'already@example.com',
        ])
        ->assertSessionHasErrors('identity');
});

test('walk-in rejects a submission with no email and no phone', function () {
    $staff = att2_staff();
    $event = att2_event([
        'registration_common_field_requirements' => [
            'email' => false,
            'contact_number' => false,
            'address' => false,
        ],
    ]);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", [
            'first_name' => 'No',
            'last_name' => 'Identity',
            'age' => 30,
        ])
        ->assertSessionHasErrors('identity');
});

test('walk-in on a completed event returns 403', function () {
    $staff = att2_staff();
    $event = att2_event([
        'status' => 'completed',
        'event_date' => today()->subDays(3)->toDateString(),
    ]);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", [
            'first_name' => 'Walk',
            'last_name' => 'In',
            'age' => 30,
            'email' => 'nope@example.com',
        ])
        ->assertForbidden();
});
