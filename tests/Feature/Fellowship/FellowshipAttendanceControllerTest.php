<?php

use App\Enums\FellowshipType;
use App\Models\FellowshipAttendance;
use App\Models\FellowshipEvent;
use App\Models\FellowshipGroup;
use App\Models\Participant;
use App\Models\User;

beforeEach(function () {
    $this->staff = User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
    ]);

    $this->event = FellowshipEvent::create([
        'fellowship_type' => FellowshipType::Family->value,
        'name' => 'Test Gathering',
        'event_date' => now()->toDateString(),
        'created_by' => $this->staff->id,
    ]);
});

function fwParticipantAttn(array $overrides = []): Participant
{
    static $counter = 0;
    $counter++;

    return Participant::create(array_merge([
        'first_name' => 'Test',
        'last_name' => 'Participant'.$counter,
        'email' => 'attendance-'.$counter.'-'.uniqid().'@example.com',
    ], $overrides));
}

function fwOtherEventAttn(): FellowshipEvent
{
    return FellowshipEvent::create([
        'fellowship_type' => FellowshipType::Church->value,
        'name' => 'Other Gathering',
        'event_date' => now()->addWeek()->toDateString(),
        'created_by' => test()->staff->id,
    ]);
}

// ----------------------------------------------------------------
// Show
// ----------------------------------------------------------------

test('the attendance page renders with an empty roster', function () {
    $this->actingAs($this->staff)
        ->get(route('fellowship.attendance.show', ['event' => $this->event->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('fellowship/attendance')
            ->where('event.id', $this->event->id)
            ->has('attendances', 0)
            ->has('groups', 0)
        );
});

test('the attendance page lists attendance rows with participant data', function () {
    $participant = fwParticipantAttn([
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
    ]);

    FellowshipAttendance::create([
        'fellowship_event_id' => $this->event->id,
        'participant_id' => $participant->id,
        'attendance_status' => 'present',
    ]);

    $this->actingAs($this->staff)
        ->get(route('fellowship.attendance.show', ['event' => $this->event->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('attendances', 1)
            ->where('attendances.0.participant_id', $participant->id)
            ->where('attendances.0.full_name', 'Juan Dela Cruz')
            ->where('attendances.0.attendance_status', 'present')
        );
});

test('the attendance page includes groups with attendance counts', function () {
    $group = FellowshipGroup::create([
        'fellowship_event_id' => $this->event->id,
        'name' => 'Parents',
    ]);
    $participant = fwParticipantAttn();

    FellowshipAttendance::create([
        'fellowship_event_id' => $this->event->id,
        'participant_id' => $participant->id,
        'fellowship_group_id' => $group->id,
        'attendance_status' => 'present',
    ]);

    $this->actingAs($this->staff)
        ->get(route('fellowship.attendance.show', ['event' => $this->event->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('groups', 1)
            ->where('groups.0.name', 'Parents')
            ->where('groups.0.attendance_count', 1)
        );
});

// ----------------------------------------------------------------
// Store
// ----------------------------------------------------------------

test('a new participant can be added to the attendance', function () {
    $this->actingAs($this->staff)
        ->post(route('fellowship.attendance.store', ['event' => $this->event->id]), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'age' => 30,
            'birthday' => '1995-01-15',
        ])
        ->assertRedirect();

    expect(FellowshipAttendance::count())->toBe(1);
    $row = FellowshipAttendance::first();
    expect($row->attendance_status)->toBe('present');
    expect($row->participant->fullName())->toBe('Juan Dela Cruz');
});

test('store reuses an existing participant matched by id', function () {
    $existing = fwParticipantAttn([
        'first_name' => 'Existing',
        'last_name' => 'Person',
    ]);

    $this->actingAs($this->staff)
        ->post(route('fellowship.attendance.store', ['event' => $this->event->id]), [
            'participant_id' => $existing->id,
            'first_name' => 'Ignored',
            'last_name' => 'Ignored',
        ])
        ->assertRedirect();

    expect(FellowshipAttendance::count())->toBe(1);
    expect(FellowshipAttendance::first()->participant_id)->toBe($existing->id);
    expect(Participant::count())->toBe(1);
});

test('store reuses an existing participant matched by normalized phone', function () {
    $existing = fwParticipantAttn([
        'first_name' => 'Existing',
        'last_name' => 'Person',
        'contact_number' => '09171234567',
    ]);

    $this->actingAs($this->staff)
        ->post(route('fellowship.attendance.store', ['event' => $this->event->id]), [
            'first_name' => 'Some',
            'last_name' => 'Name',
            'contact_number' => '09171234567',
        ])
        ->assertRedirect();

    expect(FellowshipAttendance::count())->toBe(1);
    expect(FellowshipAttendance::first()->participant_id)->toBe($existing->id);
});

test('store rejects a participant already marked on this event', function () {
    $participant = fwParticipantAttn();

    FellowshipAttendance::create([
        'fellowship_event_id' => $this->event->id,
        'participant_id' => $participant->id,
        'attendance_status' => 'present',
    ]);

    $this->actingAs($this->staff)
        ->post(route('fellowship.attendance.store', ['event' => $this->event->id]), [
            'participant_id' => $participant->id,
        ])
        ->assertStatus(422);

    expect(FellowshipAttendance::count())->toBe(1);
});

test('store accepts a group that belongs to the same event', function () {
    $group = FellowshipGroup::create([
        'fellowship_event_id' => $this->event->id,
        'name' => 'Parents',
    ]);

    $this->actingAs($this->staff)
        ->post(route('fellowship.attendance.store', ['event' => $this->event->id]), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'fellowship_group_id' => $group->id,
        ])
        ->assertRedirect();

    expect(FellowshipAttendance::first()->fellowship_group_id)->toBe($group->id);
});

test('store rejects a group that belongs to a different event', function () {
    $other = fwOtherEventAttn();
    $otherGroup = FellowshipGroup::create([
        'fellowship_event_id' => $other->id,
        'name' => 'Parents',
    ]);

    $this->actingAs($this->staff)
        ->post(route('fellowship.attendance.store', ['event' => $this->event->id]), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'fellowship_group_id' => $otherGroup->id,
        ])
        ->assertNotFound();

    expect(FellowshipAttendance::count())->toBe(0);
});

// ----------------------------------------------------------------
// Mark
// ----------------------------------------------------------------

test('a row can be marked absent', function () {
    $participant = fwParticipantAttn();
    $row = FellowshipAttendance::create([
        'fellowship_event_id' => $this->event->id,
        'participant_id' => $participant->id,
        'attendance_status' => 'present',
    ]);

    $this->actingAs($this->staff)
        ->put(route('fellowship.attendance.mark', [
            'event' => $this->event->id,
            'attendance' => $row->id,
        ]), ['attendance_status' => 'absent'])
        ->assertRedirect();

    expect($row->fresh()->attendance_status)->toBe('absent');
});

test('marking can reassign the group', function () {
    $group = FellowshipGroup::create([
        'fellowship_event_id' => $this->event->id,
        'name' => 'Parents',
    ]);
    $participant = fwParticipantAttn();
    $row = FellowshipAttendance::create([
        'fellowship_event_id' => $this->event->id,
        'participant_id' => $participant->id,
        'attendance_status' => 'present',
    ]);

    $this->actingAs($this->staff)
        ->put(route('fellowship.attendance.mark', [
            'event' => $this->event->id,
            'attendance' => $row->id,
        ]), [
            'attendance_status' => 'present',
            'fellowship_group_id' => $group->id,
        ])
        ->assertRedirect();

    expect($row->fresh()->fellowship_group_id)->toBe($group->id);
});

test('marking can clear the group assignment', function () {
    $group = FellowshipGroup::create([
        'fellowship_event_id' => $this->event->id,
        'name' => 'Parents',
    ]);
    $participant = fwParticipantAttn();
    $row = FellowshipAttendance::create([
        'fellowship_event_id' => $this->event->id,
        'participant_id' => $participant->id,
        'fellowship_group_id' => $group->id,
        'attendance_status' => 'present',
    ]);

    $this->actingAs($this->staff)
        ->put(route('fellowship.attendance.mark', [
            'event' => $this->event->id,
            'attendance' => $row->id,
        ]), [
            'attendance_status' => 'present',
            'fellowship_group_id' => null,
        ])
        ->assertRedirect();

    expect($row->fresh()->fellowship_group_id)->toBeNull();
});

test('marking rejects an invalid status', function () {
    $participant = fwParticipantAttn();
    $row = FellowshipAttendance::create([
        'fellowship_event_id' => $this->event->id,
        'participant_id' => $participant->id,
        'attendance_status' => 'present',
    ]);

    $this->actingAs($this->staff)
        ->put(route('fellowship.attendance.mark', [
            'event' => $this->event->id,
            'attendance' => $row->id,
        ]), ['attendance_status' => 'invented-status'])
        ->assertSessionHasErrors('attendance_status');
});

test('marking rejects a group that belongs to a different event', function () {
    $other = fwOtherEventAttn();
    $otherGroup = FellowshipGroup::create([
        'fellowship_event_id' => $other->id,
        'name' => 'Parents',
    ]);
    $participant = fwParticipantAttn();
    $row = FellowshipAttendance::create([
        'fellowship_event_id' => $this->event->id,
        'participant_id' => $participant->id,
        'attendance_status' => 'present',
    ]);

    $this->actingAs($this->staff)
        ->put(route('fellowship.attendance.mark', [
            'event' => $this->event->id,
            'attendance' => $row->id,
        ]), [
            'attendance_status' => 'present',
            'fellowship_group_id' => $otherGroup->id,
        ])
        ->assertNotFound();
});

test('marking a row that belongs to a different event returns 404', function () {
    $other = fwOtherEventAttn();
    $participant = fwParticipantAttn();
    $row = FellowshipAttendance::create([
        'fellowship_event_id' => $other->id,
        'participant_id' => $participant->id,
        'attendance_status' => 'present',
    ]);

    $this->actingAs($this->staff)
        ->put(route('fellowship.attendance.mark', [
            'event' => $this->event->id,
            'attendance' => $row->id,
        ]), ['attendance_status' => 'absent'])
        ->assertNotFound();
});

// ----------------------------------------------------------------
// Destroy
// ----------------------------------------------------------------

test('a row can be deleted without touching the participant', function () {
    $participant = fwParticipantAttn();
    $row = FellowshipAttendance::create([
        'fellowship_event_id' => $this->event->id,
        'participant_id' => $participant->id,
        'attendance_status' => 'present',
    ]);

    $this->actingAs($this->staff)
        ->delete(route('fellowship.attendance.destroy', [
            'event' => $this->event->id,
            'attendance' => $row->id,
        ]))
        ->assertRedirect();

    expect(FellowshipAttendance::count())->toBe(0);
    expect(Participant::find($participant->id))->not->toBeNull();
});

test('deleting a row that belongs to a different event returns 404', function () {
    $other = fwOtherEventAttn();
    $participant = fwParticipantAttn();
    $row = FellowshipAttendance::create([
        'fellowship_event_id' => $other->id,
        'participant_id' => $participant->id,
        'attendance_status' => 'present',
    ]);

    $this->actingAs($this->staff)
        ->delete(route('fellowship.attendance.destroy', [
            'event' => $this->event->id,
            'attendance' => $row->id,
        ]))
        ->assertNotFound();

    expect(FellowshipAttendance::count())->toBe(1);
});

// ----------------------------------------------------------------
// Auth
// ----------------------------------------------------------------

test('guests cannot view the attendance page', function () {
    $this->get(route('fellowship.attendance.show', ['event' => $this->event->id]))
        ->assertRedirect(route('login'));
});
