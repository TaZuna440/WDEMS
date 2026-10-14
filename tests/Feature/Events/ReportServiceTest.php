<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use App\Services\Reporting\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Pins the aggregate behavior of ReportService.
 *
 * All tests hit the service directly — no HTTP layer. The service
 * is what does the query work; the controller test file covers
 * routing and guards.
 *
 * event_date is today() throughout. Service tests do not go through
 * AdvanceEventStatus middleware, so an event in registration_closed
 * with a past start moment stays in that state.
 *
 * Helpers are prefixed rpt_ to avoid Pest function collisions.
 */

function rpt_staff(): User
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
function rpt_event(array $overrides = []): Event
{
    static $counter = 0;
    $counter++;

    return Event::create(array_merge([
        'created_by' => rpt_staff()->id,
        'event_type' => 'community_run',
        'event_name' => "Report Test Event {$counter}",
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
 * @param array<string, mixed> $overrides
 */
function rpt_participant(array $overrides = []): Participant
{
    static $counter = 0;
    $counter++;

    return Participant::create(array_merge([
        'first_name' => 'Test',
        'last_name' => "Participant{$counter}",
        'email' => "p{$counter}@example.com",
        'contact_number' => '0917123'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
        'age' => 30,
    ], $overrides));
}

/**
 * @param array<string, mixed> $overrides
 */
function rpt_registration(
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

function rpt_attend(
    Registration $registration,
    string $status,
    ?string $verifiedAt = null,
): Attendance {
    return Attendance::create([
        'registration_id' => $registration->id,
        'attendance_status' => $status,
        'attendance_time' => in_array($status, ['present', 'late'], true) ? now() : null,
        'verified_at' => $verifiedAt,
    ]);
}

function rpt_service(): ReportService
{
    return app(ReportService::class);
}

it('summarizes an empty event without error', function () {
    $event = rpt_event();
    $summary = rpt_service()->build($event)['summary'];

    expect($summary['registered_form'])->toBe(0)
        ->and($summary['attended_verified'])->toBe(0)
        ->and($summary['attended_override'])->toBe(0)
        ->and($summary['walk_ins_encoded'])->toBe(0)
        ->and($summary['total_attendance'])->toBe(0)
        ->and($summary['show_rate'])->toBeNull()
        ->and($summary['verification_rate'])->toBeNull()
        ->and($summary['no_shows'])->toBe(0);
});

it('counts form registrations and walk-ins separately', function () {
    $event = rpt_event();

    rpt_registration($event, rpt_participant());
    rpt_registration($event, rpt_participant());
    rpt_registration($event, rpt_participant(), ['source' => 'walk_in']);
    rpt_registration($event, rpt_participant(), ['source' => 'paper']);

    $summary = rpt_service()->build($event)['summary'];

    expect($summary['registered_form'])->toBe(2)
        ->and($summary['walk_ins_encoded'])->toBe(2);
});

it('splits attended into verified and override by verified_at', function () {
    $event = rpt_event();

    $r1 = rpt_registration($event, rpt_participant());
    $r2 = rpt_registration($event, rpt_participant());
    $r3 = rpt_registration($event, rpt_participant());

    rpt_attend($r1, 'present', now()->toIso8601String());
    rpt_attend($r2, 'late', now()->toIso8601String());
    rpt_attend($r3, 'present', null);

    $summary = rpt_service()->build($event)['summary'];

    expect($summary['attended_verified'])->toBe(2)
        ->and($summary['attended_override'])->toBe(1)
        ->and($summary['attended_form_total'])->toBe(3);
});

it('computes the show rate as attended over registered', function () {
    $event = rpt_event();

    for ($i = 0; $i < 4; $i++) {
        $r = rpt_registration($event, rpt_participant());
        if ($i < 3) {
            rpt_attend($r, 'present', now()->toIso8601String());
        }
    }

    $summary = rpt_service()->build($event)['summary'];

    expect($summary['show_rate'])->toBe(75.0)
        ->and($summary['no_shows'])->toBe(1);
});

it('computes the verification rate as verified over attended', function () {
    $event = rpt_event();

    for ($i = 0; $i < 4; $i++) {
        $r = rpt_registration($event, rpt_participant());
        rpt_attend($r, 'present', $i === 0 ? now()->toIso8601String() : null);
    }

    $summary = rpt_service()->build($event)['summary'];

    expect($summary['verification_rate'])->toBe(25.0);
});

it('builds a status breakdown that sums to registered form', function () {
    $event = rpt_event();

    $r1 = rpt_registration($event, rpt_participant());
    $r2 = rpt_registration($event, rpt_participant());
    $r3 = rpt_registration($event, rpt_participant());
    $r4 = rpt_registration($event, rpt_participant());
    rpt_registration($event, rpt_participant());

    rpt_attend($r1, 'present', now()->toIso8601String());
    rpt_attend($r2, 'late', now()->toIso8601String());
    rpt_attend($r3, 'absent');
    rpt_attend($r4, 'excused');

    $summary = rpt_service()->build($event)['summary'];
    $breakdown = $summary['status_breakdown'];

    expect($breakdown['present'])->toBe(1)
        ->and($breakdown['late'])->toBe(1)
        ->and($breakdown['absent'])->toBe(1)
        ->and($breakdown['excused'])->toBe(1)
        ->and($breakdown['unmarked'])->toBe(1)
        ->and(array_sum($breakdown))->toBe($summary['registered_form']);
});

it('excludes walk-ins from the status breakdown', function () {
    $event = rpt_event();

    rpt_registration($event, rpt_participant());
    $walkIn = rpt_registration($event, rpt_participant(), ['source' => 'walk_in']);
    rpt_attend($walkIn, 'present', now()->toIso8601String());

    $summary = rpt_service()->build($event)['summary'];

    expect(array_sum($summary['status_breakdown']))->toBe(1)
        ->and($summary['status_breakdown']['present'])->toBe(0);
});

it('scopes cross-event rows to the current event type', function () {
    $community = rpt_event(['event_type' => 'community_run']);
    $funRun = rpt_event(['event_type' => 'fun_run']);

    $crossEvent = rpt_service()->build($community)['cross_event'];
    $ids = array_column($crossEvent['rows'], 'id');

    expect($ids)->toContain($community->id)
        ->and($ids)->not->toContain($funRun->id);
});

it('excludes non-reportable events from cross-event', function () {
    $reportable = rpt_event(['status' => 'completed']);
    $draft = rpt_event(['status' => 'draft']);
    $open = rpt_event(['status' => 'registration_open']);
    $closed = rpt_event(['status' => 'registration_closed']);
    $cancelled = rpt_event(['status' => 'cancelled']);

    $crossEvent = rpt_service()->build($reportable)['cross_event'];
    $ids = array_column($crossEvent['rows'], 'id');

    expect($ids)->toContain($reportable->id)
        ->and($ids)->not->toContain($draft->id)
        ->and($ids)->not->toContain($open->id)
        ->and($ids)->not->toContain($closed->id)
        ->and($ids)->not->toContain($cancelled->id);
});

it('marks the current event in cross-event rows', function () {
    $event = rpt_event();
    $rows = rpt_service()->build($event)['cross_event']['rows'];
    $current = collect($rows)->firstWhere('id', $event->id);

    expect($current['is_current'])->toBeTrue();
});

it('computes cross-event average, best, and worst', function () {
    $a = rpt_event();
    rpt_attend(
        rpt_registration($a, rpt_participant()),
        'present',
        now()->toIso8601String(),
    );

    $b = rpt_event();
    rpt_attend(
        rpt_registration($b, rpt_participant()),
        'present',
        now()->toIso8601String(),
    );
    rpt_registration($b, rpt_participant());

    $crossEvent = rpt_service()->build($a)['cross_event'];

    expect($crossEvent['average_show_rate'])->toBe(75.0)
        ->and($crossEvent['best']['show_rate'])->toBe(100.0)
        ->and($crossEvent['worst']['show_rate'])->toBe(50.0);
});

it('attaches a delta against the type average to every row', function () {
    $a = rpt_event();
    rpt_attend(
        rpt_registration($a, rpt_participant()),
        'present',
        now()->toIso8601String(),
    );

    $b = rpt_event();
    rpt_attend(
        rpt_registration($b, rpt_participant()),
        'present',
        now()->toIso8601String(),
    );
    rpt_registration($b, rpt_participant());

    $rows = collect(rpt_service()->build($a)['cross_event']['rows']);
    $rowA = $rows->firstWhere('id', $a->id);
    $rowB = $rows->firstWhere('id', $b->id);

    expect($rowA['delta'])->toBe(25.0)
        ->and($rowB['delta'])->toBe(-25.0);
});

it('returns only participants registered for the current event', function () {
    $event = rpt_event();
    rpt_registration($event, rpt_participant());
    rpt_registration($event, rpt_participant());

    $other = rpt_event();
    rpt_registration($other, rpt_participant());

    $participants = rpt_service()->build($event)['participants'];

    expect($participants)->toHaveCount(2);
});

it('scopes participant history to the current event type', function () {
    $participant = rpt_participant();

    $communityCurrent = rpt_event(['event_type' => 'community_run']);
    $communityPrior = rpt_event([
        'event_type' => 'community_run',
        'event_date' => today()->subMonth()->toDateString(),
    ]);
    $funRunPrior = rpt_event([
        'event_type' => 'fun_run',
        'event_date' => today()->subWeek()->toDateString(),
    ]);

    rpt_registration($communityCurrent, $participant);

    $priorReg = rpt_registration($communityPrior, $participant);
    rpt_attend($priorReg, 'present', now()->toIso8601String());

    $funReg = rpt_registration($funRunPrior, $participant);
    rpt_attend($funReg, 'present', now()->toIso8601String());

    $participants = rpt_service()->build($communityCurrent)['participants'];

    expect($participants[0]['history'])->toHaveCount(1)
        ->and($participants[0]['history'][0]['event_name'])
        ->toBe($communityPrior->event_name);
});

it('excludes the current event from the history list', function () {
    $participant = rpt_participant();
    $event = rpt_event();

    $reg = rpt_registration($event, $participant);
    rpt_attend($reg, 'present', now()->toIso8601String());

    $participants = rpt_service()->build($event)['participants'];

    expect($participants[0]['history'])->toBeEmpty()
        ->and($participants[0]['total_appearances'])->toBe(1);
});

it('includes the current event in total appearances', function () {
    $participant = rpt_participant();

    $prior = rpt_event(['event_date' => today()->subMonth()->toDateString()]);
    $current = rpt_event();

    rpt_attend(
        rpt_registration($prior, $participant),
        'present',
        now()->toIso8601String(),
    );
    rpt_attend(
        rpt_registration($current, $participant),
        'present',
        now()->toIso8601String(),
    );

    $participants = rpt_service()->build($current)['participants'];

    expect($participants[0]['total_appearances'])->toBe(2)
        ->and($participants[0]['last_appearance'])
        ->toBe($prior->event_date->toDateString());
});

it('returns null last appearance for a first-time participant', function () {
    $event = rpt_event();
    $reg = rpt_registration($event, rpt_participant());
    rpt_attend($reg, 'present', now()->toIso8601String());

    $participants = rpt_service()->build($event)['participants'];

    expect($participants[0]['last_appearance'])->toBeNull();
});

it('returns the most recent prior appearance when several exist', function () {
    $participant = rpt_participant();

    $oldest = rpt_event(['event_date' => today()->subMonths(3)->toDateString()]);
    $middle = rpt_event(['event_date' => today()->subMonths(2)->toDateString()]);
    $recent = rpt_event(['event_date' => today()->subMonth()->toDateString()]);
    $current = rpt_event();

    foreach ([$oldest, $middle, $recent] as $e) {
        rpt_attend(
            rpt_registration($e, $participant),
            'present',
            now()->toIso8601String(),
        );
    }

    rpt_registration($current, $participant);

    $participants = rpt_service()->build($current)['participants'];

    expect($participants[0]['last_appearance'])
        ->toBe($recent->event_date->toDateString())
        ->and($participants[0]['history'])->toHaveCount(3);
});

it('produces CSV with a UTF-8 BOM and header row', function () {
    $event = rpt_event();
    rpt_registration($event, rpt_participant());

    $csv = rpt_service()->toCsv($event);

    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('Name,Contact,Source,Attendance');
});

it('produces CSV with N prior-event columns for the deepest history', function () {
    $participant = rpt_participant();

    $a = rpt_event(['event_date' => today()->subMonths(2)->toDateString()]);
    $b = rpt_event(['event_date' => today()->subMonth()->toDateString()]);
    $current = rpt_event();

    foreach ([$a, $b] as $e) {
        rpt_attend(
            rpt_registration($e, $participant),
            'present',
            now()->toIso8601String(),
        );
    }
    rpt_registration($current, $participant);

    $csv = rpt_service()->toCsv($current);

    expect($csv)->toContain('Prior Event 1')
        ->and($csv)->toContain('Prior Event 2')
        ->and($csv)->not->toContain('Prior Event 3');
});
