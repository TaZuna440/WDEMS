<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Pins the semantics of the live / deferred encoding modes on the
 * bulk walk-in endpoint.
 *
 * Encoding mode is a single top-level field for the whole batch.
 * Live (default) — each row's attendance_time is now(). Deferred —
 * each row's attendance_time is computed from the event's date and
 * that row's own arrival_time.
 *
 * Helpers are prefixed attbulk_ to avoid Pest function collisions
 * with AttendanceTest (att2_), AttendanceVerificationTest (attv_),
 * AttendanceEncodingModeTest (attdef_), AttendanceDateGateTest
 * (attgate_), and RegistrationFormTest (regform_).
 */
function attbulk_staff(): User
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
function attbulk_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => attbulk_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'Attbulk Test Run',
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
 * One row of a bulk payload.
 *
 * Both email and contact_number default to a value that is unique
 * per call. The identity model normalizes phone globally and
 * rejects two rows in the same batch sharing a normalized phone
 * (see AttendanceController::persistBulkWalkInRow()). If this
 * helper returned the same phone for every row, any test sending
 * more than one row would silently fail on the second row with a
 * `rows.{i}.identity` error that the redirect assertion does not
 * catch.
 *
 * Tests that need a specific email or phone override the field.
 * Rows that leave the default inherit a distinct value.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function attbulk_row(array $overrides = []): array
{
    static $counter = 0;
    $counter++;

    return array_merge([
        'client_uuid' => (string) Str::uuid(),
        'first_name' => 'Walk',
        'last_name' => 'In',
        'age' => 30,
        'email' => "walkin{$counter}@example.com",
        // Valid PH mobile: 09 + 9 digits. Counter keeps them distinct
        // within a run; RefreshDatabase resets the DB between tests.
        'contact_number' => '0917123'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
    ], $overrides);
}

it('defaults to live mode and stamps every row with now when the mode is omitted', function () {
    $event = attbulk_event();
    $staff = attbulk_staff();

    Carbon::setTestNow('2026-10-10 07:30:00');

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => [
                attbulk_row(['email' => 'a@example.com']),
                attbulk_row(['email' => 'b@example.com']),
            ],
        ])
        ->assertRedirect();

    expect(Attendance::count())->toBe(2);

    Attendance::all()->each(function (Attendance $a) {
        expect($a->attendance_time->toDateTimeString())
            ->toBe('2026-10-10 07:30:00');
    });

    Carbon::setTestNow();
});

it('treats an explicit live mode the same as the omitted default', function () {
    $event = attbulk_event();
    $staff = attbulk_staff();

    Carbon::setTestNow('2026-10-10 07:30:00');

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'encoding_mode' => 'live',
            'rows' => [attbulk_row(['email' => 'a@example.com'])],
        ])
        ->assertRedirect();

    expect(Attendance::latest('id')->first()->attendance_time->toDateTimeString())
        ->toBe('2026-10-10 07:30:00');

    Carbon::setTestNow();
});

it('uses each row\'s own arrival_time in deferred mode', function () {
    $event = attbulk_event();
    $staff = attbulk_staff();

    Carbon::setTestNow('2026-10-15 09:00:00');

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'encoding_mode' => 'deferred',
            'rows' => [
                attbulk_row(['email' => 'early@example.com', 'arrival_time' => '05:15']),
                attbulk_row(['email' => 'late@example.com', 'arrival_time' => '06:45']),
            ],
        ])
        ->assertRedirect();

    expect(Attendance::count())->toBe(2);

    $early = Attendance::whereHas('registration.participant', fn ($q) => $q->where('email', 'early@example.com'))->first();
    $late = Attendance::whereHas('registration.participant', fn ($q) => $q->where('email', 'late@example.com'))->first();

    expect($early)->not->toBeNull()
        ->and($early->attendance_time->toDateTimeString())->toBe('2026-10-10 05:15:00')
        ->and($late)->not->toBeNull()
        ->and($late->attendance_time->toDateTimeString())->toBe('2026-10-10 06:45:00');

    Carbon::setTestNow();
});

it('rejects a deferred batch when one row is missing arrival_time', function () {
    $event = attbulk_event();
    $staff = attbulk_staff();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'encoding_mode' => 'deferred',
            'rows' => [
                attbulk_row(['email' => 'has@example.com', 'arrival_time' => '05:15']),
                attbulk_row(['email' => 'missing@example.com']),
            ],
        ])
        ->assertSessionHasErrors('rows.1.arrival_time');

    expect(Attendance::count())->toBe(0)
        ->and(Registration::count())->toBe(0)
        ->and(Participant::count())->toBe(0);
});

it('rejects a deferred batch with a malformed arrival_time', function () {
    $event = attbulk_event();
    $staff = attbulk_staff();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'encoding_mode' => 'deferred',
            'rows' => [
                attbulk_row(['email' => 'bad@example.com', 'arrival_time' => 'not-a-time']),
            ],
        ])
        ->assertSessionHasErrors('rows.0.arrival_time');

    expect(Attendance::count())->toBe(0);
});

it('ignores per-row arrival_time when the batch mode is live', function () {
    $event = attbulk_event();
    $staff = attbulk_staff();

    Carbon::setTestNow('2026-10-15 09:00:00');

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'encoding_mode' => 'live',
            'rows' => [
                attbulk_row(['email' => 'ignored@example.com', 'arrival_time' => '05:15']),
            ],
        ])
        ->assertRedirect();

    expect(Attendance::latest('id')->first()->attendance_time->toDateTimeString())
        ->toBe('2026-10-15 09:00:00');

    Carbon::setTestNow();
});

it('rejects an unknown batch encoding_mode value', function () {
    $event = attbulk_event();
    $staff = attbulk_staff();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'encoding_mode' => 'someday',
            'rows' => [attbulk_row(['email' => 'a@example.com'])],
        ])
        ->assertSessionHasErrors('encoding_mode');

    expect(Attendance::count())->toBe(0);
});
