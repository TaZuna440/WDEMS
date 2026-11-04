<?php

use App\Mail\RegistrationConfirmationMail;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use App\Services\Participants\DuplicateReview;
use App\Services\RegistrationVerification\RegistrationVerificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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
        'contact_number' => '09171234567', 'age' => 28, 'address' => 'Manila', 'emergency_contact_name' => 'Jose Santos', 'emergency_contact_relationship' => 'Father', 'emergency_contact_number' => '+63 917 765 4321', 'responses' => [],
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

test('a duplicate verified email recovers the same registration', function () {
    $event = resolution_event();
    completePublicRegistration($this, $event, resolution_payload())->assertSessionHasNoErrors();
    completePublicRegistration($this, $event, resolution_payload())->assertSessionHasNoErrors();
    expect(Registration::count())->toBe(1);
});

test('ISSUE-009 duplicate verified phone without email produces no unique constraint error', function () {
    $event = resolution_event(['registration_common_field_requirements' => ['email' => false, 'contact_number' => true]]);
    $payload = resolution_payload(['email' => '']);
    completePublicRegistration($this, $event, $payload)->assertSessionHasNoErrors();
    completePublicRegistration($this, $event, $payload)->assertSessionHasNoErrors();
    expect(Registration::count())->toBe(1);
    expect(Participant::whereNull('email')->count())->toBe(1);
});

test('an existing participant record is not updated by a later verified submission', function () {
    $eventA = resolution_event(['registration_slug' => 'resolvaa']);
    $eventB = resolution_event(['registration_slug' => 'resolvbb']);
    completePublicRegistration($this, $eventA, resolution_payload())->assertSessionHasNoErrors();
    completePublicRegistration($this, $eventB, resolution_payload(['age' => 45]))->assertSessionHasNoErrors();
    $participant = Participant::where('email', 'maria@example.com')->sole();
    expect($participant->first_name)->toBe('Maria');
    expect($participant->age)->toBe(28);
});

test('a family member sharing an email with multiple participants is accepted separately without merging identities', function () {
    $event = resolution_event();
    $mother = Participant::create([
        'pool' => 'running',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'parent@example.com',
    ]);
    $daughter = Participant::create([
        'pool' => 'running',
        'first_name' => 'Jose',
        'last_name' => 'Santos',
        'email' => 'parent@example.com',
    ]);
    foreach ([$mother, $daughter] as $participant) {
        Registration::create([
            'event_id' => $event->id,
            'participant_id' => $participant->id,
            'registration_status' => 'confirmed',
            'source' => 'form',
        ]);
    }

    completePublicRegistration($this, $event, resolution_payload([
        'first_name' => 'Ana',
        'email' => 'parent@example.com',
    ]))->assertSessionHasNoErrors();

    $registration = Registration::latest('id')->firstOrFail();
    expect($registration->isActive())->toBeTrue()->and(Registration::held()->count())->toBe(0);
    $candidates = app(DuplicateReview::class)->findCandidates(
        $event,
        [
            'first_name' => 'Ana',
            'last_name' => 'Santos',
            'email' => 'parent@example.com',
        ],
        excludeRegistrationId: $registration->id,
        excludeParticipantId: $registration->participant_id,
    );

    expect($registration->participant_id)->not->toBe($mother->id)
        ->and($registration->participant_id)->not->toBe($daughter->id)
        ->and($candidates)->toHaveCount(2)
        ->and($mother->fresh()->first_name)->toBe('Maria')
        ->and($daughter->fresh()->first_name)->toBe('Jose');
});

test('verified recovery preserves attendance code and answers and is retry safe', function () {
    $event = resolution_event();
    completePublicRegistration($this, $event, resolution_payload())->assertSessionHasNoErrors();
    $original = Registration::sole();
    completePublicRegistration($this, $event, resolution_payload(['address' => 'Different address']))->assertSessionHasNoErrors();
    $this->post('/r/'.$event->registration_slug.'/verification/complete')->assertSessionHasNoErrors();
    expect(Registration::count())->toBe(1)->and(Participant::count())->toBe(1)
        ->and($original->fresh()->attendance_code)->toBe($original->attendance_code)
        ->and($original->fresh()->participant->address)->toBe('Manila');
    Mail::assertNotSent(RegistrationConfirmationMail::class);
    expect(DB::table('registration_identity_decisions')->where('action', 'registration_recovered')->count())->toBe(1);
});

test('conflicting birthdates create separate active identities even with exact names and contacts', function () {
    $event = resolution_event();
    completePublicRegistration($this, $event, resolution_payload(['birthday' => '2000-01-01']))->assertSessionHasNoErrors();
    completePublicRegistration($this, $event, resolution_payload(['birthday' => '2002-01-01']))->assertSessionHasNoErrors();
    expect(Registration::active()->count())->toBe(2)->and(Participant::count())->toBe(2)
        ->and(Participant::orderBy('id')->pluck('birthday')->map(fn ($date) => $date->toDateString())->all())->toBe(['2000-01-01', '2002-01-01']);
});

test('ambiguous exact candidates never select the first identity arbitrarily', function () {
    $event = resolution_event();
    foreach (range(1, 2) as $unused) {
        Participant::create(['pool' => 'running', 'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@example.com']);
    }
    completePublicRegistration($this, $event, resolution_payload())->assertSessionHasNoErrors();
    expect(Participant::count())->toBe(3)->and(Registration::sole()->isActive())->toBeTrue()
        ->and(Registration::sole()->participant_id)->toBe(Participant::latest('id')->firstOrFail()->id);
});

test('starting recovery reveals neither the existing registration nor its attendance code', function () {
    $event = resolution_event();
    completePublicRegistration($this, $event, resolution_payload())->assertSessionHasNoErrors();
    $start = $this->post('/r/'.$event->registration_slug, resolution_payload())->assertSessionHasNoErrors();
    $cookie = RegistrationVerificationService::cookieName($event);
    $token = $start->getCookie($cookie)->getValue();
    $state = app(RegistrationVerificationService::class)->publicState($event, $token);
    expect($state['status'])->toBe('pending')
        ->and(json_encode($state))->not->toContain(Registration::sole()->attendance_code);
    $this->withCookie($cookie, $token)->post('/r/'.$event->registration_slug.'/verification/complete')->assertSessionHasErrors('verification');
    expect(Registration::count())->toBe(1);
});

test('verified recovery does not reactivate an inactive registration', function (string $status) {
    $event = resolution_event();
    completePublicRegistration($this, $event, resolution_payload())->assertSessionHasNoErrors();
    Registration::sole()->update(['registration_status' => $status]);
    completePublicRegistration($this, $event, resolution_payload())->assertSessionHasErrors('verification');
    expect(Registration::count())->toBe(1)->and(Registration::sole()->registration_status->value)->toBe($status);
})->with(['cancelled', 'removed', 'duplicate']);
