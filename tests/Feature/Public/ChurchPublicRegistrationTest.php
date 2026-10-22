<?php

use App\Enums\ChurchEventStatus;
use App\Mail\RegistrationVerificationOtpMail;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\RegistrationVerificationChallenge;
use App\Models\User;
use App\Services\RegistrationVerification\RegistrationVerificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/** @return array{Event, array<string, mixed>} */
function church_public_fixture(): array
{
    $staff = User::factory()->create(['role' => 'admin', 'email_two_factor_enabled' => false]);
    $event = Event::create([
        'created_by' => $staff->id,
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Fellowship',
        'church_status' => ChurchEventStatus::Upcoming,
        'church_registration_open' => true,
        'event_name' => 'Church Public Test',
        'event_date' => today()->addWeek()->toDateString(),
        'registration_slug' => 'church-public-test',
        'registration_form_version' => 2,
        'registration_form_saved_at' => now(),
        'registration_common_field_requirements' => [
            'email' => true,
            'contact_number' => false,
            'address' => true,
        ],
    ]);

    return [$event, [
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria-church@example.com',
        'contact_number' => '09171234567',
        'birthday' => '1995-05-12',
        'address' => 'Barangay Uno, Manila',
        'participant_type' => 'Guest',
        'emergency_contact_first_name' => 'Jose',
        'emergency_contact_last_name' => 'Santos',
        'emergency_contact_relationship' => 'Parent',
        'emergency_contact_number' => '09181234567',
        'consent' => true,
    ]];
}

it('requires server-verified recaptcha before email OTP and completes a church registration', function () {
    [$event, $payload] = church_public_fixture();
    Mail::fake();
    config(['services.recaptcha.site_key' => 'site-key', 'services.recaptcha.secret_key' => 'secret-key']);
    Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response([
        'success' => true,
        'hostname' => parse_url(config('app.url'), PHP_URL_HOST),
    ])]);

    $start = $this->post('/r/'.$event->registration_slug, $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect('/r/'.$event->registration_slug.'/verification');
    $cookieName = RegistrationVerificationService::cookieName($event);
    $this->withCookie($cookieName, $start->getCookie($cookieName)->getValue());
    $url = '/r/'.$event->registration_slug.'/verification';

    expect(RegistrationVerificationChallenge::where('event_id', $event->id)->sole()->email_issued_at)->toBeNull();
    $this->post($url.'/email/resend', ['recaptcha_token' => 'valid-test-token'])
        ->assertSessionHasNoErrors();
    Mail::assertSent(RegistrationVerificationOtpMail::class);

    $code = Mail::sent(RegistrationVerificationOtpMail::class)->last()->code;
    $this->post($url.'/email', ['code' => $code])->assertSessionHasNoErrors();
    $this->post($url.'/complete')->assertSessionHasNoErrors();

    $registration = Registration::sole();
    expect($registration->participant->pool)->toBe('church')
        ->and($registration->participant_type)->toBe('Guest')
        ->and($registration->emergency_contact_name)->toBe('Jose Santos')
        ->and($registration->attendance_code)->toMatch('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/')
        ->and($registration->attendance)->toBeNull();
});

it('accepts Graduate and Ninong/Ninang through public email verification', function () {
    Mail::fake();
    config(['services.recaptcha.site_key' => 'site-key', 'services.recaptcha.secret_key' => 'secret-key']);
    Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response([
        'success' => true,
        'hostname' => parse_url(config('app.url'), PHP_URL_HOST),
    ])]);

    foreach ([
        ['type' => 'Graduate', 'first_name' => 'Gina', 'email' => 'graduate-church@example.com'],
        ['type' => 'Ninong/Ninang', 'first_name' => 'Nina', 'email' => 'ninong-church@example.com'],
    ] as $index => $case) {
        [$event, $payload] = church_public_fixture();
        $event->update(['registration_slug' => 'church-public-type-'.$index]);
        $payload['participant_type'] = $case['type'];
        $payload['first_name'] = $case['first_name'];
        $payload['email'] = $case['email'];

        $start = $this->post('/r/'.$event->registration_slug, $payload)
            ->assertSessionHasNoErrors();
        $cookieName = RegistrationVerificationService::cookieName($event);
        $this->withCookie($cookieName, $start->getCookie($cookieName)->getValue());
        $url = '/r/'.$event->registration_slug.'/verification';
        $this->post($url.'/email/resend', ['recaptcha_token' => 'valid-test-token'])
            ->assertSessionHasNoErrors();
        $code = Mail::sent(RegistrationVerificationOtpMail::class)->last()->code;
        $this->post($url.'/email', ['code' => $code])->assertSessionHasNoErrors();
        $this->post($url.'/complete')->assertSessionHasNoErrors();

        expect(Registration::query()->where('event_id', $event->id)->sole()->participant_type)
            ->toBe($case['type']);
    }
});

it('rejects unsupported church participant types from the public form', function () {
    [$event, $payload] = church_public_fixture();
    $payload['participant_type'] = 'Volunteer';

    $this->post('/r/'.$event->registration_slug, $payload)->assertSessionHasErrors('participant_type');

    expect(RegistrationVerificationChallenge::where('event_id', $event->id)->exists())->toBeFalse();
});

it('does not send an email otp without a successful recaptcha check', function () {
    [$event, $payload] = church_public_fixture();
    Mail::fake();
    config(['services.recaptcha.site_key' => 'site-key', 'services.recaptcha.secret_key' => 'secret-key']);

    $start = $this->post('/r/'.$event->registration_slug, $payload)
        ->assertSessionHasNoErrors();
    $cookieName = RegistrationVerificationService::cookieName($event);
    $this->withCookie($cookieName, $start->getCookie($cookieName)->getValue());
    $this->post('/r/'.$event->registration_slug.'/verification/email/resend')
        ->assertSessionHasErrors('email');

    Mail::assertNothingSent();
    expect(Registration::count())->toBe(0);
});

it('does not allow a church registration to choose phone verification', function () {
    [$event, $payload] = church_public_fixture();
    $start = $this->post('/r/'.$event->registration_slug, $payload)
        ->assertSessionHasNoErrors();
    $cookieName = RegistrationVerificationService::cookieName($event);
    $this->withCookie($cookieName, $start->getCookie($cookieName)->getValue());

    $this->post('/r/'.$event->registration_slug.'/verification/channel', ['channel' => 'phone'])
        ->assertSessionHasErrors('channel');

    expect(RegistrationVerificationChallenge::where('event_id', $event->id)->sole()->chosen_channel)->toBe('email');
});

it('rejects an exact church duplicate before creating a verification challenge', function () {
    [$event, $payload] = church_public_fixture();
    $person = Participant::create([
        'pool' => 'church',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => $payload['email'],
        'contact_number' => $payload['contact_number'],
    ]);
    Registration::create([
        'event_id' => $event->id,
        'participant_id' => $person->id,
        'source' => 'manual',
        'registration_status' => 'confirmed',
        'registered_at' => now(),
    ]);

    $this->post('/r/'.$event->registration_slug, $payload)->assertSessionHasErrors('identity');

    expect(RegistrationVerificationChallenge::where('event_id', $event->id)->count())->toBe(0)
        ->and(Registration::where('participant_id', $person->id)->count())->toBe(1);
});

it('keeps unpublished church registration links unavailable', function () {
    [$event, $payload] = church_public_fixture();
    $event->update(['church_registration_open' => false]);

    $this->get('/r/'.$event->registration_slug)->assertNotFound();
    $this->post('/r/'.$event->registration_slug, $payload)->assertNotFound();
    expect(RegistrationVerificationChallenge::where('event_id', $event->id)->count())->toBe(0);
});

it('does not expose a church confirmation page for a pending submission', function () {
    [$event, $payload] = church_public_fixture();
    $this->post('/r/'.$event->registration_slug, $payload)->assertSessionHasNoErrors();

    $this->get('/r/'.$event->registration_slug.'/registered')->assertNotFound();
});

it('holds a verified likely duplicate outside active attendance counts', function () {
    [$event, $payload] = church_public_fixture();
    $person = Participant::create([
        'pool' => 'church',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => $payload['email'],
    ]);
    Registration::create([
        'event_id' => $event->id,
        'participant_id' => $person->id,
        'source' => 'manual',
        'registration_status' => 'confirmed',
        'submitted_email' => $payload['email'],
    ]);
    Mail::fake();
    config(['services.recaptcha.site_key' => 'site-key', 'services.recaptcha.secret_key' => 'secret-key']);
    Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response([
        'success' => true,
        'hostname' => parse_url(config('app.url'), PHP_URL_HOST),
    ])]);

    $payload['middle_name'] = 'Rubi';
    $start = $this->post('/r/'.$event->registration_slug, $payload)->assertSessionHasNoErrors();
    $cookieName = RegistrationVerificationService::cookieName($event);
    $this->withCookie($cookieName, $start->getCookie($cookieName)->getValue());
    $url = '/r/'.$event->registration_slug.'/verification';
    $this->post($url.'/email/resend', ['recaptcha_token' => 'valid-test-token'])->assertSessionHasNoErrors();
    $code = Mail::sent(RegistrationVerificationOtpMail::class)->last()->code;
    $this->post($url.'/email', ['code' => $code])->assertSessionHasNoErrors();
    $this->post($url.'/complete')->assertSessionHasNoErrors();

    $held = Registration::where('event_id', $event->id)->where('registration_status', 'held')->sole();
    expect($held->attendance)->toBeNull()
        ->and(Registration::active()->where('event_id', $event->id)->count())->toBe(1)
        ->and($held->attendance_code)->not->toBeNull();
});

it('creates a new participant when name and submitted phone match but verified email differs', function () {
    [$event, $payload] = church_public_fixture();
    $seeded = Participant::create([
        'pool' => 'church',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'seeded-a@example.com',
        'contact_number' => '09171234567',
    ]);

    $payload['email'] = 'verified-b@example.com';
    $payload['contact_number'] = '09171234567';

    Mail::fake();
    config(['services.recaptcha.site_key' => 'site-key', 'services.recaptcha.secret_key' => 'secret-key']);
    Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response([
        'success' => true,
        'hostname' => parse_url(config('app.url'), PHP_URL_HOST),
    ])]);

    $start = $this->post('/r/'.$event->registration_slug, $payload)->assertSessionHasNoErrors();
    $cookieName = RegistrationVerificationService::cookieName($event);
    $this->withCookie($cookieName, $start->getCookie($cookieName)->getValue());
    $url = '/r/'.$event->registration_slug.'/verification';
    $this->post($url.'/email/resend', ['recaptcha_token' => 'valid-test-token'])->assertSessionHasNoErrors();
    $code = Mail::sent(RegistrationVerificationOtpMail::class)->last()->code;
    $this->post($url.'/email', ['code' => $code])->assertSessionHasNoErrors();
    $this->post($url.'/complete')->assertSessionHasNoErrors();

    expect(Participant::where('pool', 'church')->where('first_name', 'Maria')->where('last_name', 'Santos')->count())->toBe(2);

    $registration = Registration::sole();
    $newParticipant = Participant::where('email', 'verified-b@example.com')->sole();

    expect($registration->participant_id)->toBe($newParticipant->id)
        ->and($newParticipant->id)->not->toBe($seeded->id)
        ->and($newParticipant->contact_number)->toBeNull()
        ->and($registration->submitted_contact_number)->toBe('+639171234567')
        ->and($seeded->fresh()->email)->toBe('seeded-a@example.com')
        ->and($seeded->fresh()->contact_number)->toBe('09171234567');
});

it('reuses an existing participant when composite name and verified email match', function () {
    [$event, $payload] = church_public_fixture();
    $seeded = Participant::create([
        'pool' => 'church',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria-return@example.com',
        'contact_number' => null,
    ]);

    $payload['email'] = 'maria-return@example.com';
    $payload['contact_number'] = '09179998877';

    Mail::fake();
    config(['services.recaptcha.site_key' => 'site-key', 'services.recaptcha.secret_key' => 'secret-key']);
    Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response([
        'success' => true,
        'hostname' => parse_url(config('app.url'), PHP_URL_HOST),
    ])]);

    $start = $this->post('/r/'.$event->registration_slug, $payload)->assertSessionHasNoErrors();
    $cookieName = RegistrationVerificationService::cookieName($event);
    $this->withCookie($cookieName, $start->getCookie($cookieName)->getValue());
    $url = '/r/'.$event->registration_slug.'/verification';
    $this->post($url.'/email/resend', ['recaptcha_token' => 'valid-test-token'])->assertSessionHasNoErrors();
    $code = Mail::sent(RegistrationVerificationOtpMail::class)->last()->code;
    $this->post($url.'/email', ['code' => $code])->assertSessionHasNoErrors();
    $this->post($url.'/complete')->assertSessionHasNoErrors();

    expect(Participant::where('pool', 'church')->where('first_name', 'Maria')->where('last_name', 'Santos')->count())->toBe(1);

    $registration = Registration::sole();
    expect($registration->participant_id)->toBe($seeded->id)
        ->and($registration->submitted_contact_number)->toBe('+639179998877')
        ->and($seeded->fresh()->email)->toBe('maria-return@example.com');
});

it('holds a submission when similar name and submitted phone match an existing registration', function () {
    [$event, $payload] = church_public_fixture();
    $person = Participant::create([
        'pool' => 'church',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'existing@example.com',
    ]);
    Registration::create([
        'event_id' => $event->id,
        'participant_id' => $person->id,
        'source' => 'manual',
        'registration_status' => 'confirmed',
        'submitted_contact_number' => $payload['contact_number'],
    ]);

    $payload['first_name'] = 'Maria';
    $payload['middle_name'] = 'Rubi';
    $payload['last_name'] = 'Santos';
    $payload['email'] = 'new-different@example.com';

    Mail::fake();
    config(['services.recaptcha.site_key' => 'site-key', 'services.recaptcha.secret_key' => 'secret-key']);
    Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response([
        'success' => true,
        'hostname' => parse_url(config('app.url'), PHP_URL_HOST),
    ])]);

    $start = $this->post('/r/'.$event->registration_slug, $payload)->assertSessionHasNoErrors();
    $cookieName = RegistrationVerificationService::cookieName($event);
    $this->withCookie($cookieName, $start->getCookie($cookieName)->getValue());
    $url = '/r/'.$event->registration_slug.'/verification';
    $this->post($url.'/email/resend', ['recaptcha_token' => 'valid-test-token'])->assertSessionHasNoErrors();
    $code = Mail::sent(RegistrationVerificationOtpMail::class)->last()->code;
    $this->post($url.'/email', ['code' => $code])->assertSessionHasNoErrors();
    $this->post($url.'/complete')->assertSessionHasNoErrors();

    $held = Registration::where('event_id', $event->id)->where('registration_status', 'held')->sole();
    expect($held->attendance)->toBeNull()
        ->and(Registration::active()->where('event_id', $event->id)->count())->toBe(1)
        ->and($held->held_reason)->toContain('Same phone')
        ->and($held->attendance_code)->not->toBeNull();
});
