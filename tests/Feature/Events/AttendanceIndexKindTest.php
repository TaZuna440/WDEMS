<?php

use App\Models\Event;
use App\Models\User;

it('groups only church events on the church attendance index', function () {
    $staff = User::factory()->create([
        'role' => 'admin',
        'email_two_factor_enabled' => false,
    ]);
    $upcoming = Event::create([
        'created_by' => $staff->id,
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Youth',
        'church_status' => 'upcoming',
        'event_name' => 'Youth Gathering',
        'event_date' => today()->toDateString(),
    ]);
    $ongoing = Event::create([
        'created_by' => $staff->id,
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Kids Ministry',
        'church_status' => 'ongoing',
        'event_name' => 'Kids Gathering',
        'event_date' => today()->toDateString(),
    ]);
    $running = Event::create([
        'created_by' => $staff->id,
        'event_kind' => 'running',
        'event_type' => 'community_run',
        'status' => 'ongoing',
        'event_name' => 'Community Run',
        'event_date' => today()->toDateString(),
    ]);

    $this->actingAs($staff)
        ->get('/attendance?kind=church')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('kind', 'church')
            ->has('categories', 9)
            ->where(
                'groups.needsAttention',
                fn ($rows) => collect($rows)->contains('event_name', 'Youth Gathering'),
            )
            ->where(
                'groups.open',
                fn ($rows) => collect($rows)->contains('event_name', 'Kids Gathering')
                    && ! collect($rows)->contains('event_name', 'Community Run'),
            ));
});
