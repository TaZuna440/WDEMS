<?php

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->staff = User::factory()->create(['role' => 'admin', 'email_two_factor_enabled' => false]);
    $this->actingAs($this->staff);
    $this->event = Event::create(['created_by' => $this->staff->id, 'event_name' => 'Paper run', 'event_type' => 'community_run',
        'status' => 'ongoing', 'event_date' => today(), 'registration_form_version' => 2,
        'registration_common_field_requirements' => ['email' => true, 'address' => true, 'contact_number' => false],
        'registration_tshirt' => ['label' => 'Shirt', 'required' => true, 'choices' => ['Small', 'Medium', 'Large'], 'photos' => []]]);
    $this->event->forceFill(['registration_form_version' => 2, 'registration_tshirt' => ['label' => 'Shirt', 'required' => true, 'choices' => ['Small', 'Medium', 'Large'], 'photos' => []]])->save();
    $this->paper = ['first_name' => 'Maria', 'last_name' => 'Santos', 'birthday' => '2000-01-01', 'email' => 'maria@example.com',
        'address' => 'Manila City', 'emergency_contact_first_name' => 'Jose', 'emergency_contact_last_name' => 'Santos',
        'emergency_contact_relationship' => 'Parent', 'emergency_contact_number' => '09171234567',
        'tshirt_choice' => 'Medium', 'encoding_mode' => 'deferred', 'arrival_time' => '08:15', 'force_open' => true];
});

test('paper encoding records staff attestation and full form data without OTP', function () {
    $this->post(route('events.attendance.encode-paper', $this->event), $this->paper)->assertSessionHasNoErrors();
    $registration = Registration::with('attendance')->sole();
    expect($registration->source)->toBe('form')
        ->and($registration->encoded_by)->toBe($this->staff->id)
        ->and($registration->contact_attested_at)->not->toBeNull()
        ->and($registration->emergency_contact_name)->toBe('Jose Santos')
        ->and($registration->tshirt_choice)->toBe('Medium')
        ->and($registration->attendance->attendance_time->format('H:i'))->toBe('08:15')
        ->and($registration->attendance->verified_at)->toBeNull();
    Mail::assertNothingSent();
    $this->post(route('events.attendance.encode-paper', $this->event), $this->paper)->assertSessionHasErrors('identity');
    expect(Registration::count())->toBe(1);
});

test('paper encoding enforces public required fields and church isolation', function () {
    $this->post(route('events.attendance.encode-paper', $this->event), [...$this->paper, 'email' => '', 'tshirt_choice' => 'Invalid'])
        ->assertSessionHasErrors(['email', 'tshirt_choice']);
    expect(Registration::count())->toBe(0);
    $this->event->forceFill(['event_kind' => 'church'])->save();
    $this->post(route('events.attendance.encode-paper', $this->event), $this->paper)->assertForbidden();
});
