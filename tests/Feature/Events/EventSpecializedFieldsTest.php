<?php

use App\Models\Event;
use App\Models\User;

function specialized_event_payload(array $overrides = []): array
{
    return array_merge([
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Special Occasions',
        'church_activity_category' => 'Dedication',
        'specialist_workflow' => true,
        'event_name' => 'Family Dedication Gathering',
        'description' => '',
        'event_date' => now()->addDays(14)->toDateString(),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'venue' => 'Main Church',
        'venue_address' => '123 Church Road',
    ], $overrides);
}

it('saves dedication-specific details on a church event', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $this->actingAs($user)
        ->post(route('events.store'), specialized_event_payload([
            'child_name' => 'Ana Santos',
            'mother_name' => 'Maria Santos',
            'father_name' => 'Juan Santos',
            'officiating_pastor' => 'Pastor Reyes',
        ]))
        ->assertRedirect();

    $event = Event::query()->where('event_name', 'Family Dedication Gathering')->firstOrFail();

    expect($event->child_name)->toBe('Ana Santos')
        ->and($event->mother_name)->toBe('Maria Santos')
        ->and($event->father_name)->toBe('Juan Santos')
        ->and($event->officiating_pastor)->toBe('Pastor Reyes');

    $this->get(route('events.show', $event))
        ->assertInertia(fn ($page) => $page
            ->where('event.mother_name', 'Maria Santos')
            ->where('event.father_name', 'Juan Santos')
            ->where('event.officiating_pastor', 'Pastor Reyes'));
});

it('saves emergency and class details for Vacation Bible School', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $this->actingAs($user)
        ->post(route('events.store'), specialized_event_payload([
            'church_category' => 'Kids Ministry',
            'church_activity_category' => 'Vacation Bible School',
            'event_name' => 'Vacation Bible School Week',
            'emergency_contact_name' => 'Maria Santos',
            'emergency_contact_number' => '09171234567',
            'medical_notes' => 'No known allergies',
            'parental_consent' => true,
            'assigned_teacher' => 'Juan Reyes',
            'program_day_session' => 'Day 1 - Morning',
        ]))
        ->assertRedirect();

    $event = Event::query()->where('event_name', 'Vacation Bible School Week')->firstOrFail();

    expect($event->emergency_contact_name)->toBe('Maria Santos')
        ->and($event->emergency_contact_number)->toBe('09171234567')
        ->and($event->medical_notes)->toBe('No known allergies')
        ->and($event->parental_consent)->toBeTrue()
        ->and($event->assigned_teacher)->toBe('Juan Reyes')
        ->and($event->program_day_session)->toBe('Day 1 - Morning');
});

it('allows generic church events without specialized details', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $this->actingAs($user)
        ->post(route('events.store'), specialized_event_payload([
            'church_category' => 'Services',
            'church_activity_category' => 'Sunday Service',
            'event_name' => 'Sunday Worship Service',
            'child_name' => 'Must Be Ignored',
            'emergency_contact_name' => 'Must Also Be Ignored',
        ]))
        ->assertRedirect();

    $event = Event::query()->where('event_name', 'Sunday Worship Service')->firstOrFail();

    expect($event->child_name)->toBeNull()
        ->and($event->mother_name)->toBeNull()
        ->and($event->father_name)->toBeNull()
        ->and($event->officiating_pastor)->toBeNull()
        ->and($event->emergency_contact_name)->toBeNull()
        ->and($event->child_name)->toBeNull()
        ->and($event->assigned_teacher)->toBeNull();
});

it('does not save church specialized fields on running events', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $payload = specialized_event_payload([
        'event_kind' => 'running',
        'event_type' => 'community_run',
        'church_category' => null,
        'church_activity_category' => null,
        'specialist_workflow' => false,
        'event_name' => 'Community Run Specialized Field Guard',
        'distance_value' => 5,
        'distance_unit' => 'km',
        'child_name' => 'Should Not Persist',
        'mother_name' => 'Should Not Persist',
    ]);

    $this->actingAs($user)->post(route('events.store'), $payload)->assertRedirect();

    $event = Event::query()->where('event_name', 'Community Run Specialized Field Guard')->firstOrFail();

    expect($event->child_name)->toBeNull()
        ->and($event->mother_name)->toBeNull();
});
