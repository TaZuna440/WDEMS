<?php

use App\Enums\ServiceType;
use App\Models\FirstTimer;
use App\Models\Member;
use App\Models\Participant;
use App\Models\ServiceAttendanceFirstTimer;
use App\Models\ServiceAttendanceMember;
use App\Models\ServiceOccurrence;
use App\Models\User;

beforeEach(function () {
    $this->staff = User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
    ]);

    $this->occurrence = ServiceOccurrence::create([
        'service_type' => ServiceType::Morning->value,
        'occurrence_date' => now()->toDateString(),
        'start_time' => '10:00',
        'end_time' => '12:00',
        'created_by' => $this->staff->id,
    ]);
});

function makeParticipant(array $overrides = []): Participant
{
    static $counter = 0;
    $counter++;

    return Participant::create(array_merge([
        'first_name' => 'Test',
        'last_name' => 'Participant'.$counter,
        'email' => 'test-'.$counter.'-'.uniqid().'@example.com',
    ], $overrides));
}

test('adding a family member does not select another participant with the same phone', function () {
    $details = ['contact_number' => '09171234567'];
    $parent = makeParticipant(array_merge($details, ['first_name' => 'Maria', 'last_name' => 'Santos']));

    $this->actingAs($this->staff)
        ->post(route('services.attendance.store', ['occurrence' => $this->occurrence->id]), array_merge($details, [
            'track' => 'member',
            'first_name' => 'Juan',
            'last_name' => 'Santos',
            'age' => 15,
        ]))
        ->assertRedirect()->assertSessionHasNoErrors();

    $attendance = ServiceAttendanceMember::sole();
    expect($attendance->participant_id)->not->toBe($parent->id)
        ->and(Participant::findOrFail($attendance->participant_id)->first_name)->toBe('Juan');
});

// ----------------------------------------------------------------
// Show
// ----------------------------------------------------------------

test('the attendance page renders with both tracks empty', function () {
    $this->actingAs($this->staff)
        ->get(route('services.attendance.show', ['occurrence' => $this->occurrence->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('services/attendance')
            ->where('occurrence.id', $this->occurrence->id)
            ->has('members', 0)
            ->has('first_timers', 0)
        );
});

test('the attendance page lists members and first-timers separately', function () {
    $member = makeParticipant();
    $firstTimer = makeParticipant();

    ServiceAttendanceMember::create([
        'service_occurrence_id' => $this->occurrence->id,
        'participant_id' => $member->id,
        'attendance_status' => 'present',
    ]);
    ServiceAttendanceFirstTimer::create([
        'service_occurrence_id' => $this->occurrence->id,
        'participant_id' => $firstTimer->id,
        'invited_by' => 'Maria',
        'attendance_status' => 'present',
    ]);

    $this->actingAs($this->staff)
        ->get(route('services.attendance.show', ['occurrence' => $this->occurrence->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('members', 1)
            ->has('first_timers', 1)
            ->where('members.0.participant_id', $member->id)
            ->where('first_timers.0.participant_id', $firstTimer->id)
            ->where('first_timers.0.invited_by', 'Maria')
        );
});

// ----------------------------------------------------------------
// Store — Members track
// ----------------------------------------------------------------

test('a new participant can be added to the members track', function () {
    $this->actingAs($this->staff)
        ->post(route('services.attendance.store', ['occurrence' => $this->occurrence->id]), [
            'track' => 'member',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'age' => 30,
            'birthday' => '1995-01-15',
        ])
        ->assertRedirect();

    expect(ServiceAttendanceMember::count())->toBe(1);
    $row = ServiceAttendanceMember::first();
    expect($row->attendance_status)->toBe('present');
    expect($row->participant->fullName())->toBe('Juan Dela Cruz');
});

test('an existing participant can be added to the members track by id', function () {
    $participant = makeParticipant(['first_name' => 'Existing', 'last_name' => 'Member']);

    $this->actingAs($this->staff)
        ->post(route('services.attendance.store', ['occurrence' => $this->occurrence->id]), [
            'track' => 'member',
            'participant_id' => $participant->id,
        ])
        ->assertRedirect();

    expect(ServiceAttendanceMember::count())->toBe(1);
    expect(ServiceAttendanceMember::first()->participant_id)->toBe($participant->id);
});

// ----------------------------------------------------------------
// Store — First-Timers track
// ----------------------------------------------------------------

test('a new participant can be added to the first-timers track', function () {
    $this->actingAs($this->staff)
        ->post(route('services.attendance.store', ['occurrence' => $this->occurrence->id]), [
            'track' => 'first_timer',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'invited_by' => 'Ana',
        ])
        ->assertRedirect();

    expect(ServiceAttendanceFirstTimer::count())->toBe(1);
    $row = ServiceAttendanceFirstTimer::first();
    expect($row->invited_by)->toBe('Ana');
    expect($row->attendance_status)->toBe('present');
});

test('a first-timer record is created automatically on first-ever first-timer attendance', function () {
    $this->actingAs($this->staff)
        ->post(route('services.attendance.store', ['occurrence' => $this->occurrence->id]), [
            'track' => 'first_timer',
            'first_name' => 'New',
            'last_name' => 'Visitor',
            'invited_by' => 'Friend',
        ])
        ->assertRedirect();

    expect(FirstTimer::count())->toBe(1);
    $firstTimer = FirstTimer::first();
    expect($firstTimer->first_service_date->toDateString())->toBe(now()->toDateString());
});

test('a second first-timer attendance does not duplicate the first_timer record', function () {
    $participant = makeParticipant();

    // First attendance
    $this->actingAs($this->staff)
        ->post(route('services.attendance.store', ['occurrence' => $this->occurrence->id]), [
            'track' => 'first_timer',
            'participant_id' => $participant->id,
        ]);

    // Second occurrence for the same participant
    $secondOccurrence = ServiceOccurrence::create([
        'service_type' => ServiceType::Morning->value,
        'occurrence_date' => now()->addWeek()->toDateString(),
        'created_by' => $this->staff->id,
    ]);

    $this->actingAs($this->staff)
        ->post(route('services.attendance.store', ['occurrence' => $secondOccurrence->id]), [
            'track' => 'first_timer',
            'participant_id' => $participant->id,
        ]);

    // Attendance rows: 2. FirstTimer records: 1.
    expect(ServiceAttendanceFirstTimer::count())->toBe(2);
    expect(FirstTimer::count())->toBe(1);
});

// ----------------------------------------------------------------
// Cross-track and same-track constraints
// ----------------------------------------------------------------

test('a participant cannot be on both tracks for the same occurrence', function () {
    $participant = makeParticipant();

    ServiceAttendanceMember::create([
        'service_occurrence_id' => $this->occurrence->id,
        'participant_id' => $participant->id,
        'attendance_status' => 'present',
    ]);

    $this->actingAs($this->staff)
        ->post(route('services.attendance.store', ['occurrence' => $this->occurrence->id]), [
            'track' => 'first_timer',
            'participant_id' => $participant->id,
        ])
        ->assertStatus(422);
});

test('a participant cannot be added twice to the members track', function () {
    $participant = makeParticipant();

    ServiceAttendanceMember::create([
        'service_occurrence_id' => $this->occurrence->id,
        'participant_id' => $participant->id,
        'attendance_status' => 'present',
    ]);

    $this->actingAs($this->staff)
        ->post(route('services.attendance.store', ['occurrence' => $this->occurrence->id]), [
            'track' => 'member',
            'participant_id' => $participant->id,
        ])
        ->assertStatus(422);
});

// ----------------------------------------------------------------
// Marking
// ----------------------------------------------------------------

test('a member attendance row can be marked absent', function () {
    $participant = makeParticipant();
    $row = ServiceAttendanceMember::create([
        'service_occurrence_id' => $this->occurrence->id,
        'participant_id' => $participant->id,
        'attendance_status' => 'present',
    ]);

    $this->actingAs($this->staff)
        ->put(route('services.attendance.mark-member', [
            'occurrence' => $this->occurrence->id,
            'attendance' => $row->id,
        ]), ['attendance_status' => 'absent'])
        ->assertRedirect();

    expect($row->fresh()->attendance_status)->toBe('absent');
});

test('a first-timer attendance row can be marked excused', function () {
    $participant = makeParticipant();
    $row = ServiceAttendanceFirstTimer::create([
        'service_occurrence_id' => $this->occurrence->id,
        'participant_id' => $participant->id,
        'attendance_status' => 'present',
    ]);

    $this->actingAs($this->staff)
        ->put(route('services.attendance.mark-first-timer', [
            'occurrence' => $this->occurrence->id,
            'attendance' => $row->id,
        ]), ['attendance_status' => 'excused'])
        ->assertRedirect();

    expect($row->fresh()->attendance_status)->toBe('excused');
});

test('marking rejects an invalid status', function () {
    $participant = makeParticipant();
    $row = ServiceAttendanceMember::create([
        'service_occurrence_id' => $this->occurrence->id,
        'participant_id' => $participant->id,
        'attendance_status' => 'present',
    ]);

    $this->actingAs($this->staff)
        ->put(route('services.attendance.mark-member', [
            'occurrence' => $this->occurrence->id,
            'attendance' => $row->id,
        ]), ['attendance_status' => 'invented-status'])
        ->assertSessionHasErrors('attendance_status');
});

test('marking an attendance row that belongs to a different occurrence returns 404', function () {
    $otherOccurrence = ServiceOccurrence::create([
        'service_type' => ServiceType::Youth->value,
        'occurrence_date' => now()->addWeek()->toDateString(),
        'created_by' => $this->staff->id,
    ]);
    $participant = makeParticipant();
    $row = ServiceAttendanceMember::create([
        'service_occurrence_id' => $otherOccurrence->id,
        'participant_id' => $participant->id,
        'attendance_status' => 'present',
    ]);

    $this->actingAs($this->staff)
        ->put(route('services.attendance.mark-member', [
            'occurrence' => $this->occurrence->id,
            'attendance' => $row->id,
        ]), ['attendance_status' => 'absent'])
        ->assertNotFound();
});

// ----------------------------------------------------------------
// Deletion
// ----------------------------------------------------------------

test('a member attendance row can be deleted without touching the participant', function () {
    $participant = makeParticipant();
    $row = ServiceAttendanceMember::create([
        'service_occurrence_id' => $this->occurrence->id,
        'participant_id' => $participant->id,
        'attendance_status' => 'present',
    ]);

    $this->actingAs($this->staff)
        ->delete(route('services.attendance.destroy-member', [
            'occurrence' => $this->occurrence->id,
            'attendance' => $row->id,
        ]))
        ->assertRedirect();

    expect(ServiceAttendanceMember::count())->toBe(0);
    expect(Participant::find($participant->id))->not->toBeNull();
});

test('a first-timer attendance row can be deleted', function () {
    $participant = makeParticipant();
    $row = ServiceAttendanceFirstTimer::create([
        'service_occurrence_id' => $this->occurrence->id,
        'participant_id' => $participant->id,
        'attendance_status' => 'present',
    ]);

    $this->actingAs($this->staff)
        ->delete(route('services.attendance.destroy-first-timer', [
            'occurrence' => $this->occurrence->id,
            'attendance' => $row->id,
        ]))
        ->assertRedirect();

    expect(ServiceAttendanceFirstTimer::count())->toBe(0);
});

// ----------------------------------------------------------------
// Cascade on occurrence delete
// ----------------------------------------------------------------

test('deleting an occurrence cascades to both attendance tables', function () {
    $member = makeParticipant();
    $firstTimer = makeParticipant();

    ServiceAttendanceMember::create([
        'service_occurrence_id' => $this->occurrence->id,
        'participant_id' => $member->id,
        'attendance_status' => 'present',
    ]);
    ServiceAttendanceFirstTimer::create([
        'service_occurrence_id' => $this->occurrence->id,
        'participant_id' => $firstTimer->id,
        'attendance_status' => 'present',
    ]);

    $this->occurrence->delete();

    expect(ServiceAttendanceMember::count())->toBe(0);
    expect(ServiceAttendanceFirstTimer::count())->toBe(0);
});

// ----------------------------------------------------------------
// Auth
// ----------------------------------------------------------------

test('guests cannot view the attendance page', function () {
    $this->get(route('services.attendance.show', ['occurrence' => $this->occurrence->id]))
        ->assertRedirect(route('login'));
});
