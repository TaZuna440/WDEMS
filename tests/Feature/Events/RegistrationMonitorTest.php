<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\Date;

/**
 * Covers Phase 7a of the participant-identity-and-monitoring plan:
 * the RegistrationMonitorController and its two routes.
 *
 * Time mocking: capture the real base time ONCE per test, then
 * compute every mock offset from that captured value. Calling
 * `Date::setTestNow(now()->subX)` in sequence drifts, because now()
 * inside the argument reads the previous mock.
 *
 * Helpers prefixed monitor_ to avoid Pest collisions.
 */

function monitor_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

/**
 * @param array<string, mixed> $overrides
 */
function monitor_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => monitor_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'Monitor Test Run',
        'event_date' => now()->addWeek()->toDateString(),
        'status' => 'registration_open',
        'registration_slug' => 'monitor'.random_int(1000, 9999),
        'registration_start' => now(),
        'registration_form_saved_at' => now(),
    ], $overrides));
}

function monitor_registration(Event $event, array $participantOverrides = []): Registration
{
    $participant = Participant::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'age' => 28,
        'email' => 'maria'.uniqid().'@example.com',
    ], $participantOverrides));

    return Registration::create([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'registration_date' => now(),
        'registration_status' => 'confirmed',
        'source' => 'form',
        'registered_at' => now(),
    ]);
}

// ---------------------------------------------------------------------------
// Landing — /registrations/monitor
// ---------------------------------------------------------------------------

test('the landing page lists open events', function () {
    $staff = monitor_staff();
    monitor_event(['event_name' => 'Open Run']);

    $this->actingAs($staff)
        ->get('/registrations/monitor')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('registrations/monitor/index')
            ->has('events', 1)
            ->where('events.0.event_name', 'Open Run')
        );
});

test('the landing page excludes non-open events', function () {
    $staff = monitor_staff();
    monitor_event(['event_name' => 'Draft', 'status' => 'draft']);
    monitor_event(['event_name' => 'Closed', 'status' => 'registration_closed']);
    monitor_event(['event_name' => 'Completed', 'status' => 'completed']);

    $this->actingAs($staff)
        ->get('/registrations/monitor')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('events', 0));
});

test('the landing page sorts by most recent submission', function () {
    $staff = monitor_staff();

    $base = Date::now();

    $olderEvent = monitor_event(['event_name' => 'Older']);
    $newerEvent = monitor_event(['event_name' => 'Newer']);

    // Older event gets a submission 30 minutes before the base.
    Date::setTestNow($base->subMinutes(30));
    monitor_registration($olderEvent);

    // Newer event gets a submission at the base time.
    Date::setTestNow($base);
    monitor_registration($newerEvent);

    Date::setTestNow();

    $this->actingAs($staff)
        ->get('/registrations/monitor')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('events.0.event_name', 'Newer')
            ->where('events.1.event_name', 'Older')
        );
});

test('the landing page reports the total and last-hour count per event', function () {
    $staff = monitor_staff();
    $event = monitor_event();

    $base = Date::now();

    // One registration three hours before base — outside the hour window.
    Date::setTestNow($base->subHours(3));
    monitor_registration($event);

    // Two registrations inside the hour window.
    Date::setTestNow($base->subMinutes(20));
    monitor_registration($event);

    Date::setTestNow($base->subMinutes(5));
    monitor_registration($event);

    Date::setTestNow();

    $this->actingAs($staff)
        ->get('/registrations/monitor')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('events.0.total', 3)
            ->where('events.0.last_hour', 2)
        );
});

// ---------------------------------------------------------------------------
// Show — /registrations/monitor/{event}
// ---------------------------------------------------------------------------

test('the show page renders for a registration_open event', function () {
    $staff = monitor_staff();
    $event = monitor_event();

    $this->actingAs($staff)
        ->get("/registrations/monitor/{$event->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('registrations/monitor/show')
            ->where('event.id', $event->id)
            ->where('event.is_open', true)
        );
});

test('the show page renders for a registration_closed event', function () {
    $staff = monitor_staff();
    $event = monitor_event(['status' => 'registration_closed']);

    $this->actingAs($staff)
        ->get("/registrations/monitor/{$event->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('registrations/monitor/show')
            ->where('event.is_open', false)
        );
});

test('the show page returns 404 for a draft event', function () {
    $staff = monitor_staff();
    $event = monitor_event(['status' => 'draft']);

    $this->actingAs($staff)
        ->get("/registrations/monitor/{$event->id}")
        ->assertNotFound();
});

test('the show page returns 404 for a completed event', function () {
    $staff = monitor_staff();
    $event = monitor_event(['status' => 'completed']);

    $this->actingAs($staff)
        ->get("/registrations/monitor/{$event->id}")
        ->assertNotFound();
});

test('the show page computes the three rate windows', function () {
    $staff = monitor_staff();
    $event = monitor_event();

    $base = Date::now();

    // One registration 90 min before base — outside both windows.
    Date::setTestNow($base->subMinutes(90));
    monitor_registration($event);

    // Two registrations 30 min before base — inside the hour window only.
    Date::setTestNow($base->subMinutes(30));
    monitor_registration($event);
    monitor_registration($event);

    // One registration 5 min before base — inside both windows.
    Date::setTestNow($base->subMinutes(5));
    monitor_registration($event);

    Date::setTestNow();

    $this->actingAs($staff)
        ->get("/registrations/monitor/{$event->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total', 4)
            ->where('stats.last_hour', 3)
            ->where('stats.last_15min', 1)
        );
});

test('the show page classifies first-time participants as new', function () {
    $staff = monitor_staff();
    $event = monitor_event();
    monitor_registration($event);

    $this->actingAs($staff)
        ->get("/registrations/monitor/{$event->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('registrations.0.is_returning', false)
            ->where('registrations.0.other_events_count', 0)
        );
});

test('the show page classifies a participant with prior events as returning', function () {
    $staff = monitor_staff();
    $priorEvent = monitor_event(['event_name' => 'Prior', 'status' => 'registration_closed']);

    $participant = Participant::create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'age' => 28,
        'email' => 'maria@example.com',
    ]);

    Registration::create([
        'event_id' => $priorEvent->id,
        'participant_id' => $participant->id,
        'registration_date' => now()->subMonth(),
        'registration_status' => 'confirmed',
        'source' => 'form',
        'registered_at' => now()->subMonth(),
    ]);

    $currentEvent = monitor_event(['event_name' => 'Current']);
    Registration::create([
        'event_id' => $currentEvent->id,
        'participant_id' => $participant->id,
        'registration_date' => now(),
        'registration_status' => 'confirmed',
        'source' => 'form',
        'registered_at' => now(),
    ]);

    $this->actingAs($staff)
        ->get("/registrations/monitor/{$currentEvent->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('registrations.0.is_returning', true)
            ->where('registrations.0.other_events_count', 1)
        );
});

// ---------------------------------------------------------------------------
// Integration — openRegistration redirect
// ---------------------------------------------------------------------------

test('opening registration redirects to the monitor show page', function () {
    $staff = monitor_staff();

    $event = Event::create([
        'created_by' => $staff->id,
        'event_type' => 'community_run',
        'event_name' => 'Redirect Test',
        'event_date' => now()->addWeek()->toDateString(),
        'status' => 'draft',
        'registration_form_saved_at' => now(),
    ]);

    $response = $this->actingAs($staff)
        ->post("/events/{$event->id}/open-registration");

    $response->assertRedirect(route('registration-monitor.show', $event));
});
