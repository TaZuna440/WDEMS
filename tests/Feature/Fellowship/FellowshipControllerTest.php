<?php

use App\Enums\FellowshipType;
use App\Models\FellowshipAttendance;
use App\Models\FellowshipEvent;
use App\Models\FellowshipGroup;
use App\Models\Participant;
use App\Models\User;
use App\Services\Church\RegistrationBridge;

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

test('the fellowship landing page shows its activity cards', function () {
    $this->actingAs($this->staff)
        ->get(route('fellowship.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/church/specialist/ministry')
            ->where('ministry.label', 'Fellowship')
            ->has('activities', 2)
            ->where('activities.0.label', 'Church Fellowship')
            ->where('activities.1.label', 'Ministry Fellowship'));
});

test('the legacy Fellowship type route redirects without changing stored events', function () {
    fwEvent(FellowshipType::Family->value);
    fwEvent(FellowshipType::Family->value, ['event_date' => now()->addWeeks(2)->toDateString()]);
    fwEvent(FellowshipType::Womens->value);

    $this->actingAs($this->staff)
        ->get(route('fellowship.show', ['type' => 'family']))
        ->assertRedirect(route('events.index', [
            'kind' => 'church',
            'church_category' => 'Fellowship',
        ]));

    expect(FellowshipEvent::count())->toBe(3);
});

// ----------------------------------------------------------------
// Per-type view (show)
// ----------------------------------------------------------------

test('the legacy per-type route redirects to the generic Fellowship list', function () {
    fwEvent(FellowshipType::Family->value);
    fwEvent(FellowshipType::Mens->value);

    $this->actingAs($this->staff)
        ->get(route('fellowship.show', ['type' => 'family']))
        ->assertRedirect(route('events.index', [
            'kind' => 'church',
            'church_category' => 'Fellowship',
        ]));
});

test('the legacy Fellowship route redirects after activation', function () {
    $event = fwEvent(FellowshipType::Family->value);
    $churchEvent = app(RegistrationBridge::class)->activate($event);

    $this->actingAs($this->staff)
        ->get(route('fellowship.show', ['type' => 'family']))
        ->assertRedirect(route('events.index', [
            'kind' => 'church',
            'church_category' => 'Fellowship',
        ]));
});

test('an unknown fellowship type returns 404 from the route constraint', function () {
    $this->actingAs($this->staff)
        ->get('/fellowship/not-a-real-type')
        ->assertNotFound();
});

test('legacy Fellowship records remain stored after route cutover', function () {
    fwEvent(FellowshipType::Family->value);
    fwEvent(FellowshipType::Family->value, ['event_date' => now()->addWeeks(3)->toDateString()]);

    $this->actingAs($this->staff)
        ->get(route('fellowship.show', ['type' => 'family']))
        ->assertRedirect(route('events.index', [
            'kind' => 'church',
            'church_category' => 'Fellowship',
        ]));

    expect(FellowshipEvent::count())->toBe(2);
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

test('event creation rejects a past scheduling date', function () {
    $this->actingAs($this->staff)
        ->post(route('fellowship.events.store', ['type' => 'family']), [
            'name' => 'Retroactive Gathering',
            'event_date' => now()->subWeek()->toDateString(),
        ])
        ->assertSessionHasErrors('event_date');

    expect(FellowshipEvent::count())->toBe(0);
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
