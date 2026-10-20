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
 * Pins the semantics of attendances.verified_at.
 *
 * A mark through the code-lookup path with a matching code and a
 * presence status (Present or Late) sets verified_at. Everything
 * else — search-and-click, bulk, wrong code, absence statuses —
 * leaves it null. The report distinguishes the two.
 *
 * Helpers are prefixed attv_ to avoid Pest function collisions with
 * AttendanceTest (att2_), AttendanceDateGateTest (attgate_), and
 * RegistrationFormTest (regform_).
 */

function attv_staff(): User
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
function attv_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => attv_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'Attv Test Run',
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

/**
 * Event with a form registration carrying the given attendance code.
 *
 * @return array{0: Event, 1: Registration}
 */
function attv_event_with_registration(string $code = 'KM4X-P2N7'): array
{
    $event = attv_event();

    $participant = Participant::create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'contact_number' => '09171234567',
        'email' => 'maria@example.com',
        'age' => 30,
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

    return [$event, $registration];
}

it('sets verified_at when a matching code is submitted with present', function () {
    [$event, $registration] = attv_event_with_registration();
    $staff = attv_staff();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/{$registration->id}/mark", [
            'status' => 'present',
            'verified_code' => 'KM4X-P2N7',
        ])
        ->assertRedirect();

    $attendance = Attendance::where('registration_id', $registration->id)->first();
    expect($attendance)->not->toBeNull()
        ->and($attendance->verified_at)->not->toBeNull()
        ->and($attendance->attendance_status)->toBe(AttendanceStatus::Present);
});

it('accepts a lowercase code and normalizes it', function () {
    [$event, $registration] = attv_event_with_registration();
    $staff = attv_staff();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/{$registration->id}/mark", [
            'status' => 'present',
            'verified_code' => 'km4xp2n7',
        ])
        ->assertRedirect();

    expect(Attendance::where('registration_id', $registration->id)->first()->verified_at)->not->toBeNull();
});

it('accepts a code submitted without the dash', function () {
    [$event, $registration] = attv_event_with_registration();
    $staff = attv_staff();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/{$registration->id}/mark", [
            'status' => 'present',
            'verified_code' => 'KM4XP2N7',
        ])
        ->assertRedirect();

    expect(Attendance::where('registration_id', $registration->id)->first()->verified_at)->not->toBeNull();
});

it('rejects late for new attendance entries even with a matching code', function () {
    [$event, $registration] = attv_event_with_registration();
    $staff = attv_staff();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/{$registration->id}/mark", [
            'status' => 'late',
            'verified_code' => 'KM4X-P2N7',
        ])
        ->assertSessionHasErrors('status');

    expect(Attendance::where('registration_id', $registration->id)->exists())->toBeFalse();
});

it('leaves verified_at null when no code is submitted', function () {
    [$event, $registration] = attv_event_with_registration();
    $staff = attv_staff();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/{$registration->id}/mark", [
            'status' => 'present',
        ])
        ->assertRedirect();

    expect(Attendance::where('registration_id', $registration->id)->first()->verified_at)->toBeNull();
});

it('leaves verified_at null when the code does not match', function () {
    [$event, $registration] = attv_event_with_registration();
    $staff = attv_staff();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/{$registration->id}/mark", [
            'status' => 'present',
            'verified_code' => 'XXXX-YYYY',
        ])
        ->assertRedirect();

    expect(Attendance::where('registration_id', $registration->id)->first()->verified_at)->toBeNull();
});

it('leaves verified_at null for absent even with a matching code', function () {
    [$event, $registration] = attv_event_with_registration();
    $staff = attv_staff();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/{$registration->id}/mark", [
            'status' => 'absent',
            'verified_code' => 'KM4X-P2N7',
        ])
        ->assertRedirect();

    $attendance = Attendance::where('registration_id', $registration->id)->first();
    expect($attendance->verified_at)->toBeNull()
        ->and($attendance->attendance_status)->toBe(AttendanceStatus::Absent);
});

it('rejects excused for new attendance entries even with a matching code', function () {
    [$event, $registration] = attv_event_with_registration();
    $staff = attv_staff();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/{$registration->id}/mark", [
            'status' => 'excused',
            'verified_code' => 'KM4X-P2N7',
        ])
        ->assertSessionHasErrors('status');

    expect(Attendance::where('registration_id', $registration->id)->exists())->toBeFalse();
});

it('leaves verified_at null for walk-in registrations which have no code', function () {
    $event = attv_event();
    $staff = attv_staff();

    $participant = Participant::create([
        'first_name' => 'Walk',
        'last_name' => 'In',
        'contact_number' => '09998887777',
        'email' => 'walkin@example.com',
        'age' => 25,
    ]);

    $registration = Registration::create([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'registration_date' => now(),
        'registration_status' => 'confirmed',
        'source' => 'walk_in',
        'registered_at' => now(),
        'attendance_code' => null,
    ]);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/{$registration->id}/mark", [
            'status' => 'present',
            'verified_code' => 'KM4X-P2N7',
        ])
        ->assertRedirect();

    expect(Attendance::where('registration_id', $registration->id)->first()->verified_at)->toBeNull();
});

it('bulk mark never sets verified_at', function () {
    [$event, $registration] = attv_event_with_registration();
    $staff = attv_staff();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-mark", [
            'registration_ids' => [$registration->id],
        ])
        ->assertRedirect();

    expect(Attendance::where('registration_id', $registration->id)->first()->verified_at)->toBeNull();
});
