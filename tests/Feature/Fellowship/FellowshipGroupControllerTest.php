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

function fwParticipantGroup(array $overrides = []): Participant
{
    static $counter = 0;
    $counter++;

    return Participant::create(array_merge(['pool' => 'church',
        'first_name' => 'Test',
        'last_name' => 'Participant'.$counter,
        'email' => 'group-test-'.$counter.'-'.uniqid().'@example.com',
    ], $overrides));
}

function fwOtherEvent(): FellowshipEvent
{
    return FellowshipEvent::create([
        'fellowship_type' => FellowshipType::Church->value,
        'name' => 'Other Gathering',
        'event_date' => now()->addWeek()->toDateString(),
        'created_by' => test()->staff->id,
    ]);
}

// ----------------------------------------------------------------
// Store
// ----------------------------------------------------------------

test('a group can be created on an event', function () {
    $this->actingAs($this->staff)
        ->post(route('fellowship.groups.store', ['event' => $this->event->id]), [
            'name' => 'Parents',
            'display_order' => 1,
        ])
        ->assertRedirect();

    expect(FellowshipGroup::count())->toBe(1);
    $group = FellowshipGroup::first();
    expect($group->name)->toBe('Parents');
    expect($group->fellowship_event_id)->toBe($this->event->id);
    expect($group->display_order)->toBe(1);
});

test('group creation requires a name', function () {
    $this->actingAs($this->staff)
        ->post(route('fellowship.groups.store', ['event' => $this->event->id]), [])
        ->assertSessionHasErrors('name');

    expect(FellowshipGroup::count())->toBe(0);
});

test('group creation rejects a duplicate name within the same event', function () {
    FellowshipGroup::create([
        'fellowship_event_id' => $this->event->id,
        'name' => 'Parents',
    ]);

    $this->actingAs($this->staff)
        ->post(route('fellowship.groups.store', ['event' => $this->event->id]), [
            'name' => 'Parents',
        ])
        ->assertSessionHasErrors('name');

    expect(FellowshipGroup::count())->toBe(1);
});

test('group creation allows the same name across different events', function () {
    $other = fwOtherEvent();
    FellowshipGroup::create([
        'fellowship_event_id' => $other->id,
        'name' => 'Parents',
    ]);

    $this->actingAs($this->staff)
        ->post(route('fellowship.groups.store', ['event' => $this->event->id]), [
            'name' => 'Parents',
        ])
        ->assertRedirect();

    expect(FellowshipGroup::count())->toBe(2);
});

// ----------------------------------------------------------------
// Update
// ----------------------------------------------------------------

test('a group can be renamed', function () {
    $group = FellowshipGroup::create([
        'fellowship_event_id' => $this->event->id,
        'name' => 'Parents',
    ]);

    $this->actingAs($this->staff)
        ->put(route('fellowship.groups.update', [
            'event' => $this->event->id,
            'group' => $group->id,
        ]), ['name' => 'Parents & Guardians'])
        ->assertRedirect();

    expect($group->fresh()->name)->toBe('Parents & Guardians');
});

test('renaming a group to its own name does not collide', function () {
    $group = FellowshipGroup::create([
        'fellowship_event_id' => $this->event->id,
        'name' => 'Parents',
    ]);

    $this->actingAs($this->staff)
        ->put(route('fellowship.groups.update', [
            'event' => $this->event->id,
            'group' => $group->id,
        ]), ['name' => 'Parents'])
        ->assertRedirect();

    expect($group->fresh()->name)->toBe('Parents');
});

test('renaming a group to another group\'s name is rejected', function () {
    FellowshipGroup::create([
        'fellowship_event_id' => $this->event->id,
        'name' => 'Parents',
    ]);
    $youth = FellowshipGroup::create([
        'fellowship_event_id' => $this->event->id,
        'name' => 'Youth',
    ]);

    $this->actingAs($this->staff)
        ->put(route('fellowship.groups.update', [
            'event' => $this->event->id,
            'group' => $youth->id,
        ]), ['name' => 'Parents'])
        ->assertSessionHasErrors('name');

    expect($youth->fresh()->name)->toBe('Youth');
});

test('updating a group that belongs to a different event returns 404', function () {
    $other = fwOtherEvent();
    $otherGroup = FellowshipGroup::create([
        'fellowship_event_id' => $other->id,
        'name' => 'Parents',
    ]);

    $this->actingAs($this->staff)
        ->put(route('fellowship.groups.update', [
            'event' => $this->event->id,
            'group' => $otherGroup->id,
        ]), ['name' => 'Renamed'])
        ->assertNotFound();
});

// ----------------------------------------------------------------
// Delete
// ----------------------------------------------------------------

test('a group can be deleted', function () {
    $group = FellowshipGroup::create([
        'fellowship_event_id' => $this->event->id,
        'name' => 'Parents',
    ]);

    $this->actingAs($this->staff)
        ->delete(route('fellowship.groups.destroy', [
            'event' => $this->event->id,
            'group' => $group->id,
        ]))
        ->assertRedirect();

    expect(FellowshipGroup::count())->toBe(0);
});

test('deleting a group nulls the group pointer on its attendances', function () {
    $group = FellowshipGroup::create([
        'fellowship_event_id' => $this->event->id,
        'name' => 'Parents',
    ]);
    $participant = fwParticipantGroup();

    FellowshipAttendance::create([
        'fellowship_event_id' => $this->event->id,
        'participant_id' => $participant->id,
        'fellowship_group_id' => $group->id,
        'attendance_status' => 'present',
    ]);

    $this->actingAs($this->staff)
        ->delete(route('fellowship.groups.destroy', [
            'event' => $this->event->id,
            'group' => $group->id,
        ]))
        ->assertRedirect();

    expect(FellowshipGroup::count())->toBe(0);
    expect(FellowshipAttendance::count())->toBe(1);
    expect(FellowshipAttendance::first()->fellowship_group_id)->toBeNull();
});

test('deleting a group that belongs to a different event returns 404', function () {
    $other = fwOtherEvent();
    $otherGroup = FellowshipGroup::create([
        'fellowship_event_id' => $other->id,
        'name' => 'Parents',
    ]);

    $this->actingAs($this->staff)
        ->delete(route('fellowship.groups.destroy', [
            'event' => $this->event->id,
            'group' => $otherGroup->id,
        ]))
        ->assertNotFound();

    expect(FellowshipGroup::count())->toBe(1);
});
