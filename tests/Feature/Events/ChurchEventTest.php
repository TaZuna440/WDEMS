<?php

use App\Enums\ChurchEventStatus;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\Event;
use App\Models\User;

beforeEach(function () {
    $this->staff = User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
    ]);
});

test('a church event can be created via POST', function () {
    $this->actingAs($this->staff)->post(route('events.store'), [
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Special Occasions',
        'church_status' => 'upcoming',
        'event_name' => 'Pasko sa Nayon',
        'event_date' => now()->addWeek()->toDateString(),
        'start_time' => '10:00',
        'end_time' => '12:00',
        'venue' => 'Main Hall',
        'venue_address' => '123 Church Street',
    ])->assertRedirect(route('events.index'));

    $event = Event::latest()->first();

    expect($event->event_kind)->toBe('church');
    expect($event->church_category)->toBe('Special Occasions');
    expect($event->church_status)->toBe(ChurchEventStatus::Upcoming);
    expect($event->distance_value)->toBeNull();
    expect($event->statusLabel())->toBe('Upcoming');
});

test('the events index can filter by kind', function () {
    Event::create([
        'created_by' => $this->staff->id,
        'event_kind' => 'running',
        'event_type' => EventType::CommunityRun->value,
        'event_name' => 'Test Running Event',
        'event_date' => now()->addWeek()->toDateString(),
        'start_time' => '06:00',
        'venue' => 'Test Venue',
        'venue_address' => '456 Running Street',
        'distance_value' => 5.0,
        'distance_unit' => 'km',
        'status' => EventStatus::Draft,
    ]);

    Event::create([
        'created_by' => $this->staff->id,
        'event_kind' => 'church',
        'event_type' => EventType::Church->value,
        'church_category' => 'Youth',
        'church_status' => ChurchEventStatus::Upcoming->value,
        'event_name' => 'Test Church Event',
        'event_date' => now()->addWeek()->toDateString(),
        'start_time' => '10:00',
        'venue' => 'Test Church',
        'venue_address' => '789 Church Street',
        'status' => EventStatus::Draft,
    ]);

    $this->actingAs($this->staff)
        ->get(route('events.index', ['kind' => 'church']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/index')
            ->has('events', 1)
        );

    $this->actingAs($this->staff)
        ->get(route('events.index', ['kind' => 'running']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/index')
            ->has('events', 1)
        );
});

test('the events index filters church categories and selected dates', function () {
    $service = Event::create([
        'created_by' => $this->staff->id,
        'event_kind' => 'church',
        'event_type' => EventType::Church->value,
        'church_category' => 'Services',
        'church_status' => ChurchEventStatus::Upcoming->value,
        'event_name' => 'Sunday service',
        'event_date' => '2026-10-25',
        'venue' => 'Church',
        'venue_address' => 'Church address',
    ]);
    Event::create([
        'created_by' => $this->staff->id,
        'event_kind' => 'church',
        'event_type' => EventType::Church->value,
        'church_category' => 'Fellowship',
        'church_status' => ChurchEventStatus::Upcoming->value,
        'event_name' => 'Fellowship',
        'event_date' => '2026-10-25',
        'venue' => 'Hall',
        'venue_address' => 'Hall address',
    ]);
    $november = Event::create([
        'created_by' => $this->staff->id,
        'event_kind' => 'church',
        'event_type' => EventType::Church->value,
        'church_category' => 'Fellowship',
        'church_status' => ChurchEventStatus::Upcoming->value,
        'event_name' => 'November gathering',
        'event_date' => '2026-11-02',
        'venue' => 'Hall',
        'venue_address' => 'Hall address',
    ]);

    $this->actingAs($this->staff)
        ->get(route('events.index', ['kind' => 'church', 'church_category' => 'Services', 'date' => '2026-10-25']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.church_category', 'Services')
            ->where('filters.date', '2026-10-25')
            ->has('events', 1)
            ->where('events.0.id', $service->id));

    $this->actingAs($this->staff)
        ->get(route('events.index', ['kind' => 'church', 'month' => '2026-11']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.month', '2026-11')
            ->has('events', 1)
            ->where('events.0.id', $november->id));

    $this->actingAs($this->staff)
        ->get(route('events.index', ['kind' => 'church', 'date' => '2026-10-25']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.date', '2026-10-25')
            ->has('events', 2));
});

