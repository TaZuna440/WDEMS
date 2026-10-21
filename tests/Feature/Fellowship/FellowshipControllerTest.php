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
});

function fwEvent(string $type, array $overrides = []): FellowshipEvent
{
    return FellowshipEvent::create(array_merge([
        'fellowship_type' => $type,
        'name' => 'Gathering',
        'event_date' => now()->addWeek()->toDateString(),
        'created_by' => test()->staff->id,
    ], $overrides));
}

function fwParticipantCtrl(array $overrides = []): Participant
{
    static $counter = 0;
    $counter++;

    return Participant::create(array_merge(['pool' => 'church',
        'first_name' => 'Test',
        'last_name' => 'Participant'.$counter,
        'email' => 'test-'.$counter.'-'.uniqid().'@example.com',
    ], $overrides));
}

// ----------------------------------------------------------------
// Landing (index)
// ----------------------------------------------------------------

test('the fellowship landing page renders six fellowship types', function () {
    $this->actingAs($this->staff)
        ->get(route('fellowship.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('fellowship/index')
            ->has('fellowship_types', 6)
        );
});

test('the landing page reports event counts per type', function () {
    fwEvent(FellowshipType::Family->value);
    fwEvent(FellowshipType::Family->value, ['event_date' => now()->addWeeks(2)->toDateString()]);
    fwEvent(FellowshipType::Womens->value);

    $this->actingAs($this->staff)
        ->get(route('fellowship.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('fellowship_types.0.value', 'family')
            ->where('fellowship_types.0.event_count', 2)
            ->where('fellowship_types.4.value', 'womens')
            ->where('fellowship_types.4.event_count', 1)
        );
});

// ----------------------------------------------------------------
// Per-type view (show)
// ----------------------------------------------------------------

test('the per-type view lists events for that type only', function () {
    fwEvent(FellowshipType::Family->value);
    fwEvent(FellowshipType::Mens->value);

    $this->actingAs($this->staff)
        ->get(route('fellowship.show', ['type' => 'family']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('fellowship/show')
            ->where('fellowship_type.value', 'family')
            ->has('events', 1)
        );
});

test('an unknown fellowship type returns 404 from the route constraint', function () {
    $this->actingAs($this->staff)
        ->get('/fellowship/not-a-real-type')
        ->assertNotFound();
});

test('events are ordered most-recent-first', function () {
    fwEvent(FellowshipType::Family->value);
    fwEvent(FellowshipType::Family->value, ['event_date' => now()->addWeeks(3)->toDateString()]);

    $this->actingAs($this->staff)
        ->get(route('fellowship.show', ['type' => 'family']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('events', 2)
            ->where('events.0.event_date', now()->addWeeks(3)->toDateString())
            ->where('events.1.event_date', now()->addWeek()->toDateString())
        );
});

// ----------------------------------------------------------------
// Create
// ----------------------------------------------------------------

test('an authenticated user can create one fellowship event', function () {
    $this->actingAs($this->staff)
        ->post(route('fellowship.events.store', ['type' => 'family']), [
            'name' => 'November Family Gathering',
            'event_date' => now()->addWeek()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'venue' => 'Community Hall',
            'facilitator' => 'Pastor David',
            'notes' => 'First of the month.',
        ])
        ->assertRedirect(route('fellowship.show', ['type' => 'family']));

    expect(FellowshipEvent::count())->toBe(1);
    $event = FellowshipEvent::first();
    expect($event->fellowship_type)->toBe(FellowshipType::Family);
    expect($event->name)->toBe('November Family Gathering');
    expect($event->facilitator)->toBe('Pastor David');
    expect($event->created_by)->toBe($this->staff->id);
});

test('event creation requires a name', function () {
    $this->actingAs($this->staff)
        ->post(route('fellowship.events.store', ['type' => 'family']), [
            'event_date' => now()->addWeek()->toDateString(),
        ])
        ->assertSessionHasErrors('name');

    expect(FellowshipEvent::count())->toBe(0);
});

test('event creation requires a date', function () {
    $this->actingAs($this->staff)
        ->post(route('fellowship.events.store', ['type' => 'family']), [
            'name' => 'Test Gathering',
        ])
        ->assertSessionHasErrors('event_date');

    expect(FellowshipEvent::count())->toBe(0);
});

test('event creation rejects an end_time before start_time', function () {
    $this->actingAs($this->staff)
        ->post(route('fellowship.events.store', ['type' => 'family']), [
            'name' => 'Test Gathering',
            'event_date' => now()->addWeek()->toDateString(),
            'start_time' => '12:00',
            'end_time' => '10:00',
        ])
        ->assertSessionHasErrors('end_time');

    expect(FellowshipEvent::count())->toBe(0);
});

test('event creation rejects a date more than 2 years out', function () {
    $this->actingAs($this->staff)
        ->post(route('fellowship.events.store', ['type' => 'family']), [
            'name' => 'Test Gathering',
            'event_date' => now()->addYears(3)->toDateString(),
        ])
        ->assertSessionHasErrors('event_date');
});

test('event creation allows a past date for retroactive logging', function () {
    $this->actingAs($this->staff)
        ->post(route('fellowship.events.store', ['type' => 'family']), [
            'name' => 'Retroactive Gathering',
            'event_date' => now()->subWeek()->toDateString(),
        ])
        ->assertRedirect();

    expect(FellowshipEvent::count())->toBe(1);
});

// ----------------------------------------------------------------
// Update
// ----------------------------------------------------------------

test('an event can be updated', function () {
    $event = fwEvent(FellowshipType::Family->value, ['name' => 'Original']);

    $this->actingAs($this->staff)
        ->put(route('fellowship.events.update', ['event' => $event->id]), [
            'name' => 'Renamed',
            'event_date' => $event->event_date->toDateString(),
            'venue' => 'New Venue',
        ])
        ->assertRedirect(route('fellowship.show', ['type' => 'family']));

    expect($event->fresh()->name)->toBe('Renamed');
    expect($event->fresh()->venue)->toBe('New Venue');
});

test('update ignores fellowship_type in the payload', function () {
    $event = fwEvent(FellowshipType::Family->value);

    $this->actingAs($this->staff)
        ->put(route('fellowship.events.update', ['event' => $event->id]), [
            'name' => 'Renamed',
            'event_date' => $event->event_date->toDateString(),
            'fellowship_type' => 'mens',
        ])
        ->assertRedirect();

    expect($event->fresh()->fellowship_type)->toBe(FellowshipType::Family);
});

// ----------------------------------------------------------------
// Delete
// ----------------------------------------------------------------

test('an event can be deleted', function () {
    $event = fwEvent(FellowshipType::Family->value);

    $this->actingAs($this->staff)
        ->delete(route('fellowship.events.destroy', ['event' => $event->id]))
        ->assertRedirect(route('fellowship.show', ['type' => 'family']));

    expect(FellowshipEvent::count())->toBe(0);
});

test('deleting an event cascades to groups and attendances', function () {
    $event = fwEvent(FellowshipType::Family->value);
    $group = FellowshipGroup::create([
        'fellowship_event_id' => $event->id,
        'name' => 'Parents',
    ]);
    $participant = fwParticipantCtrl();

    FellowshipAttendance::create([
        'fellowship_event_id' => $event->id,
        'participant_id' => $participant->id,
        'fellowship_group_id' => $group->id,
        'attendance_status' => 'present',
    ]);

    $event->delete();

    expect(FellowshipGroup::count())->toBe(0);
    expect(FellowshipAttendance::count())->toBe(0);
    expect(Participant::find($participant->id))->not->toBeNull();
});

// ----------------------------------------------------------------
// Auth
// ----------------------------------------------------------------

test('guests are redirected to login from the fellowship landing', function () {
    $this->get(route('fellowship.index'))->assertRedirect(route('login'));
});
