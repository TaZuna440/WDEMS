<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * Pins the semantics of the live / deferred encoding modes on the
 * single-row walk-in endpoint.
 *
 * Live (default) — the operator registers the participant at the
 * table. attendance_time is set to now().
 *
 * Deferred — the operator transcribes a paper form. attendance_time
 * is set to the event's date at the submitted arrival_time, so the
 * record preserves when the participant actually arrived.
 *
 * Helpers are prefixed attdef_ to avoid Pest function collisions with
 * AttendanceTest (att2_), AttendanceVerificationTest (attv_),
 * AttendanceDateGateTest (attgate_), and RegistrationFormTest
 * (regform_).
 */
function attdef_staff(): User
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
function attdef_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => attdef_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'Attdef Test Run',
        'event_date' => '2026-10-10',
        'status' => 'registration_closed',
        'registration_form_saved_at' => now(),
        'registration_common_field_requirements' => [
            'email' => true,
            'contact_number' => false,
            'address' => false,
        ],
    ], $overrides));
}

/**
 * A minimal valid walk-in payload. Overrides merge on top.
 *
 * Both email and contact_number are supplied. Phone is structurally
 * required on every event regardless of the requirements map —
 * see AI-CONTEXT.md §18. Email is supplied here for symmetry with
 * the common walk-in shape and to keep the identity resolver on its
 * email path when both are present.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function attdef_payload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Walk',
        'last_name' => 'In',
        'age' => 30,
        'email' => 'walkin@example.com',
        'contact_number' => '09171234567',
        'mark_present' => true,
    ], $overrides);
}

it('defaults to live mode and stamps attendance_time with now when the mode is omitted', function () {
    $event = attdef_event();
    $staff = attdef_staff();

    Carbon::setTestNow('2026-10-10 07:30:00');

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", attdef_payload())
        ->assertRedirect();

    $attendance = Attendance::latest('id')->first();
    expect($attendance)->not->toBeNull()
        ->and($attendance->attendance_time->toDateTimeString())
        ->toBe('2026-10-10 07:30:00');

    Carbon::setTestNow();
});

it('treats an explicit live mode the same as the omitted default', function () {
    $event = attdef_event();
    $staff = attdef_staff();

    Carbon::setTestNow('2026-10-10 07:30:00');

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", attdef_payload([
            'encoding_mode' => 'live',
        ]))
        ->assertRedirect();

    $attendance = Attendance::latest('id')->first();
    expect($attendance->attendance_time->toDateTimeString())
        ->toBe('2026-10-10 07:30:00');

    Carbon::setTestNow();
});

it('uses the submitted arrival_time in deferred mode', function () {
    $event = attdef_event();
    $staff = attdef_staff();

    Carbon::setTestNow('2026-10-15 09:00:00');

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", attdef_payload([
            'encoding_mode' => 'deferred',
            'arrival_time' => '05:15',
        ]))
        ->assertRedirect();

    $attendance = Attendance::latest('id')->first();
    expect($attendance->attendance_time->toDateTimeString())
        ->toBe('2026-10-10 05:15:00');

    Carbon::setTestNow();
});

it('rejects a deferred submission without an arrival_time', function () {
    $event = attdef_event();
    $staff = attdef_staff();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", attdef_payload([
            'encoding_mode' => 'deferred',
        ]))
        ->assertSessionHasErrors('arrival_time');

    expect(Attendance::count())->toBe(0);
});

it('rejects a deferred submission with a malformed arrival_time', function () {
    $event = attdef_event();
    $staff = attdef_staff();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", attdef_payload([
            'encoding_mode' => 'deferred',
            'arrival_time' => 'not-a-time',
        ]))
        ->assertSessionHasErrors('arrival_time');

    expect(Attendance::count())->toBe(0);
});

it('ignores a submitted arrival_time when the mode is live', function () {
    $event = attdef_event();
    $staff = attdef_staff();

    Carbon::setTestNow('2026-10-15 09:00:00');

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", attdef_payload([
            'encoding_mode' => 'live',
            'arrival_time' => '05:15',
        ]))
        ->assertRedirect();

    $attendance = Attendance::latest('id')->first();
    expect($attendance->attendance_time->toDateTimeString())
        ->toBe('2026-10-15 09:00:00');

    Carbon::setTestNow();
});

it('rejects an unknown encoding_mode value', function () {
    $event = attdef_event();
    $staff = attdef_staff();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", attdef_payload([
            'encoding_mode' => 'yesterday',
        ]))
        ->assertSessionHasErrors('encoding_mode');

    expect(Attendance::count())->toBe(0);
});
