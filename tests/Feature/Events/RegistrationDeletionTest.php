<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\RegistrationField;
use App\Models\RegistrationFieldResponse;
use App\Models\User;

function registration_ctx(string $status = 'registration_open', string $role = 'staff'): array
{
    $user = User::factory()->create(['role' => $role]);

    $event = Event::create([
        'created_by' => $user->id,
        'event_type' => 'community_run',
        'event_name' => 'Registration Deletion Event',
        'event_date' => now()->toDateString(),
        'status' => $status,
    ]);

    $participant = Participant::create([
        'pool' => 'running',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria@example.com',
    ]);

    $registration = Registration::create([
        'registration_status' => 'confirmed',
        'event_id' => $event->id,
        'participant_id' => $participant->id,
    ]);

    return [$user, $event, $registration];
}

it('deletes a registration with the correct password', function () {
    [$staff, $event, $registration] = registration_ctx();

    $response = $this->actingAs($staff)
        ->delete("/registrations/{$registration->id}", ['password' => 'password']);

    $response->assertRedirect(route('registration-monitor.show', ['event' => $event->id]));
    expect(Registration::find($registration->id))->toBeNull();
});

it('does not delete a registration when the password is incorrect', function () {
    [$staff, , $registration] = registration_ctx();

    $response = $this->actingAs($staff)
        ->delete("/registrations/{$registration->id}", ['password' => 'incorrect']);

    $response->assertSessionHasErrors([
        'password' => 'The provided password does not match our records.',
    ]);
    expect(Registration::find($registration->id))->not->toBeNull();
});

it('deletes with a correct password without requiring an active OTP', function () {
    [$staff, $event, $registration] = registration_ctx();

    $this->actingAs($staff)
        ->delete("/registrations/{$registration->id}", ['password' => 'password'])
        ->assertRedirect(route('registration-monitor.show', ['event' => $event->id]))
        ->assertSessionHasNoErrors();

    expect(Registration::find($registration->id))->toBeNull();
});

it('allows deletion while the event is ongoing and visible in the monitor', function () {
    [$staff, $event, $registration] = registration_ctx('ongoing');

    $this->actingAs($staff)
        ->delete("/registrations/{$registration->id}", ['password' => 'password'])
        ->assertRedirect(route('registration-monitor.show', ['event' => $event->id]));

    expect(Registration::find($registration->id))->toBeNull();
});

it('requires password confirmation for admins too', function () {
    [$admin, , $registration] = registration_ctx(role: 'admin');

    $this->actingAs($admin)
        ->delete("/registrations/{$registration->id}")
        ->assertSessionHasErrors('password');

    expect(Registration::find($registration->id))->not->toBeNull();
});

it('does not expose registration deletion OTP endpoints', function () {
    [$staff, , $registration] = registration_ctx();

    $this->actingAs($staff)
        ->post("/registrations/{$registration->id}/deletion/request-otp")
        ->assertNotFound();

    $this->post("/registrations/{$registration->id}/deletion/verify-otp", [
        'code' => '123456',
    ])->assertNotFound();
});

it('cascades field responses and attendances but preserves the participant', function () {
    [$staff, $event, $registration] = registration_ctx();

    $field = RegistrationField::create([
        'event_id' => $event->id,
        'label' => 'Shirt Size',
        'field_type' => 'text',
        'is_required' => false,
        'display_order' => 0,
    ]);

    $response = RegistrationFieldResponse::create([
        'registration_id' => $registration->id,
        'registration_field_id' => $field->id,
        'value' => 'M',
    ]);

    $attendance = Attendance::create([
        'registration_id' => $registration->id,
        'attendance_status' => 'present',
        'attendance_time' => now(),
        'recorded_by' => $staff->id,
    ]);

    $participantId = $registration->participant_id;

    $this->actingAs($staff)
        ->delete("/registrations/{$registration->id}", ['password' => 'password'])
        ->assertRedirect();

    expect(Registration::find($registration->id))->toBeNull();
    expect(RegistrationFieldResponse::find($response->id))->toBeNull();
    expect(Attendance::find($attendance->id))->toBeNull();
    expect(Participant::find($participantId))->not->toBeNull();
});

it('rejects deletion when the event is not in a manageable status', function () {
    [$staff, , $registration] = registration_ctx('completed');

    $this->actingAs($staff)
        ->delete("/registrations/{$registration->id}", ['password' => 'password'])
        ->assertForbidden();

    expect(Registration::find($registration->id))->not->toBeNull();
});

it('blocks guests from deleting registrations', function () {
    [, , $registration] = registration_ctx();

    $this->delete("/registrations/{$registration->id}", ['password' => 'password'])
        ->assertRedirect(route('login'));

    expect(Registration::find($registration->id))->not->toBeNull();
});
