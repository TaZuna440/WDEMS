<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\User;

it('counts only confirmed church registrations on the Reports side and links to Church Summary', function () {
    $staff = User::factory()->create([
        'role' => 'admin',
        'email_two_factor_enabled' => false,
    ]);
    $this->actingAs($staff);
    $event = Event::create([
        'created_by' => $staff->id,
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Kids Ministry',
        'church_status' => 'completed',
        'event_name' => 'Completed Church Gathering',
        'event_date' => today()->subDay()->toDateString(),
    ]);
    $confirmed = Participant::create([
        'pool' => 'church',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
    ]);
    $held = Participant::create([
        'pool' => 'church',
        'first_name' => 'Ana',
        'last_name' => 'Garcia',
    ]);
    $event->registrations()->create([
        'participant_id' => $confirmed->id,
        'registration_status' => 'confirmed',
        'source' => 'manual',
    ]);
    $event->registrations()->create([
        'participant_id' => $held->id,
        'registration_status' => 'held',
        'source' => 'manual',
    ]);

    $this->get('/reports?kind=church')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('events', 1)
            ->where('events.0.registered_count', 1));
});
