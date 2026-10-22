<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard counts active registrations and present attendance only', function () {
    $user = User::factory()->create(['role' => 'admin', 'email_two_factor_enabled' => false]);
    $event = Event::create(['created_by' => $user->id, 'event_kind' => 'running', 'event_type' => 'community_run', 'event_name' => 'Dashboard run']);
    foreach (['confirmed', 'confirmed', 'held', 'duplicate', 'removed'] as $i => $status) {
        $person = Participant::create(['pool' => 'running', 'first_name' => 'Person', 'last_name' => "Number{$i}"]);
        $row = $event->registrations()->create(['participant_id' => $person->id, 'registration_status' => $status, 'source' => 'form']);
        if ($status === 'confirmed') {
            $row->attendance()->create(['attendance_status' => $i === 0 ? 'present' : 'absent']);
        }
    }
    $this->actingAs($user)->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('stats.1.value', 2)->where('stats.2.value', 1)->where('stats.2.subtext', '50% show rate')->has('recentActivity', 2));
});

test('church dashboard uses church lifecycle and excludes finished events', function () {
    $user = User::factory()->create(['role' => 'admin', 'email_two_factor_enabled' => false]);
    foreach (['upcoming', 'completed', 'cancelled'] as $status) {
        Event::create(['created_by' => $user->id, 'event_kind' => 'church', 'event_type' => 'church', 'event_name' => "Church {$status}", 'church_status' => $status, 'event_date' => today()->addWeek()]);
    }
    $this->actingAs($user)->get('/dashboard?side=church')->assertInertia(fn ($page) => $page
        ->has('upcoming', 1)->where('upcoming.0.status', 'upcoming')->has('actionItems', 1)
        ->where('actionItems.0.status', 'upcoming')->where('stats.2.value', '—'));
});
