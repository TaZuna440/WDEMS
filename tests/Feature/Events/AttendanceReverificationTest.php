<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(now()->setTime(9, 0));
});

/**
 * Pins the re-mark behavior on an already-verified attendance row.
 *
 * Before A.5, `AttendanceController::mark()` wrote
 * `$verified_at = null` on every mark that did not carry a matching
 * code -- including re-marks of a previously code-verified row.
 * Editing a note on a verified row silently destroyed the
 * verification the participant had earned. The pre-A.5 test suite
 * never caught it, because every existing test either created a
 * fresh row (existing was null anyway) or exercised the absence
 * branch (shouldRecordTime was false).
 *
 * A.5 changed the default to preserve the existing value:
 *
 *   $verifiedAt = $shouldRecordTime
 *       ? $registration->attendance?->verified_at
 *       : null;
 *
 * These tests exercise the four branches of that ternary on a row
 * that starts verified:
 *
 *   - Present, no code            -> preserved  (the fix)
 *   - Late,    no code            -> preserved  (the fix)
 *   - Present, wrong code         -> preserved  (the fix, new behavior)
 *   - Absent,  no code            -> null       (unchanged: absence
 *                                                cannot be proven by
 *                                                a code)
 *
 * Helpers prefixed reverify_ to avoid Pest collisions.
 */

function reverify_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

/**
 * Create a running event whose attendance window is open and a
 * registration with a known attendance_code.
 *
 * @return array{event: Event, registration: Registration, attendance: Attendance}
 */
function reverify_verified_row(string $code = 'KM4X-P2N7'): array
{
    $staff = reverify_staff();

    $event = Event::create([
        'created_by' => $staff->id,
        'event_kind' => 'running',
        'event_type' => 'community_run',
        'event_name' => 'Reverify Test '.uniqid(),
        'event_date' => today()->toDateString(),
        'start_time' => '06:00',
        'venue' => 'Start Line',
        'venue_address' => '456 Run Street',
        'distance_value' => 5.0,
        'distance_unit' => 'km',
        'status' => 'ongoing',
    ]);

    $participant = Participant::create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria-'.uniqid().'@example.com',
    ]);

    $registration = Registration::create([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'registration_date' => now(),
        'registration_status' => 'confirmed',
        'source' => 'form',
        'registered_at' => now(),
        'attendance_code' => $code,
    ]);

    // Seed the row in a verified state -- as if the participant had
    // already presented the code at the table and been marked Present.
    $attendance = Attendance::create([
        'registration_id' => $registration->id,
        'attendance_status' => 'present',
        'attendance_time' => now()->subMinutes(5),
        'recorded_by' => $staff->id,
        'verified_at' => now()->subMinutes(5),
    ]);

    return [
        'event' => $event,
        'registration' => $registration,
        'attendance' => $attendance,
        'staff' => $staff,
        'code' => $code,
    ];
}

// ---------------------------------------------------------------------------
// Verified_at is preserved when re-marking a verified row
// ---------------------------------------------------------------------------

test('re-marking Present without a code preserves verified_at', function () {
    $ctx = reverify_verified_row();
    $originalVerifiedAt = $ctx['attendance']->verified_at;

    expect($originalVerifiedAt)->not->toBeNull();

    $this->actingAs($ctx['staff'])
        ->post(route('events.attendance.mark', [
            'event' => $ctx['event']->id,
            'registration' => $ctx['registration']->id,
        ]), [
            'status' => 'present',
            'notes' => 'Added a note after arrival.',
        ])
        ->assertRedirect();

    $ctx['attendance']->refresh();
    expect($ctx['attendance']->verified_at)->not->toBeNull();
    expect($ctx['attendance']->verified_at->toIso8601String())
        ->toBe($originalVerifiedAt->toIso8601String());
});

test('rejecting Late leaves existing verification unchanged', function () {
    $ctx = reverify_verified_row();
    $originalVerifiedAt = $ctx['attendance']->verified_at;

    $this->actingAs($ctx['staff'])
        ->post(route('events.attendance.mark', [
            'event' => $ctx['event']->id,
            'registration' => $ctx['registration']->id,
        ]), [
            'status' => 'late',
        ])
        ->assertSessionHasErrors('status');

    $ctx['attendance']->refresh();
    expect($ctx['attendance']->verified_at)->not->toBeNull();
    expect($ctx['attendance']->verified_at->toIso8601String())
        ->toBe($originalVerifiedAt->toIso8601String());
});

test('re-marking Present with a wrong code preserves verified_at', function () {
    // Pre-fix, submitting a code that did not match zeroed out a
    // verification the participant had already earned. A staff typo
    // destroyed the proof of arrival. Post-fix, the existing
    // verification survives.
    $ctx = reverify_verified_row();
    $originalVerifiedAt = $ctx['attendance']->verified_at;

    $this->actingAs($ctx['staff'])
        ->post(route('events.attendance.mark', [
            'event' => $ctx['event']->id,
            'registration' => $ctx['registration']->id,
        ]), [
            'status' => 'present',
            'verified_code' => 'XXXX-YYYY',
        ])
        ->assertRedirect();

    $ctx['attendance']->refresh();
    expect($ctx['attendance']->verified_at)->not->toBeNull();
    expect($ctx['attendance']->verified_at->toIso8601String())
        ->toBe($originalVerifiedAt->toIso8601String());
});

// ---------------------------------------------------------------------------
// Absence strips verified_at -- a code cannot prove absence
// ---------------------------------------------------------------------------

test('re-marking Absent clears verified_at', function () {
    $ctx = reverify_verified_row();

    $this->actingAs($ctx['staff'])
        ->post(route('events.attendance.mark', [
            'event' => $ctx['event']->id,
            'registration' => $ctx['registration']->id,
        ]), [
            'status' => 'absent',
        ])
        ->assertRedirect();

    $ctx['attendance']->refresh();
    expect($ctx['attendance']->verified_at)->toBeNull();
});

// ---------------------------------------------------------------------------
// Re-marking with a matching code refreshes verified_at
// ---------------------------------------------------------------------------

test('re-marking Present with the matching code refreshes verified_at', function () {
    // A second submit with the matching code sets verified_at to
    // now() again -- the same code, freshly proven. The value
    // changes; it stays non-null.
    $ctx = reverify_verified_row();
    $originalVerifiedAt = $ctx['attendance']->verified_at;

    $this->travel(2)->minutes();

    $this->actingAs($ctx['staff'])
        ->post(route('events.attendance.mark', [
            'event' => $ctx['event']->id,
            'registration' => $ctx['registration']->id,
        ]), [
            'status' => 'present',
            'verified_code' => $ctx['code'],
        ])
        ->assertRedirect();

    $ctx['attendance']->refresh();
    expect($ctx['attendance']->verified_at)->not->toBeNull();
    expect($ctx['attendance']->verified_at->toIso8601String())
        ->not->toBe($originalVerifiedAt->toIso8601String());
});
