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
