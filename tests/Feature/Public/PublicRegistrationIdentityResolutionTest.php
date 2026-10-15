<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;

require_once __DIR__.'/../../Support/PublicVerification.php';

function resolution_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => User::factory()->create(['role' => 'staff', 'email_two_factor_enabled' => false])->id,
        'event_type' => 'community_run', 'event_name' => 'Resolution Test Run',
        'event_date' => now()->addWeek()->toDateString(), 'status' => 'registration_open',
        'registration_slug' => 'resolvet', 'registration_start' => now(),
        'registration_form_saved_at' => now(),
        'registration_common_field_requirements' => ['email' => true, 'contact_number' => true],
    ], $overrides));
}

function resolution_payload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@example.com',
        'contact_number' => '09171234567', 'age' => 28, 'address' => 'Manila', 'responses' => [],
    ], $overrides);
}

test('an existing verified participant is reused across events', function () {
    $eventA = resolution_event(['registration_slug' => 'resolvaa']);
    $eventB = resolution_event(['registration_slug' => 'resolvbb']);
    completePublicRegistration($this, $eventA, resolution_payload())->assertSessionHasNoErrors();
    completePublicRegistration($this, $eventB, resolution_payload())->assertSessionHasNoErrors();
    expect(Registration::count())->toBe(2);
    expect(Participant::where('email', 'maria@example.com')->count())->toBe(1);
});

test('an existing verified phone-only participant is reused across events', function () {
    $requirements = ['email' => false, 'contact_number' => true];
    $eventA = resolution_event(['registration_slug' => 'resolvaa', 'registration_common_field_requirements' => $requirements]);
    $eventB = resolution_event(['registration_slug' => 'resolvbb', 'registration_common_field_requirements' => $requirements]);
    $payload = resolution_payload(['email' => '']);
    completePublicRegistration($this, $eventA, $payload)->assertSessionHasNoErrors();
    completePublicRegistration($this, $eventB, $payload)->assertSessionHasNoErrors();
    expect(Registration::count())->toBe(2);
    expect(Participant::count())->toBe(1);
    expect(Participant::sole()->contact_number_normalized)->toBe('09171234567');
});

test('a duplicate verified email for the same event is rejected safely', function () {
    $event = resolution_event();
    completePublicRegistration($this, $event, resolution_payload())->assertSessionHasNoErrors();
    $this->post('/r/'.$event->registration_slug, resolution_payload())
        ->assertSessionHasErrors('identity');
    expect(Registration::count())->toBe(1);
});

test('ISSUE-009 duplicate verified phone without email produces no unique constraint error', function () {
    $event = resolution_event(['registration_common_field_requirements' => ['email' => false, 'contact_number' => true]]);
    $payload = resolution_payload(['email' => '']);
    completePublicRegistration($this, $event, $payload)->assertSessionHasNoErrors();
    $this->post('/r/'.$event->registration_slug, $payload)
        ->assertSessionHasErrors('identity');
    expect(Registration::count())->toBe(1);
    expect(Participant::whereNull('email')->count())->toBe(1);
});

test('an existing participant record is not updated by a later verified submission', function () {
    $eventA = resolution_event(['registration_slug' => 'resolvaa']);
    $eventB = resolution_event(['registration_slug' => 'resolvbb']);
    completePublicRegistration($this, $eventA, resolution_payload())->assertSessionHasNoErrors();
    completePublicRegistration($this, $eventB, resolution_payload(['first_name' => 'Maria D.', 'age' => 45]))->assertSessionHasNoErrors();
    $participant = Participant::where('email', 'maria@example.com')->sole();
    expect($participant->first_name)->toBe('Maria');
    expect($participant->age)->toBe(28);
});
