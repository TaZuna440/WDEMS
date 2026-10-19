<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers the birthdate swap Phase 3: the single walk-in endpoint
 * accepts `birthday` (preferred, required going forward per D4) or
 * legacy `age`, mirroring PublicRegistrationRequest's transitional
 * rules. Mirrors BirthdateSwapTest for the public form.
 *
 * Helpers are prefixed wbd_ to avoid Pest function collisions with
 * AttendanceTest (att2_) and friends.
 */

function wbd_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

/**
 * Test event with contact_number and address marked optional — same
 * shape as att2_event(). Today's date + registration_closed keeps
 * canRecordAttendanceNow() true for the walk-in gate.
 *
 * @param array<string, mixed> $overrides
 */
function wbd_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => wbd_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'WBD Test Run',
        'event_date' => today()->toDateString(),
        'status' => 'registration_closed',
        'registration_form_saved_at' => now(),
        'registration_common_field_requirements' => [
            'email' => true,
            'contact_number' => false,
            'address' => false,
        ],
    ], $overrides));
}

function wbd_phone(): string
{
    return '0917'.str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
}

/**
 * Base walk-in payload. Callers override 'email' and add the
 * demographic field(s) under test.
 *
 * @param array<string, mixed> $overrides
 */
function wbd_payload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Walk',
        'last_name' => 'In',
        'contact_number' => wbd_phone(),
        'mark_present' => true,
    ], $overrides);
}

test('walk-in with a birthday persists it and derives age', function () {
    $staff = wbd_staff();
    $event = wbd_event();
    $email = 'wbd-birthday@example.com';
    $birthday = now()->subYears(30)->toDateString();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", wbd_payload([
            'email' => $email,
            'birthday' => $birthday,
        ]))
        ->assertRedirect();

    $participant = Participant::where('email', $email)->firstOrFail();
    expect($participant->birthday->toDateString())->toBe($birthday);
    expect($participant->age)->toBe(Carbon::parse($birthday)->age);

    $registration = Registration::where('participant_id', $participant->id)->firstOrFail();
    expect($registration->source)->toBe('walk_in');
});

test('walk-in with both age and birthday prefers birthday on write', function () {
    $staff = wbd_staff();
    $event = wbd_event();
    $email = 'wbd-both@example.com';
    $birthday = now()->subYears(25)->toDateString();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", wbd_payload([
            'email' => $email,
            'age' => 99,
            'birthday' => $birthday,
        ]))
        ->assertRedirect();

    $participant = Participant::where('email', $email)->firstOrFail();
    expect($participant->birthday->toDateString())->toBe($birthday);
    // The stale age value must not leak through: the accessor
    // derives from birthday, and the raw age column stays null.
    expect($participant->age)->toBe(Carbon::parse($birthday)->age);
    expect($participant->getRawOriginal('age'))->toBeNull();
});

test('walk-in with legacy age still works and leaves birthday null', function () {
    $staff = wbd_staff();
    $event = wbd_event();
    $email = 'wbd-legacy@example.com';

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", wbd_payload([
            'email' => $email,
            'age' => 40,
        ]))
        ->assertRedirect();

    $participant = Participant::where('email', $email)->firstOrFail();
    expect($participant->birthday)->toBeNull();
    expect($participant->age)->toBe(40);
});

test('walk-in rejects a birthday under 3 years old', function () {
    $staff = wbd_staff();
    $event = wbd_event();
    $email = 'wbd-under3@example.com';

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", wbd_payload([
            'email' => $email,
            'birthday' => now()->subYears(2)->toDateString(),
        ]))
        ->assertSessionHasErrors('birthday');

    expect(Participant::where('email', $email)->exists())->toBeFalse();
});

test('walk-in rejects a birthday over 120 years old', function () {
    $staff = wbd_staff();
    $event = wbd_event();
    $email = 'wbd-over120@example.com';

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", wbd_payload([
            'email' => $email,
            'birthday' => now()->subYears(121)->toDateString(),
        ]))
        ->assertSessionHasErrors('birthday');

    expect(Participant::where('email', $email)->exists())->toBeFalse();
});

test('walk-in rejects a future birthday', function () {
    $staff = wbd_staff();
    $event = wbd_event();
    $email = 'wbd-future@example.com';

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", wbd_payload([
            'email' => $email,
            'birthday' => now()->addDay()->toDateString(),
        ]))
        ->assertSessionHasErrors('birthday');

    expect(Participant::where('email', $email)->exists())->toBeFalse();
});

test('walk-in rejects a submission with neither age nor birthday', function () {
    $staff = wbd_staff();
    $event = wbd_event();
    $email = 'wbd-neither@example.com';

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", wbd_payload([
            'email' => $email,
        ]))
        ->assertSessionHasErrors('birthday');

    expect(Participant::where('email', $email)->exists())->toBeFalse();
});

test('walk-in accepts the exact boundary birthdays', function () {
    $staff = wbd_staff();
    $event = wbd_event();

    // Exactly 3 years ago — youngest allowed.
    $youngEmail = 'wbd-boundary-young@example.com';
    $youngBirthday = now()->subYears(3)->toDateString();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", wbd_payload([
            'email' => $youngEmail,
            'birthday' => $youngBirthday,
        ]))
        ->assertRedirect();

    $young = Participant::where('email', $youngEmail)->firstOrFail();
    expect($young->age)->toBe(3);

    // Exactly 120 years ago — oldest allowed.
    $oldEmail = 'wbd-boundary-old@example.com';
    $oldBirthday = now()->subYears(120)->toDateString();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/walk-in", wbd_payload([
            'email' => $oldEmail,
            'birthday' => $oldBirthday,
        ]))
        ->assertRedirect();

    $old = Participant::where('email', $oldEmail)->firstOrFail();
    expect($old->age)->toBe(120);
});
