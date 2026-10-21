<?php

use App\Mail\RegistrationDeletionOtpMail;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\RegistrationField;
use App\Models\RegistrationFieldResponse;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

/**
 * Build the fixture triplet every test needs: a staff user, an event
 * in a manageable status, and a registration on that event.
 *
 * The event status defaults to `registration_open` because
 * RegistrationDeletionController::ensureManageable() rejects
 * draft / ongoing / completed / cancelled with 403. The event
 * deletion tests do not need this — their controller has no status
 * gate — but the registration flow does.
 */
function registration_ctx(string $status = 'registration_open'): array
{
    $staff = User::factory()->create(['role' => 'staff']);

    $event = Event::create([
        'created_by' => $staff->id,
        'event_type' => 'community_run',
        'event_name' => 'Registration OTP Event',
        'event_date' => now()->toDateString(),
        'status' => $status,
    ]);

    $participant = Participant::create(['pool' => 'running',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria@example.com',
    ]);

    $registration = Registration::create(['registration_status' => 'confirmed',
        'event_id' => $event->id,
        'participant_id' => $participant->id,
    ]);

    return [$staff, $event, $registration];
}

function admin_registration_ctx(string $status = 'registration_open'): array
{
    $admin = User::factory()->create(['role' => 'admin']);

    $event = Event::create([
        'created_by' => $admin->id,
        'event_type' => 'community_run',
        'event_name' => 'Registration OTP Event',
        'event_date' => now()->toDateString(),
        'status' => $status,
    ]);

    $participant = Participant::create(['pool' => 'running',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria@example.com',
    ]);

    $registration = Registration::create(['registration_status' => 'confirmed',
        'event_id' => $event->id,
        'participant_id' => $participant->id,
    ]);

    return [$admin, $event, $registration];
}

function capture_registration_otp_code(): string
{
    $code = null;

    Mail::assertSent(RegistrationDeletionOtpMail::class, function (RegistrationDeletionOtpMail $mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    if ($code === null) {
        throw new RuntimeException('No registration-deletion OTP email was sent.');
    }

    return $code;
}

// ---------------------------------------------------------------------
// Staff OTP path
// ---------------------------------------------------------------------

it('lets staff request an OTP and sends the code via mail', function () {
    [$staff, , $registration] = registration_ctx();

    $response = $this->actingAs($staff)
        ->post("/registrations/{$registration->id}/deletion/request-otp");

    $response->assertRedirect();
    Mail::assertSent(RegistrationDeletionOtpMail::class);
});

it('rejects a second OTP request within the cooldown window', function () {
    [$staff, , $registration] = registration_ctx();

    $this->actingAs($staff)
        ->post("/registrations/{$registration->id}/deletion/request-otp");

    $second = $this->actingAs($staff)
        ->post("/registrations/{$registration->id}/deletion/request-otp");

    $second->assertSessionHasErrors('code');
});

it('deletes the registration when staff verifies with the correct code', function () {
    [$staff, $event, $registration] = registration_ctx();

    $this->actingAs($staff)
        ->post("/registrations/{$registration->id}/deletion/request-otp");

    $code = capture_registration_otp_code();

    $response = $this->actingAs($staff)
        ->post("/registrations/{$registration->id}/deletion/verify-otp", ['code' => $code]);

    $response->assertRedirect(route('registration-monitor.show', ['event' => $event->id]));
    expect(Registration::find($registration->id))->toBeNull();
});

it('does not delete the registration when staff supplies the wrong code', function () {
    [$staff, , $registration] = registration_ctx();

    $this->actingAs($staff)
        ->post("/registrations/{$registration->id}/deletion/request-otp");

    $response = $this->actingAs($staff)
        ->post("/registrations/{$registration->id}/deletion/verify-otp", ['code' => '000000']);

    $response->assertSessionHasErrors('code');
    expect(Registration::find($registration->id))->not->toBeNull();
});

it('locks after five wrong verification attempts', function () {
    [$staff, , $registration] = registration_ctx();

    $this->actingAs($staff)
        ->post("/registrations/{$registration->id}/deletion/request-otp");

    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($staff)
            ->post("/registrations/{$registration->id}/deletion/verify-otp", ['code' => '000000'])
            ->assertSessionHasErrors('code');
    }

    expect(Registration::find($registration->id))->not->toBeNull();
});

it('returns an error when verifying without requesting a code', function () {
    [$staff, , $registration] = registration_ctx();

    $response = $this->actingAs($staff)
        ->post("/registrations/{$registration->id}/deletion/verify-otp", ['code' => '123456']);

    $response->assertSessionHasErrors('code');
    expect(Registration::find($registration->id))->not->toBeNull();
});

it('does not let staff delete directly via the destroy endpoint', function () {
    [$staff, , $registration] = registration_ctx();

    $response = $this->actingAs($staff)
        ->delete("/registrations/{$registration->id}");

    $response->assertForbidden();
    expect(Registration::find($registration->id))->not->toBeNull();
});

// ---------------------------------------------------------------------
// Admin direct path
// ---------------------------------------------------------------------

it('lets an admin delete a registration directly without OTP', function () {
    [$admin, $event, $registration] = admin_registration_ctx();

    $response = $this->actingAs($admin)
        ->delete("/registrations/{$registration->id}");

    $response->assertRedirect(route('registration-monitor.show', ['event' => $event->id]));
    expect(Registration::find($registration->id))->toBeNull();
    Mail::assertNothingSent();
});

it('cascades field responses and attendances but preserves the participant', function () {
    [$admin, $event, $registration] = admin_registration_ctx();

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
        'recorded_by' => $admin->id,
    ]);

    $participantId = $registration->participant_id;

    $this->actingAs($admin)->delete("/registrations/{$registration->id}");

    expect(Registration::find($registration->id))->toBeNull();
    expect(RegistrationFieldResponse::find($response->id))->toBeNull();
    expect(Attendance::find($attendance->id))->toBeNull();
    expect(Participant::find($participantId))->not->toBeNull();
});

// ---------------------------------------------------------------------
// Admin blocked from OTP endpoints
// ---------------------------------------------------------------------

it('does not let admin hit the request-otp endpoint', function () {
    [$admin, , $registration] = admin_registration_ctx();

    $response = $this->actingAs($admin)
        ->post("/registrations/{$registration->id}/deletion/request-otp");

    $response->assertForbidden();
});

it('does not let admin hit the verify-otp endpoint', function () {
    [$admin, , $registration] = admin_registration_ctx();

    $response = $this->actingAs($admin)
        ->post("/registrations/{$registration->id}/deletion/verify-otp", ['code' => '123456']);

    $response->assertForbidden();
});

// ---------------------------------------------------------------------
// Status gate
// ---------------------------------------------------------------------

it('rejects deletion when the event is not in a manageable status', function () {
    [$admin, , $registration] = admin_registration_ctx('draft');

    $response = $this->actingAs($admin)
        ->delete("/registrations/{$registration->id}");

    $response->assertForbidden();
    expect(Registration::find($registration->id))->not->toBeNull();
});

it('rejects staff OTP request when the event is not in a manageable status', function () {
    [$staff, , $registration] = registration_ctx('completed');

    $response = $this->actingAs($staff)
        ->post("/registrations/{$registration->id}/deletion/request-otp");

    $response->assertForbidden();
});

// ---------------------------------------------------------------------
// Guest blocked
// ---------------------------------------------------------------------

it('blocks guests from all deletion endpoints', function () {
    [$staff, , $registration] = registration_ctx();

    $this->delete("/registrations/{$registration->id}")
        ->assertRedirect(route('login'));

    $this->post("/registrations/{$registration->id}/deletion/request-otp")
        ->assertRedirect(route('login'));

    $this->post("/registrations/{$registration->id}/deletion/verify-otp", ['code' => '123456'])
        ->assertRedirect(route('login'));

    expect(Registration::find($registration->id))->not->toBeNull();
});
