<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Carbon;

it('defaults the event index to running and never mixes kinds', function () {
    $staff = User::factory()->create(['role' => 'admin']);
    $running = Event::create(['event_name' => 'Run', 'event_type' => 'community_run', 'created_by' => $staff->id]);
    $church = Event::create(['event_name' => 'Church', 'event_type' => 'church', 'event_kind' => 'church', 'created_by' => $staff->id]);
    foreach (['/events', '/events?kind=all', '/events?kind=invalid'] as $url) {
        $this->actingAs($staff)->get($url)->assertInertia(fn ($page) => $page->where('filters.kind', 'running')->has('events', 1)->where('events.0.id', $running->id));
    }
    $this->get('/events?kind=church')->assertInertia(fn ($page) => $page->has('events', 1)->where('events.0.id', $church->id));
});

it('keeps the live attendance totals independent of a full-name search', function () {
    $staff = User::factory()->create(['role' => 'admin']);
    $event = Event::create(['event_name' => 'Run', 'event_type' => 'community_run', 'status' => 'ongoing', 'event_date' => today(), 'created_by' => $staff->id]);
    foreach (['Maria', 'Juan'] as $name) {
        $person = Participant::create(['pool' => 'running', 'first_name' => $name, 'middle_name' => 'Reyes', 'last_name' => 'Santos', 'suffix' => 'Jr.']);
        $r = Registration::create(['event_id' => $event->id, 'participant_id' => $person->id, 'registration_status' => 'confirmed', 'source' => 'form']);
        $r->attendance()->create(['attendance_status' => 'present', 'attendance_time' => now(), 'recorded_by' => $staff->id]);
    }
    $this->actingAs($staff)->get("/events/{$event->id}/attendance?q=Maria%20Reyes%20Santos%20Jr.")
        ->assertInertia(fn ($page) => $page->has('rows', 1)->where('rows.0.participant.full_name', 'Maria Reyes Santos Jr.')
            ->where('check_in_summary.total', 2)->where('check_in_summary.present', 2)->has('check_in_summary.recent', 2));
});

it('keeps church events out of the registration queue', function () {
    $staff = User::factory()->create(['role' => 'admin']);
    Event::create(['event_name' => 'Church', 'event_type' => 'church', 'event_kind' => 'church', 'created_by' => $staff->id]);
    $this->actingAs($staff)->get('/registrations')
        ->assertInertia(fn ($page) => $page->has('needsForm', 0)->has('open', 0)->has('closed', 0));
});

it('shows a next-day run once its attendance window opens before midnight', function () {
    $this->travelTo(Carbon::parse('2026-10-21 23:45:00'));
    $staff = User::factory()->create(['role' => 'admin']);
    $event = Event::create(['event_name' => 'Midnight Run', 'event_type' => 'community_run', 'status' => 'registration_open', 'event_date' => '2026-10-22', 'start_time' => '00:30', 'created_by' => $staff->id]);
    expect($event->canViewAttendance())->toBeTrue();
    $this->actingAs($staff)->get('/attendance')
        ->assertInertia(fn ($page) => $page->has('today', 1)->where('today.0.id', $event->id));
    $this->travelBack();
});

it('does not offer running dashboard actions or statuses for church events', function () {
    $staff = User::factory()->create(['role' => 'admin']);
    Event::create(['event_name' => 'Church', 'event_type' => 'church', 'event_kind' => 'church', 'event_date' => today()->addDay(), 'created_by' => $staff->id]);
    $this->actingAs($staff)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->has('actionItems', 0)->has('upcoming', 0));
});
