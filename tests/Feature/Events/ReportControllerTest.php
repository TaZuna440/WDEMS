<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Pins the HTTP behavior of ReportController.
 *
 * The service-level tests (ReportServiceTest) cover the aggregate
 * logic. These tests cover routing, guards, and the CSV download
 * response.
 *
 * The `AdvanceEventStatus` middleware runs on this route. Tests
 * whose subject is `registration_closed` must use a future
 * event_date so the middleware stays out of the way. Same rule as
 * EventFinalizeTest.
 *
 * Helpers are prefixed rpth_ to avoid Pest function collisions.
 */
function rpth_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function rpth_event(array $overrides = []): Event
{
    static $counter = 0;
    $counter++;

    return Event::create(array_merge([
        'created_by' => rpth_staff()->id,
        'event_type' => 'community_run',
        'event_name' => "Report HTTP Event {$counter}",
        'event_date' => today()->toDateString(),
        'start_time' => '08:00',
        'distance_value' => 5,
        'distance_unit' => 'km',
        'venue' => 'Test Venue',
        'venue_address' => 'Test Address',
        'status' => 'completed',
        'registration_form_saved_at' => now(),
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function rpth_participant(array $overrides = []): Participant
{
    static $counter = 0;
    $counter++;

    return Participant::create(array_merge(['pool' => 'running',
        'first_name' => 'Test',
        'last_name' => "ReportParticipant{$counter}",
        'email' => "rp{$counter}@example.com",
        'contact_number' => '0918123'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
        'age' => 30,
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function rpth_registration(
    Event $event,
    Participant $participant,
    array $overrides = [],
): Registration {
    return Registration::create(array_merge([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'registration_date' => now(),
        'registration_status' => 'confirmed',
        'source' => 'form',
        'registered_at' => now(),
    ], $overrides));
}

function rpth_attend(
    Registration $registration,
    string $status = 'present',
    ?string $verifiedAt = null,
): Attendance {
    return Attendance::create([
        'registration_id' => $registration->id,
        'attendance_status' => $status,
        'attendance_time' => in_array($status, ['present', 'late'], true) ? now() : null,
        'verified_at' => $verifiedAt,
    ]);
}

it('renders the report for a completed event', function () {
    $event = rpth_event(['status' => 'completed']);
    $staff = rpth_staff();

    $this->actingAs($staff)
        ->get("/events/{$event->id}/report")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/report')
            ->where('event.id', $event->id)
            ->where('event.status', 'completed')
            ->has('summary')
            ->has('cross_event')
            ->has('participants')
        );
});

it('renders the report for an ongoing event', function () {
    $event = rpth_event(['status' => 'ongoing']);
    $staff = rpth_staff();

    $this->actingAs($staff)
        ->get("/events/{$event->id}/report")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/report')
            ->where('event.status', 'ongoing')
        );
});

it('returns 404 for a draft event', function () {
    $event = rpth_event(['status' => 'draft']);
    $staff = rpth_staff();

    $this->actingAs($staff)
        ->get("/events/{$event->id}/report")
        ->assertNotFound();
});

it('returns 404 for a registration_open event', function () {
    $event = rpth_event(['status' => 'registration_open', 'event_date' => today()->addWeek()->toDateString()]);
    $staff = rpth_staff();

    $this->actingAs($staff)
        ->get("/events/{$event->id}/report")
        ->assertNotFound();
});

it('returns 404 for a registration_closed event', function () {
    // Future event_date so AdvanceEventStatus does not flip the
    // status to ongoing before the controller runs. The subject is
    // the controller's guard, not the middleware.
    $event = rpth_event([
        'status' => 'registration_closed',
        'event_date' => today()->addWeek()->toDateString(),
    ]);
    $staff = rpth_staff();

    $this->actingAs($staff)
        ->get("/events/{$event->id}/report")
        ->assertNotFound();
});

it('returns 404 for a cancelled event', function () {
    $event = rpth_event(['status' => 'cancelled']);
    $staff = rpth_staff();

    $this->actingAs($staff)
        ->get("/events/{$event->id}/report")
        ->assertNotFound();
});

it('redirects unauthenticated requests to login', function () {
    $event = rpth_event();

    $this->get("/events/{$event->id}/report")
        ->assertRedirect('/login');
});

it('includes the summary figures in the payload', function () {
    $event = rpth_event();
    $staff = rpth_staff();

    $reg1 = rpth_registration($event, rpth_participant());
    $reg2 = rpth_registration($event, rpth_participant());

    rpth_attend($reg1, 'present', now()->toIso8601String());
    rpth_attend($reg2, 'present', null);

    $this->actingAs($staff)
        ->get("/events/{$event->id}/report")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.registered_form', 2)
            ->where('summary.attended_verified', 1)
            ->where('summary.attended_override', 1)
            ->where('summary.attended_form_total', 2)
        );
});

it('includes the participant rows in the payload', function () {
    $event = rpth_event();
    $staff = rpth_staff();

    rpth_registration($event, rpth_participant());
    rpth_registration($event, rpth_participant());

    $this->actingAs($staff)
        ->get("/events/{$event->id}/report")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('participants', 2));
});

it('streams a CSV with attachment headers', function () {
    $event = rpth_event();
    $staff = rpth_staff();

    rpth_registration($event, rpth_participant());

    $response = $this->actingAs($staff)
        ->get("/events/{$event->id}/report/export");

    $response->assertOk();

    // Streamed responses don't set Content-Disposition in the same
    // header bag during tests; the StreamedResponse is captured by
    // the test harness. Assert on the wrapper instead.
    $this->assertStringContainsString(
        'text/csv',
        (string) $response->headers->get('Content-Type'),
    );

    $this->assertStringContainsString(
        'attachment',
        (string) $response->headers->get('Content-Disposition'),
    );
});

it('streams a CSV with the expected header row and BOM', function () {
    $event = rpth_event();
    $staff = rpth_staff();

    $participant = rpth_participant();
    rpth_registration($event, $participant);

    $response = $this->actingAs($staff)
        ->get("/events/{$event->id}/report/export");

    $response->assertOk();

    $body = $response->streamedContent();

    expect($body)->toStartWith("\xEF\xBB\xBF")
        ->and($body)->toContain('Name,Contact,Source,Attendance')
        ->and($body)->toContain($participant->last_name);
});

it('returns 404 on export for a non-reportable event', function () {
    $event = rpth_event(['status' => 'draft']);
    $staff = rpth_staff();

    $this->actingAs($staff)
        ->get("/events/{$event->id}/report/export")
        ->assertNotFound();
});

it('redirects unauthenticated export requests to login', function () {
    $event = rpth_event();

    $this->get("/events/{$event->id}/report/export")
        ->assertRedirect('/login');
});

it('rejects church report and CSV even with a running-compatible status', function () {
    $event = rpth_event(['event_kind' => 'church', 'status' => 'completed']);
    $this->actingAs(rpth_staff())->get("/events/{$event->id}/report")->assertNotFound();
    $this->get("/events/{$event->id}/report/export")->assertNotFound();
});
