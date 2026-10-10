<?php

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers Phase B of docs/attendance-redesign.md: the bulk-mark
 * endpoint behind Confirm mode.
 *
 * Two request shapes:
 *   - registration_ids: explicit list, all-or-nothing.
 *   - mark_all_visible + filter + q: matches the current search,
 *     not the current page (D10).
 *
 * Helpers prefixed bmk_ to avoid Pest function collisions.
 */

function bmk_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

function bmk_admin(): User
{
    return User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

/**
 * @param array<string, mixed> $overrides
 */
function bmk_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => bmk_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'BMK Test Run',
        'event_date' => today()->toDateString(),
        'status' => 'registration_closed',
        'registration_form_saved_at' => now(),
    ], $overrides));
}

/**
 * @param array<string, mixed> $overrides
 */
function bmk_participant(array $overrides = []): Participant
{
    return Participant::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'age' => 28,
        'email' => 'p'.uniqid().'@example.com',
    ], $overrides));
}

/**
 * @param array<string, mixed> $overrides
 */
function bmk_registration(Event $event, ?Participant $participant = null, array $overrides = []): Registration
{
    $participant ??= bmk_participant();

    return Registration::create(array_merge([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'registration_date' => now(),
        'registration_status' => 'confirmed',
        'source' => 'form',
        'registered_at' => now(),
    ], $overrides));
}

// ---------------------------------------------------------------------------
// Explicit list shape
// ---------------------------------------------------------------------------

test('bulk mark by explicit list marks the submitted registrations', function () {
    $staff = bmk_staff();
    $event = bmk_event();

    $a = bmk_registration($event);
    $b = bmk_registration($event);
    $c = bmk_registration($event);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-mark", [
            'registration_ids' => [$a->id, $b->id],
        ])
        ->assertRedirect();

    expect($a->fresh()->attendance)->not->toBeNull();
    expect($b->fresh()->attendance)->not->toBeNull();
    expect($c->fresh()->attendance)->toBeNull();
});

test('bulk mark sets status to present with attendance_time recorded', function () {
    $staff = bmk_staff();
    $event = bmk_event();
    $a = bmk_registration($event);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-mark", [
            'registration_ids' => [$a->id],
        ])
        ->assertRedirect();

    $attendance = $a->fresh()->attendance;
    expect($attendance)->not->toBeNull();
    expect($attendance->attendance_status)->toBe(AttendanceStatus::Present);
    expect($attendance->attendance_time)->not->toBeNull();
    expect($attendance->recorded_by)->toBe($staff->id);
});

test('bulk mark rejects a registration id from another event', function () {
    $staff = bmk_staff();
    $eventA = bmk_event();
    $eventB = bmk_event(['event_name' => 'Other BMK Run']);

    $regA = bmk_registration($eventA);
    $regB = bmk_registration($eventB);

    $this->actingAs($staff)
        ->post("/events/{$eventA->id}/attendance/bulk-mark", [
            'registration_ids' => [$regA->id, $regB->id],
        ])
        ->assertStatus(422);

    expect($regA->fresh()->attendance)->toBeNull();
    expect($regB->fresh()->attendance)->toBeNull();
});

test('bulk mark with an empty explicit list is rejected', function () {
    $staff = bmk_staff();
    $event = bmk_event();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-mark", [
            'registration_ids' => [],
        ])
        ->assertSessionHasErrors('shape');
});

// ---------------------------------------------------------------------------
// Mark-all-visible shape
// ---------------------------------------------------------------------------

test('bulk mark all visible with filter unmarked marks every unmarked row', function () {
    $staff = bmk_staff();
    $event = bmk_event();

    $a = bmk_registration($event);
    $b = bmk_registration($event);
    $c = bmk_registration($event);

    // Pre-mark c as present. It should not be affected.
    Attendance::create([
        'registration_id' => $c->id,
        'attendance_status' => AttendanceStatus::Present,
        'attendance_time' => now(),
        'recorded_by' => $staff->id,
    ]);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-mark", [
            'mark_all_visible' => true,
            'filter' => 'unmarked',
            'q' => '',
        ])
        ->assertRedirect();

    expect($a->fresh()->attendance)->not->toBeNull();
    expect($b->fresh()->attendance)->not->toBeNull();
    expect($c->fresh()->attendance)->not->toBeNull();
});

test('bulk mark all visible respects the search scope', function () {
    $staff = bmk_staff();
    $event = bmk_event();

    $maria = bmk_registration($event, bmk_participant(['first_name' => 'Maria']));
    $juan = bmk_registration($event, bmk_participant(['first_name' => 'Juan']));

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-mark", [
            'mark_all_visible' => true,
            'filter' => 'all',
            'q' => 'Maria',
        ])
        ->assertRedirect();

    expect($maria->fresh()->attendance)->not->toBeNull();
    expect($juan->fresh()->attendance)->toBeNull();
});

test('bulk mark all visible is scoped to the current event', function () {
    $staff = bmk_staff();
    $eventA = bmk_event();
    $eventB = bmk_event(['event_name' => 'Other BMK Run']);

    $regA = bmk_registration($eventA);
    $regB = bmk_registration($eventB);

    $this->actingAs($staff)
        ->post("/events/{$eventA->id}/attendance/bulk-mark", [
            'mark_all_visible' => true,
            'filter' => 'all',
            'q' => '',
        ])
        ->assertRedirect();

    expect($regA->fresh()->attendance)->not->toBeNull();
    expect($regB->fresh()->attendance)->toBeNull();
});

// ---------------------------------------------------------------------------
// Shape validation
// ---------------------------------------------------------------------------

test('bulk mark with both shapes at once is rejected', function () {
    $staff = bmk_staff();
    $event = bmk_event();
    $a = bmk_registration($event);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-mark", [
            'registration_ids' => [$a->id],
            'mark_all_visible' => true,
            'filter' => 'all',
            'q' => '',
        ])
        ->assertSessionHasErrors('shape');
});

test('bulk mark with neither shape is rejected', function () {
    $staff = bmk_staff();
    $event = bmk_event();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-mark", [])
        ->assertSessionHasErrors('shape');
});

// ---------------------------------------------------------------------------
// Idempotency
// ---------------------------------------------------------------------------

test('bulk mark is idempotent — running twice does not duplicate attendance', function () {
    $staff = bmk_staff();
    $event = bmk_event();
    $a = bmk_registration($event);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-mark", [
            'registration_ids' => [$a->id],
        ])
        ->assertRedirect();

    $firstTime = $a->fresh()->attendance->attendance_time;

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-mark", [
            'registration_ids' => [$a->id],
        ])
        ->assertRedirect();

    expect(Attendance::where('registration_id', $a->id)->count())->toBe(1);
    // The second call overwrites attendance_time — that's the
    // updateOrCreate contract. Not testing equality; just
    // confirming one row exists.
});

// ---------------------------------------------------------------------------
// Gate and force-open
// ---------------------------------------------------------------------------

test('bulk mark is forbidden when the window is closed and no force_open', function () {
    $staff = bmk_staff();
    $event = bmk_event(['event_date' => today()->addDays(3)->toDateString()]);
    $a = bmk_registration($event);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-mark", [
            'registration_ids' => [$a->id],
        ])
        ->assertForbidden();

    expect($a->fresh()->attendance)->toBeNull();
});

test('bulk mark succeeds on a completed event with force_open for admin', function () {
    $admin = bmk_admin();
    $event = bmk_event([
        'status' => 'completed',
        'event_date' => today()->subDays(3)->toDateString(),
    ]);
    $a = bmk_registration($event);

    $this->actingAs($admin)
        ->post("/events/{$event->id}/attendance/bulk-mark?force_open=1", [
            'registration_ids' => [$a->id],
        ])
        ->assertRedirect();

    expect($a->fresh()->attendance)->not->toBeNull();
});
