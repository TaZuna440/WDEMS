<?php

use App\Models\Event;
use App\Models\User;
use App\Support\ChurchSpecialistCatalog;

beforeEach(function () {
    $this->actingAs(User::factory()->create([
        'role' => 'admin',
        'email_two_factor_enabled' => false,
    ]));
});

it('renders every specialist ministry and activity category as a real workflow page', function () {
    foreach (ChurchSpecialistCatalog::ministries() as $slug => $ministry) {
        $this->get("/events/church/{$slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('events/church/specialist/ministry')
                ->where('ministry.slug', $slug)
                ->where('ministry.label', $ministry['label'])
                ->where('add_event_url', route('events.create.church.category', $slug)));

        foreach ($ministry['activities'] as $activitySlug => $activityLabel) {
            $this->get("/events/church/{$slug}/{$activitySlug}")
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('events/church/specialist/category')
                    ->where('ministry.label', $ministry['label'])
                    ->where('activity.label', $activityLabel)
                    ->where('activity.event_count', 0)
                    ->where('add_event_url', route('events.create.church.category', [$slug, $activitySlug])));
        }
    }
});

it('keeps specialist event lists isolated by ministry and activity category', function () {
    $staff = auth()->user();
    $kidsService = Event::create([
        'created_by' => $staff->id,
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Kids Ministry',
        'church_activity_category' => 'Kids Service',
        'church_status' => 'upcoming',
        'event_name' => 'Kids Sunday Service',
        'event_date' => today()->addWeek()->toDateString(),
        'venue' => 'Kids Hall',
        'venue_address' => 'Kids Ministry address',
    ]);
    $youthCamp = Event::create([
        'created_by' => $staff->id,
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Youth',
        'church_activity_category' => 'Youth Camp',
        'church_status' => 'upcoming',
        'event_name' => 'Youth Camp 2026',
        'event_date' => today()->addWeek()->toDateString(),
        'venue' => 'Camp Site',
        'venue_address' => 'Youth Camp address',
    ]);
    $huddle = Event::create([
        'created_by' => $staff->id,
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Youth',
        'church_activity_category' => 'Huddle',
        'church_status' => 'upcoming',
        'event_name' => 'Youth Huddle',
        'event_date' => today()->addWeek()->toDateString(),
        'venue' => 'Meeting Room',
        'venue_address' => 'Huddle address',
    ]);

    $this->get('/events/church/kids/kids-service')
        ->assertInertia(fn ($page) => $page
            ->has('events', 1)
            ->where('events.0.id', $kidsService->id)
            ->where('events.0.name', 'Kids Sunday Service'));
    $this->get('/events/church/youth/camp')
        ->assertInertia(fn ($page) => $page
            ->has('events', 1)
            ->where('events.0.id', $youthCamp->id)
            ->where('events.0.name', 'Youth Camp 2026'));
    $this->get('/events/church/youth/huddle')
        ->assertInertia(fn ($page) => $page
            ->has('events', 1)
            ->where('events.0.id', $huddle->id));
    $this->get('/events/church/youth/timothy-class')
        ->assertInertia(fn ($page) => $page->has('events', 0));
});

it('keeps the Youth category set exact and the Services Youth route available', function () {
    $this->get('/events/church/youth')
        ->assertInertia(fn ($page) => $page
            ->has('activities', 4)
            ->where('activities.0.label', 'Youth Camp')
            ->where('activities.1.label', 'Huddle')
            ->where('activities.2.label', 'Campus Ministry')
            ->where('activities.3.label', 'Timothy Class'));

    $this->get('/events/church/youth/service')->assertNotFound();
    $this->get('/events/church/youth/not-a-module')->assertNotFound();
    $this->get('/events/church/services/youth')->assertOk();
});
