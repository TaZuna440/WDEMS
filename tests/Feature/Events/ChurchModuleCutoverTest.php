<?php

use App\Models\Event;
use App\Models\FellowshipEvent;
use App\Models\User;

beforeEach(function () {
    $this->staff = User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
    ]);
    $this->actingAs($this->staff);
});

it('renders the Ministry card pages and points activity cards to filtered Events lists', function (string $slug, string $label, array $activities) {
    $this->get("/events/church/{$slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/church/specialist/ministry')
            ->where('ministry.label', $label)
            ->has('activities', count($activities))
            ->where('activities.0.label', $activities[0])
            ->where('activities.0.href', route('events.index', [
                'kind' => 'church',
                'church_category' => $label,
                'church_activity' => $activities[0],
            ]))
            ->where('add_event_url', route('events.create.church.category', $slug)));
})->with([
    ['services', 'Services', ['Sunday Service', 'Special Service']],
    ['fellowship', 'Fellowship', ['Church Fellowship', 'Ministry Fellowship']],
    ['discipleship', 'Discipleship', ['Bible Study', 'Discipleship Class', 'Leadership Training']],
]);

it('filters generic Events by both Church module and activity', function () {
    $matching = Event::create([
        'created_by' => $this->staff->id,
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Fellowship',
        'church_activity_category' => 'Church Fellowship',
        'church_status' => 'upcoming',
        'event_name' => 'Sunday fellowship',
        'event_date' => today()->addWeek()->toDateString(),
        'start_time' => '10:00',
        'venue' => 'Community Hall',
        'venue_address' => '1 Main Street',
    ]);
    Event::create([
        'created_by' => $this->staff->id,
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Fellowship',
        'church_activity_category' => 'Ministry Fellowship',
        'church_status' => 'upcoming',
        'event_name' => 'Ministry gathering',
        'event_date' => today()->addWeek()->toDateString(),
        'start_time' => '10:00',
        'venue' => 'Church Hall',
        'venue_address' => '2 Main Street',
    ]);

    $this->get(route('events.index', [
        'kind' => 'church',
        'church_category' => 'Fellowship',
        'church_activity' => 'Church Fellowship',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('events', 1)
            ->where('events.0.id', $matching->id)
            ->where('filters.church_activity', 'Church Fellowship'));
});

it('opens the shared wizard with the Church module and activity locked', function (string $module, string $activitySlug, string $label, string $activity) {
    $this->get("/events/create/church/{$module}/{$activitySlug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/create')
            ->where('preset_event_type', 'church')
            ->where('preset_church_category', $label)
            ->where('lock_church_category', true)
            ->where('church_categories.0.value', $label)
            ->where('preset_church_activity_category', $activity)
            ->where('lock_specialist_activity', true)
            ->where('specialist_workflow', true));
})->with([
    ['services', 'sunday-service', 'Services', 'Sunday Service'],
    ['fellowship', 'church-fellowship', 'Fellowship', 'Church Fellowship'],
    ['discipleship', 'bible-study', 'Discipleship', 'Bible Study'],
]);

it('creates a standard Event from the Fellowship activity wizard route', function () {
    $this->get('/events/create/church/fellowship/church-fellowship')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/create')
            ->where('preset_church_category', 'Fellowship')
            ->where('preset_church_activity_category', 'Church Fellowship'));

    $response = $this->post(route('events.store'), [
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Fellowship',
        'church_activity_category' => 'Church Fellowship',
        'specialist_workflow' => true,
        'church_status' => 'upcoming',
        'event_name' => 'Fellowship Gathering',
        'event_date' => today()->addWeek()->toDateString(),
        'start_time' => '10:00',
        'end_time' => '12:00',
        'venue' => 'Community Hall',
        'venue_address' => '1 Main Street',
    ]);

    $event = Event::query()->where('church_category', 'Fellowship')->first();

    $response->assertRedirect(route('events.show', $event));

    expect(Event::query()->where('church_category', 'Fellowship')->count())->toBe(1)
        ->and($event?->church_activity_category)->toBe('Church Fellowship')
        ->and(FellowshipEvent::count())->toBe(0);
});
