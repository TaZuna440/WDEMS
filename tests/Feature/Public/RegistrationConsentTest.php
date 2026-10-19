<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use App\Support\RegistrationLegalText;

require_once __DIR__.'/../../Support/PublicVerification.php';

function consentTestEvent(): Event
{
    return Event::create([
        'created_by' => User::factory()->create(['role' => 'staff', 'email_two_factor_enabled' => false])->id,
        'event_type' => 'community_run', 'event_name' => 'Consent Run',
        'event_date' => now()->addWeek(), 'status' => 'registration_open',
        'registration_slug' => bin2hex(random_bytes(4)), 'registration_start' => now(),
        'registration_form_saved_at' => now(),
        'registration_common_field_requirements' => ['email' => true, 'contact_number' => false],
    ]);
}

function consentTestPayload(array $overrides = []): array
{
    return array_replace([
        'first_name' => 'Maria', 'last_name' => 'Santos',
        'birthday' => now()->subYears(28)->toDateString(),
        'address' => 'Manila', 'email' => 'maria@example.com',
        'emergency_contact_name' => 'Jose Santos',
        'emergency_contact_relationship' => 'Father',
        'emergency_contact_number' => '+63 917 765 4321',
        'responses' => [],
    ], $overrides);
}

test('finalize writes the consent trail (closes P5)', function () {
    $event = consentTestEvent();

    $response = completePublicRegistration($this, $event, consentTestPayload());
    $response->assertSessionHasNoErrors();

    $registration = Registration::sole();
    expect($registration->consent_accepted_at)->not->toBeNull();
    expect($registration->privacy_notice_version)->toBe(RegistrationLegalText::PRIVACY_NOTICE_VERSION);
});

test('legal texts inject the event name and leave no raw placeholder', function () {
    $event = consentTestEvent();

    $paragraphs = RegistrationLegalText::disclaimerFor($event);
    expect($paragraphs)->toHaveCount(7);
    // Paragraph 1 carries the placeholder; paragraph 4 ("bring your
    // own water") legitimately names no event. So: no raw placeholder
    // may survive anywhere, and injection is proven where expected.
    foreach ($paragraphs as $paragraph) {
        expect($paragraph)->not->toContain('{event_name}');
    }
    expect($paragraphs[0])->toContain('Consent Run');

    expect(RegistrationLegalText::privacyConsentFor($event))
        ->toContain('Consent Run')
        ->not->toContain('{event_name}');
});

test('middle name and suffix persist through finalize', function () {
    $event = consentTestEvent();

    $response = completePublicRegistration($this, $event, consentTestPayload([
        'middle_name' => 'Dela',
        'suffix' => 'Jr.',
    ]));
    $response->assertSessionHasNoErrors();

    $participant = Participant::sole();
    expect($participant->middle_name)->toBe('Dela');
    expect($participant->suffix)->toBe('Jr.');
});

test('emergency contact persists through finalize', function () {
    $event = consentTestEvent();

    $response = completePublicRegistration($this, $event, consentTestPayload([
        'emergency_contact_name' => 'Jose Santos',
        'emergency_contact_relationship' => 'Father',
        'emergency_contact_number' => '+63 917 765 4321',
    ]));
    $response->assertSessionHasNoErrors();

    $registration = Registration::sole();
    expect($registration->emergency_contact_name)->toBe('Jose Santos');
    expect($registration->emergency_contact_relationship)->toBe('Father');
    expect($registration->emergency_contact_number)->toBe('+63 917 765 4321');
});

test('emergency contact matching own number does not block finalize (warn only)', function () {
    $event = consentTestEvent();

    $response = completePublicRegistration($this, $event, consentTestPayload([
        'contact_number' => '+63 917 123 4567',
        'emergency_contact_name' => 'Jose Santos',
        'emergency_contact_relationship' => 'Father',
        'emergency_contact_number' => '+63 917 123 4567',
    ]));
    $response->assertSessionHasNoErrors();

    expect(Registration::sole()->emergency_contact_number)->toBe('+63 917 123 4567');
});

test('emergency contact fields are required at intake', function () {
    $event = consentTestEvent();

    $payload = consentTestPayload();
    unset($payload['emergency_contact_name'], $payload['emergency_contact_relationship'], $payload['emergency_contact_number']);

    $response = $this->post('/r/'.$event->registration_slug, $payload);
    $response->assertSessionHasErrors([
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_number',
    ]);
});
