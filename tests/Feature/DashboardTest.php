<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
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

test('dashboard remembers explicit side switches while query overrides remain visit-only', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'email_two_factor_enabled' => false,
        'dashboard_side' => 'church',
    ]);
    $this->actingAs($user);

    $this->get('/dashboard')->assertInertia(fn ($page) => $page->where('activeSide', 'church'));
    $this->get('/dashboard?side=running')->assertInertia(fn ($page) => $page->where('activeSide', 'running'));
    expect($user->fresh()->dashboard_side)->toBe('church');

    $this->post('/dashboard/side', ['side' => 'running'])->assertRedirect('/dashboard?side=running');
    expect($user->fresh()->dashboard_side)->toBe('running');
    $this->get('/dashboard')->assertInertia(fn ($page) => $page->where('activeSide', 'running'));
});

test('church dashboard links standard modules to their ministry card pages', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'email_two_factor_enabled' => false,
    ]);

    $this->actingAs($user)->get('/dashboard?side=church')->assertInertia(fn ($page) => $page
        ->where('churchCategories.0.href', '/events/church/services')
        ->where('churchCategories.1.href', '/events/church/fellowship')
        ->where('churchCategories.2.href', '/events/church/discipleship'));
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
        ->where('stats.1.value', 2)->where('stats.2.value', 1)->where('stats.2.subtext', '50% show rate')
        ->has('recentActivity', 5));
});

test('church dashboard surfaces held registrations and unmarked events without cross-pool counts', function () {
    $user = User::factory()->create(['role' => 'admin', 'email_two_factor_enabled' => false]);
    $event = Event::create([
        'created_by' => $user->id,
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Services',
        'church_status' => 'ongoing',
        'event_name' => 'Today service',
        'event_date' => today()->toDateString(),
    ]);
    $confirmed = Participant::create(['pool' => 'church', 'first_name' => 'Ana', 'last_name' => 'Member']);
    $event->registrations()->create(['participant_id' => $confirmed->id, 'registration_status' => 'confirmed', 'source' => 'manual']);
    $held = Participant::create(['pool' => 'church', 'first_name' => 'Held', 'last_name' => 'Person']);
    $event->registrations()->create(['participant_id' => $held->id, 'registration_status' => 'held', 'source' => 'manual']);
    $provisional = Participant::create([
        'pool' => 'church',
        'first_name' => 'Quick',
        'last_name' => 'Walkin',
        'provisional_event_id' => $event->id,
    ]);
    $provisionalRegistration = $event->registrations()->create([
        'participant_id' => $provisional->id,
        'registration_status' => 'confirmed',
        'source' => 'manual',
    ]);
    $provisionalRegistration->attendance()->create(['attendance_status' => 'present']);

    $this->actingAs($user)->get('/dashboard?side=church')->assertInertia(fn ($page) => $page
        ->where('stats.3.value', 1)
        ->where('stats.3.href', route('church.attendance', ['event' => $event->id, 'filter' => 'unmarked']))
        ->has('actionItems', 2)
        ->where('actionItems.0.urgency', 'needs_review')
        ->where('actionItems.0.reason', '1 held registration awaiting review')
        ->where('actionItems.0.action.href', route('events.possible-duplicates', $event))
        ->where('actionItems.1.urgency', 'urgent')
        ->where('actionItems.1.reason', 'Today’s event — 1 participant still unmarked')
        ->where('recentActivity.0.description', 'A registration was held for organizer review at Today service'));
});

test('running dashboard reports recent Community Run statistics only', function () {
    $user = User::factory()->create(['role' => 'admin', 'email_two_factor_enabled' => false]);
    $run = Event::create([
        'created_by' => $user->id,
        'event_kind' => 'running',
        'event_type' => 'community_run',
        'event_name' => 'Completed run',
        'event_date' => today()->subWeek()->toDateString(),
        'status' => 'completed',
    ]);
    $firstTimer = Participant::create(['pool' => 'running', 'first_name' => 'First', 'last_name' => 'Timer']);
    $paperRegistration = $run->registrations()->create([
        'participant_id' => $firstTimer->id,
        'registration_status' => 'confirmed',
        'participant_type' => 'First-Timer',
        'source' => 'paper',
    ]);
    $paperRegistration->attendance()->create(['attendance_status' => 'present']);
    $absent = Participant::create(['pool' => 'running', 'first_name' => 'Absent', 'last_name' => 'Runner']);
    $run->registrations()->create([
        'participant_id' => $absent->id,
        'registration_status' => 'confirmed',
        'source' => 'form',
    ])->attendance()->create(['attendance_status' => 'absent']);

    $this->actingAs($user)->get('/dashboard?side=running')->assertInertia(fn ($page) => $page
        ->where('eventStatistics.average_show_rate', 50)
        ->where('eventStatistics.paper_encoded_walk_ins', 1)
        ->where('eventStatistics.first_time_runners_this_month', 1)
        ->has('eventStatistics.recent_runs', 1)
        ->where('eventStatistics.recent_runs.0.id', $run->id)
        ->where('eventStatistics.recent_runs.0.registrations', 2)
        ->where('eventStatistics.recent_runs.0.show_rate', 50));
});

test('church dashboard uses church lifecycle and excludes finished events', function () {
    $user = User::factory()->create(['role' => 'admin', 'email_two_factor_enabled' => false]);
    foreach (['upcoming', 'completed', 'cancelled'] as $status) {
        Event::create(['created_by' => $user->id, 'event_kind' => 'church', 'event_type' => 'church', 'event_name' => "Church {$status}", 'church_status' => $status, 'event_date' => today()->addWeek()]);
    }
    $this->actingAs($user)->get('/dashboard?side=church')->assertInertia(fn ($page) => $page
        ->has('upcoming', 1)->where('upcoming.0.status', 'upcoming')->has('actionItems', 1)
        ->where('actionItems.0.status', 'upcoming')->where('stats.2.value', 0));
});

test('church dashboard ranks the top five church attendees by present marks only', function () {
    $user = User::factory()->create(['role' => 'admin', 'email_two_factor_enabled' => false]);
    $sequence = 0;
    $recordPresent = function (Participant $participant) use ($user, &$sequence): void {
        $sequence++;
        $event = Event::create([
            'created_by' => $user->id,
            'event_kind' => 'church',
            'event_type' => 'church',
            'church_category' => 'Services',
            'church_status' => 'completed',
            'event_name' => "History service {$sequence}",
            'event_date' => today()->subDays($sequence)->toDateString(),
        ]);
        $registration = Registration::create([
            'event_id' => $event->id,
            'participant_id' => $participant->id,
            'source' => 'manual',
            'registration_status' => 'confirmed',
        ]);
        $registration->attendance()->create(['attendance_status' => 'present']);
    };

    $alice = Participant::create(['pool' => 'church', 'first_name' => 'Alice', 'last_name' => 'Able']);
    $bob = Participant::create(['pool' => 'church', 'first_name' => 'Bob', 'last_name' => 'Baker']);
    $cara = Participant::create(['pool' => 'church', 'first_name' => 'Cara', 'last_name' => 'Cruz']);
    $dalia = Participant::create(['pool' => 'church', 'first_name' => 'Dalia', 'last_name' => 'Diaz']);
    $erin = Participant::create(['pool' => 'church', 'first_name' => 'Erin', 'last_name' => 'Evans']);
    $zora = Participant::create(['pool' => 'church', 'first_name' => 'Zora', 'last_name' => 'Zed']);

    foreach ([$alice, $alice, $alice, $bob, $bob, $cara, $cara, $dalia, $erin, $zora] as $person) {
        $recordPresent($person);
    }

    $absent = Participant::create(['pool' => 'church', 'first_name' => 'Absent', 'last_name' => 'Attendee']);
    $absentEvent = Event::create([
        'created_by' => $user->id,
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Fellowship',
        'church_status' => 'completed',
        'event_name' => 'Absent fellowship',
    ]);
    $absentRegistration = Registration::create([
        'event_id' => $absentEvent->id,
        'participant_id' => $absent->id,
        'source' => 'manual',
        'registration_status' => 'confirmed',
    ]);
    $absentRegistration->attendance()->create(['attendance_status' => 'absent']);

    $held = Participant::create(['pool' => 'church', 'first_name' => 'Held', 'last_name' => 'Person']);
    Registration::create([
        'event_id' => $absentEvent->id,
        'participant_id' => $held->id,
        'source' => 'manual',
        'registration_status' => 'held',
    ]);

    $provisionalEvent = Event::create([
        'created_by' => $user->id,
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Services',
        'church_status' => 'completed',
        'event_name' => 'Provisional event',
    ]);
    $provisional = Participant::create([
        'pool' => 'church',
        'first_name' => 'Quick',
        'last_name' => 'Walkin',
        'provisional_event_id' => $provisionalEvent->id,
    ]);
    $provisionalRegistration = Registration::create([
        'event_id' => $provisionalEvent->id,
        'participant_id' => $provisional->id,
        'source' => 'manual',
        'registration_status' => 'confirmed',
    ]);
    $provisionalRegistration->attendance()->create(['attendance_status' => 'present']);

    $runningTwin = Participant::create(['pool' => 'running', 'first_name' => 'Alice', 'last_name' => 'Able']);
    $runningEvent = Event::create([
        'created_by' => $user->id,
        'event_kind' => 'running',
        'event_type' => 'community_run',
        'event_name' => 'Running event',
        'status' => 'completed',
    ]);
    $runningRegistration = Registration::create([
        'event_id' => $runningEvent->id,
        'participant_id' => $runningTwin->id,
        'source' => 'form',
        'registration_status' => 'confirmed',
    ]);
    $runningRegistration->attendance()->create(['attendance_status' => 'present']);

    $this->actingAs($user)->get('/dashboard?side=church')->assertInertia(fn ($page) => $page
        ->has('churchParticipantHistory', 5)
        ->where('churchParticipantHistory.0.full_name', 'Alice Able')
        ->where('churchParticipantHistory.0.attendance_count', 3)
        ->where('churchParticipantHistory.1.full_name', 'Bob Baker')
        ->where('churchParticipantHistory.2.full_name', 'Cara Cruz')
        ->where('churchParticipantHistory.3.full_name', 'Dalia Diaz')
        ->where('churchParticipantHistory.4.full_name', 'Erin Evans')
        ->where('churchParticipantHistory.0.event_types.0', 'Services')
        ->where('churchParticipantHistory.2.attendance_count', 2)
        ->where('churchParticipantHistory.3.attendance_count', 1)
        ->where('churchParticipantHistory.4.attendance_count', 1)
        ->missing('churchParticipantHistory.5'));

    $this->get('/dashboard?side=running')->assertInertia(fn ($page) => $page
        ->has('churchParticipantHistory', 0));
});
