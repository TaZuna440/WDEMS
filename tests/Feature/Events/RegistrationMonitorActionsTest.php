<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\RegistrationField;
use App\Models\RegistrationFieldResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers the four action endpoints introduced in Phase 8:
 *
 *   - PUT  /registrations/{registration}/participant
 *   - POST /registrations/{registration}/flag
 *   - POST /registrations/{registration}/note
 *   - GET  /registrations/monitor/{event}/export
 *
 * Helpers prefixed rma_ to avoid Pest function collisions.
 */

function rma_staff(): User
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
function rma_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => rma_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'RMA Test Run',
        'event_date' => today()->toDateString(),
        'status' => 'registration_open',
        'registration_form_saved_at' => now(),
        'registration_slug' => 'rmaabc'.uniqid(),
        'registration_start' => now(),
    ], $overrides));
}

/**
 * @param array<string, mixed> $overrides
 */
function rma_participant(array $overrides = []): Participant
{
    return Participant::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'age' => 28,
        'email' => 'p'.uniqid().'@example.com',
    ], $overrides));
}

function rma_registration(Event $event, ?Participant $participant = null): Registration
{
    $participant ??= rma_participant();

    return Registration::create([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'registration_date' => now(),
        'registration_status' => 'confirmed',
        'source' => 'form',
        'registered_at' => now(),
    ]);
}

// ---------------------------------------------------------------------------
// editParticipant
// ---------------------------------------------------------------------------

test('edit participant updates the fields', function () {
    $staff = rma_staff();
    $event = rma_event();
    $registration = rma_registration($event);

    $this->actingAs($staff)
        ->put("/registrations/{$registration->id}/participant", [
            'first_name' => 'Maria Clara',
            'last_name' => 'Santos',
            'email' => 'maria.clara@example.com',
            'contact_number' => '09171234567',
            'address' => '123 Main St',
        ])
        ->assertRedirect();

    $participant = $registration->fresh()->participant;

    expect($participant->first_name)->toBe('Maria Clara');
    expect($participant->email)->toBe('maria.clara@example.com');
    expect($participant->contact_number)->toBe('09171234567');
    expect($participant->address)->toBe('123 Main St');
});

test('edit participant rejects an email that belongs to another participant', function () {
    $staff = rma_staff();
    $event = rma_event();

    $other = rma_participant(['email' => 'taken@example.com']);
    $registration = rma_registration($event);

    $this->actingAs($staff)
        ->put("/registrations/{$registration->id}/participant", [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'taken@example.com',
        ])
        ->assertSessionHasErrors('email');
});

test('edit participant allows keeping the current email', function () {
    $staff = rma_staff();
    $event = rma_event();

    $participant = rma_participant(['email' => 'mine@example.com']);
    $registration = rma_registration($event, $participant);

    $this->actingAs($staff)
        ->put("/registrations/{$registration->id}/participant", [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'mine@example.com',
        ])
        ->assertSessionHasNoErrors();
});

test('edit participant rejects a phone that belongs to another participant', function () {
    $staff = rma_staff();
    $event = rma_event();

    $other = rma_participant(['contact_number' => '09171234567']);
    $registration = rma_registration($event);

    $this->actingAs($staff)
        ->put("/registrations/{$registration->id}/participant", [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'contact_number' => '09171234567',
        ])
        ->assertSessionHasErrors('contact_number');
});

test('edit participant is forbidden on a completed event', function () {
    $staff = rma_staff();
    $event = rma_event([
        'status' => 'completed',
        'event_date' => today()->subDays(3)->toDateString(),
    ]);
    $registration = rma_registration($event);

    $this->actingAs($staff)
        ->put("/registrations/{$registration->id}/participant", [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria@example.com',
        ])
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// toggleFlag
// ---------------------------------------------------------------------------

test('flag registration sets flagged_at when flagged is true', function () {
    $staff = rma_staff();
    $event = rma_event();
    $registration = rma_registration($event);

    $this->actingAs($staff)
        ->post("/registrations/{$registration->id}/flag", ['flagged' => true])
        ->assertRedirect();

    expect($registration->fresh()->flagged_at)->not->toBeNull();
});

test('flag registration clears flagged_at when flagged is false', function () {
    $staff = rma_staff();
    $event = rma_event();
    $registration = rma_registration($event);

    $registration->update(['flagged_at' => now()]);

    $this->actingAs($staff)
        ->post("/registrations/{$registration->id}/flag", ['flagged' => false])
        ->assertRedirect();

    expect($registration->fresh()->flagged_at)->toBeNull();
});

test('flag registration is forbidden on a completed event', function () {
    $staff = rma_staff();
    $event = rma_event([
        'status' => 'completed',
        'event_date' => today()->subDays(3)->toDateString(),
    ]);
    $registration = rma_registration($event);

    $this->actingAs($staff)
        ->post("/registrations/{$registration->id}/flag", ['flagged' => true])
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// saveNote
// ---------------------------------------------------------------------------

test('save note stores the note text', function () {
    $staff = rma_staff();
    $event = rma_event();
    $registration = rma_registration($event);

    $this->actingAs($staff)
        ->post("/registrations/{$registration->id}/note", [
            'note' => 'Called to confirm shirt size.',
        ])
        ->assertRedirect();

    expect($registration->fresh()->notes)->toBe('Called to confirm shirt size.');
});

test('empty note clears the column to null', function () {
    $staff = rma_staff();
    $event = rma_event();
    $registration = rma_registration($event);

    $registration->update(['notes' => 'Previous note']);

    $this->actingAs($staff)
        ->post("/registrations/{$registration->id}/note", ['note' => ''])
        ->assertRedirect();

    expect($registration->fresh()->notes)->toBeNull();
});

test('note longer than 2000 characters is rejected', function () {
    $staff = rma_staff();
    $event = rma_event();
    $registration = rma_registration($event);

    $this->actingAs($staff)
        ->post("/registrations/{$registration->id}/note", [
            'note' => str_repeat('a', 2001),
        ])
        ->assertSessionHasErrors('note');
});

// ---------------------------------------------------------------------------
// exportCsv
// ---------------------------------------------------------------------------

test('csv export returns the expected headers', function () {
    $staff = rma_staff();
    $event = rma_event();
    rma_registration($event);

    $response = $this->actingAs($staff)
        ->get("/registrations/monitor/{$event->id}/export");

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();

    expect($content)->toContain('First name');
    expect($content)->toContain('Last name');
    expect($content)->toContain('Email');
    expect($content)->toContain('Contact number');
});

test('csv export includes one column per custom field', function () {
    $staff = rma_staff();
    $event = rma_event();

    RegistrationField::create([
        'event_id' => $event->id,
        'label' => 'Shirt Size',
        'field_type' => 'select',
        'options' => ['Small', 'Medium', 'Large'],
        'is_required' => false,
        'display_order' => 0,
    ]);

    $registration = rma_registration($event);

    $field = $event->registrationFields()->first();

    RegistrationFieldResponse::create([
        'registration_id' => $registration->id,
        'registration_field_id' => $field->id,
        'value' => 'Medium',
    ]);

    $response = $this->actingAs($staff)
        ->get("/registrations/monitor/{$event->id}/export");

    $content = $response->streamedContent();

    expect($content)->toContain('Shirt Size');
    expect($content)->toContain('Medium');
});

test('csv export joins checkbox arrays with comma', function () {
    $staff = rma_staff();
    $event = rma_event();

    RegistrationField::create([
        'event_id' => $event->id,
        'label' => 'Dietary',
        'field_type' => 'checkbox',
        'options' => ['Vegetarian', 'Vegan'],
        'is_required' => false,
        'display_order' => 0,
    ]);

    $registration = rma_registration($event);
    $field = $event->registrationFields()->first();

    RegistrationFieldResponse::create([
        'registration_id' => $registration->id,
        'registration_field_id' => $field->id,
        'value' => json_encode(['Vegetarian', 'Vegan']),
    ]);

    $response = $this->actingAs($staff)
        ->get("/registrations/monitor/{$event->id}/export");

    $content = $response->streamedContent();

    expect($content)->toContain('Vegetarian, Vegan');
});
