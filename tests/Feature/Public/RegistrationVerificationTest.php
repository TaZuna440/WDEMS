<?php

use App\Mail\RegistrationVerificationOtpMail;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\RegistrationField;
use App\Models\RegistrationFieldResponse;
use App\Models\RegistrationVerificationChallenge as Challenge;
use App\Models\User;
use App\Services\Otp\OtpCode;
use App\Services\RegistrationVerification\RegistrationVerificationService as Verification;
use App\Services\TwoFactor\EmailTwoFactorService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

require_once __DIR__.'/../../Support/PublicVerification.php';

function verificationEvent(bool $email = true, bool $phone = false): Event
{
    return Event::create([
        'created_by' => User::factory()->create(['role' => 'staff', 'email_two_factor_enabled' => false])->id,
        'event_type' => 'community_run', 'event_name' => 'Verification Run',
        'event_date' => now()->addWeek(), 'status' => 'registration_open',
        'registration_slug' => bin2hex(random_bytes(4)), 'registration_start' => now(),
        'registration_form_saved_at' => now(),
        'registration_common_field_requirements' => ['email' => $email, 'contact_number' => $phone],
    ]);
}

function verificationPayload(array $overrides = []): array
{
    return array_replace([
        'first_name' => 'Maria', 'last_name' => 'Santos', 'age' => 28,
        'address' => 'Manila', 'email' => 'maria@example.com',
        'contact_number' => '+63 917 123 4567', 'emergency_contact_name' => 'Jose Santos', 'emergency_contact_relationship' => 'Father', 'emergency_contact_number' => '+63 917 765 4321', 'responses' => [],
    ], $overrides);
}

function startVerification(TestCase $test, Event $event, array $payload = []): array
{
    $response = $test->post('/r/'.$event->registration_slug, verificationPayload($payload));
    $response->assertRedirect('/r/'.$event->registration_slug.'/verification');
    $name = Verification::cookieName($event);
    $cookie = $response->getCookie($name);
    expect($cookie->isHttpOnly())->toBeTrue();
    expect($cookie->getSameSite())->toBe('lax');
    expect($cookie->getPath())->toBe('/r/'.$event->registration_slug);
    $test->withCookie($name, $cookie->getValue());

    return [Challenge::latest('id')->firstOrFail(), $cookie->getValue(), '/r/'.$event->registration_slug.'/verification'];
}

beforeEach(function () {
    Mail::fake();
});

test('finalization distinguishes family members by middle name and suffix', function () {
    $event = verificationEvent();
    Participant::create(['first_name' => 'Maria', 'last_name' => 'Santos', 'middle_name' => 'Reyes', 'suffix' => 'Sr.', 'email' => 'maria@example.com']);

    completePublicRegistration($this, $event, verificationPayload(['middle_name' => 'Reyes', 'suffix' => 'Jr.']))
        ->assertSessionHasNoErrors();

    expect(Participant::count())->toBe(2)
        ->and(Registration::sole()->participant->suffix)->toBe('Jr.');
    $this->post('/r/'.$event->registration_slug, verificationPayload(['middle_name' => ' REYES ', 'suffix' => 'JR.']))
        ->assertSessionHasErrors('identity');
});

test('version two verifies email only even when the contact number is required', function () {
    $event = verificationEvent(true, true);
    $event->forceFill(['registration_form_version' => 2])->save();
    [$challenge, , $url] = startVerification($this, $event, [
        'birthday' => '2000-01-15', 'emergency_contact_first_name' => 'Jose',
        'emergency_contact_last_name' => 'Santos', 'emergency_contact_relationship' => 'Parent',
    ]);

    Mail::assertNothingSent();
    config(['services.recaptcha.site_key' => 'test-site', 'services.recaptcha.secret_key' => 'test-secret']);
    \Illuminate\Support\Facades\Http::fake(['www.google.com/recaptcha/api/siteverify' => \Illuminate\Support\Facades\Http::response(['success' => true, 'hostname' => parse_url(config('app.url'), PHP_URL_HOST)])]);
    $this->post($url.'/email/resend', ['recaptcha_token' => 'valid-test-token'])->assertSessionHasNoErrors();

    expect($challenge->email_required)->toBeTrue()
        ->and($challenge->phone_required)->toBeFalse()
        ->and($challenge->chosen_channel)->toBe('email');

    $this->post($url.'/email', ['code' => Mail::sent(RegistrationVerificationOtpMail::class)->sole()->code])
        ->assertSessionHasNoErrors();
    $this->post($url.'/complete')->assertSessionHasNoErrors();
    expect(Registration::count())->toBe(1);
});

test('email intake stores encrypted pending data and no registration records', function () {
    $event = verificationEvent();
    $field = RegistrationField::create(['event_id' => $event->id, 'label' => 'Size', 'field_type' => 'text', 'is_required' => true, 'display_order' => 0]);
    [$challenge, $token, $url] = startVerification($this, $event, ['responses' => [$field->id => 'Medium']]);
    expect(Participant::count())->toBe(0);
    expect(Registration::count())->toBe(0);
    expect(RegistrationFieldResponse::count())->toBe(0);
    $raw = DB::table('registration_verification_challenges')->first();
    expect($raw->token_hash)->toBe(hash('sha256', $token))->not->toBe($token);
    expect($raw->payload)->not->toContain('maria@example.com', 'Medium', '+63');
    $mail = Mail::sent(RegistrationVerificationOtpMail::class)->sole();
    expect($mail->code)->toMatch('/\A[0-9]{6}\z/');
    expect(OtpCode::matches($mail->code, $raw->email_code_hash))->toBeTrue();
    expect($mail->render())->toContain($mail->code, 'Verification Run');
    $this->get($url)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertInertia(fn (Assert $page) => $page->component('registrations/verify')->where('verification.ready', false)->missing('verification.token')->missing('verification.payload'));
    $this->post($url.'/complete')->assertSessionHasErrors('verification');
    $this->post($url.'/email', ['code' => $mail->code])->assertSessionHasNoErrors();
    // Firebase Phone Auth cannot be exercised from the test suite without
    // stubbing the Admin SDK. Write the verified marker directly.
    $challenge->forceFill(['phone_verified_at' => now()])->save();
    expect(Registration::count())->toBe(0);
    $this->post($url.'/complete')->assertSessionHasNoErrors()->assertRedirect($url);
    expect(Registration::count())->toBe(1);
    expect(RegistrationFieldResponse::sole()->value)->toBe('Medium');
    expect($challenge->refresh()->payload)->toBeNull();
    expect($challenge->email_code_hash)->toBeNull();
    $this->get($url)->assertInertia(fn (Assert $page) => $page->where('verification.status', 'finalized'));
});

test('Registration Code is visible after finalization then permanently hidden after acknowledgement', function () {
    $event = verificationEvent();
    [$challenge, $token, $url] = startVerification($this, $event);
    $emailCode = Mail::sent(RegistrationVerificationOtpMail::class)->sole()->code;

    $this->post($url.'/email', ['code' => $emailCode])->assertSessionHasNoErrors();
    $this->post($url.'/complete')->assertSessionHasNoErrors();

    $registration = Registration::sole();
    $storedCode = $registration->attendance_code;
    $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $page) => $page
            ->where('verification.status', 'finalized')
            ->where('verification.attendance_code', $storedCode));

    // The code-free destination must remain reachable when the public form closes.
    $event->update(['status' => 'registration_closed']);

    $cookieName = Verification::cookieName($event);
    $acknowledgement = $this->post($url.'/acknowledge-code')
        ->assertRedirect('/r/'.$event->registration_slug.'/registered');
    $expiredCookie = $acknowledgement->getCookie($cookieName);
    expect($expiredCookie)->not->toBeNull()
        ->and($expiredCookie->getPath())->toBe('/r/'.$event->registration_slug)
        ->and($expiredCookie->getExpiresTime())->toBeLessThan(now()->getTimestamp());

    $challenge->refresh();
    $acknowledgedAt = $challenge->code_acknowledged_at;
    expect($acknowledgedAt)->not->toBeNull()
        ->and($registration->fresh()->attendance_code)->toBe($storedCode)
        ->and(app(Verification::class)->publicState($event, $token))
            ->toBe(['status' => 'acknowledged']);

    $this->withCookie($cookieName, $token)->get($url)
        ->assertRedirect('/r/'.$event->registration_slug.'/registered');
    $this->get('/r/'.$event->registration_slug.'/registered')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('registrations/confirmed')
            ->missing('attendance_code')
            ->missing('verification.attendance_code'));

    // Restoring the old cookie and repeating the POST is harmless.
    $this->withCookie($cookieName, $token)->post($url.'/acknowledge-code')
        ->assertRedirect('/r/'.$event->registration_slug.'/registered');
    expect($challenge->fresh()->code_acknowledged_at->equalTo($acknowledgedAt))->toBeTrue();
    $this->withCookie($cookieName, $token)->get($url)
        ->assertRedirect('/r/'.$event->registration_slug.'/registered');
});

test('registered confirmation route resolves to the anonymous public layout', function () {
    $event = verificationEvent();

    $this->get('/r/'.$event->registration_slug.'/registered')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('registrations/confirmed')
            ->where('event_name', 'Verification Run')
            ->missing('attendance_code')
            ->missing('verification.attendance_code'));

    $middleware = app('router')->getRoutes()
        ->getByName('public-registration.confirmed')
        ->gatherMiddleware();
    expect($middleware)->not->toContain('auth', 'verified', 'device.trusted', 'verified.or.admin', 'admin');

    // The application has no JS test runner; this guards the Inertia page-name
    // mapping directly so the confirmation page cannot fall through to AppLayout.
    $resolver = file_get_contents(resource_path('js/app.tsx'));
    expect($resolver)
        ->toContain("case name === 'registrations/confirmed':\n                return PublicLayout;")
        ->not->toContain("case name === 'registrations/confirmed':\n                return AppLayout;");
});

test('acknowledgement rejects pending missing invalid and expired challenges', function (string $state) {
    $event = verificationEvent();

    if ($state === 'missing') {
        $this->post('/r/'.$event->registration_slug.'/verification/acknowledge-code')
            ->assertSessionHasErrors('verification');

        return;
    }

    [$challenge, $token, $url] = startVerification($this, $event);

    if ($state === 'expired') {
        $this->post($url.'/email', ['code' => Mail::sent(RegistrationVerificationOtpMail::class)->sole()->code]);
        $this->post($url.'/complete')->assertSessionHasNoErrors();
        $this->travel(24)->hours();
        $this->post($url.'/acknowledge-code')->assertSessionHasErrors('verification');
    } elseif ($state === 'pending') {
        $this->post($url.'/acknowledge-code')->assertSessionHasErrors('verification');
    } elseif ($state === 'invalid') {
        $this->withCookie(Verification::cookieName($event), 'invalid-token')
            ->post($url.'/acknowledge-code')->assertSessionHasErrors('verification');
    }

    expect($challenge->fresh()->code_acknowledged_at)->toBeNull();
})->with(['pending', 'missing', 'invalid', 'expired']);

test('phone only and both-channel registrations require every gate and finalize once', function (bool $email) {
    $event = verificationEvent($email, true);
    [$challenge, , $url] = startVerification($this, $event);
    $challenge->forceFill(['phone_verified_at' => now()])->save();
    if ($email) {
        $this->post($url.'/complete')->assertSessionHasErrors('verification');
        expect(Registration::count())->toBe(0);
        $this->post($url.'/email', ['code' => Mail::sent(RegistrationVerificationOtpMail::class)->sole()->code])->assertSessionHasNoErrors();
    } else {
        Mail::assertNothingSent();
    }
    $this->post($url.'/complete')->assertSessionHasNoErrors();
    $this->post($url.'/complete')->assertSessionHasNoErrors();
    expect(Registration::count())->toBe(1);
    expect(Participant::count())->toBe(1);
})->with([false, true]);

test('email verification alone cannot bypass required phone', function () {
    $event = verificationEvent(true, true);
    [, , $url] = startVerification($this, $event);
    $this->post($url.'/email', ['code' => Mail::sent(RegistrationVerificationOtpMail::class)->sole()->code])->assertSessionHasNoErrors();
    $this->post($url.'/complete')->assertSessionHasErrors('verification');
    expect(Participant::count())->toBe(0);
});

test('incorrect codes lock each channel after five attempts and regeneration never resets attempts', function (string $channel) {
    $event = verificationEvent($channel === 'email', $channel === 'phone');
    [$challenge, $token, $url] = startVerification($this, $event);
    $service = app(Verification::class);
    $actor = User::findOrFail($event->created_by);
    $code = $channel === 'email' ? Mail::sent(RegistrationVerificationOtpMail::class)->sole()->code : $service->generatePhone($challenge, $actor);
    $wrong = $code === '000000' ? '111111' : '000000';
    $this->post($url.'/'.$channel, ['code' => $wrong])->assertSessionHasErrors($channel);
    $this->travel(100)->seconds();
    if ($channel === 'email') {
        expect($service->sendEmail($event, $token))->toBeNull();
        $replacement = Mail::sent(RegistrationVerificationOtpMail::class)->last()->code;
    } else {
        $replacement = $service->generatePhone($challenge, $actor);
    }
    expect($replacement)->not->toBe($code);
    $this->post($url.'/'.$channel, ['code' => $code])->assertSessionHasErrors($channel);
    for ($i = 0; $i < 3; $i++) {
        $wrong = $replacement === '000000' ? '111111' : '000000';
        $this->post($url.'/'.$channel, ['code' => $wrong])->assertSessionHasErrors($channel);
    }
    $this->post($url.'/'.$channel, ['code' => $replacement])->assertSessionHasErrors($channel);
    expect($challenge->refresh()->getAttribute($channel.'_attempts'))->toBe(5);
    expect($challenge->getAttribute($channel.'_verified_at'))->toBeNull();
    $this->travel(60)->seconds();
    if ($channel === 'email') {
        expect($service->sendEmail($event, $token))->toContain('Too many');
    } else {
        expect(fn () => $service->generatePhone($challenge, $actor))->toThrow(ValidationException::class);
    }
    expect(Registration::count())->toBe(0);
})->with(['email']);

test('cooldown is enforced at 99 seconds and allows issuance at 100 seconds', function (string $channel) {
    $event = verificationEvent($channel === 'email', $channel === 'phone');
    [$challenge, , $url] = startVerification($this, $event);
    if ($channel === 'phone') {
        $this->actingAs(User::findOrFail($event->created_by));
        $url = '/registrations/verification/'.$challenge->id.'/phone-code';
        $this->postJson($url)->assertOk();
    } else {
        $url .= '/email/resend';
    }
    $hash = $challenge->refresh()->getAttribute($channel.'_code_hash');
    $this->travel(99)->seconds();
    if ($channel === 'phone') {
        $this->postJson($url)->assertUnprocessable();
    } else {
        $this->post($url)->assertSessionHasErrors('email');
    }
    expect($challenge->refresh()->getAttribute($channel.'_code_hash'))->toBe($hash);
    $this->travel(1)->seconds();
    if ($channel === 'phone') {
        $this->postJson($url)->assertOk();
    } else {
        $this->post($url)->assertSessionHasNoErrors();
    }
    expect($challenge->refresh()->getAttribute($channel.'_code_hash'))->not->toBe($hash);
})->with(['email']);

test('code expires at ten minutes and a consumed code cannot be reused', function (string $channel) {
    $event = verificationEvent($channel === 'email', $channel === 'phone');
    [$challenge, $token, $url] = startVerification($this, $event);
    $service = app(Verification::class);
    $actor = User::findOrFail($event->created_by);
    $code = $channel === 'email' ? Mail::sent(RegistrationVerificationOtpMail::class)->sole()->code : $service->generatePhone($challenge, $actor);
    $this->travel(10)->minutes();
    $this->post($url.'/'.$channel, ['code' => $code])->assertSessionHasErrors($channel);
    if ($channel === 'email') {
        $service->sendEmail($event, $token);
        $code = Mail::sent(RegistrationVerificationOtpMail::class)->last()->code;
    } else {
        $code = $service->generatePhone($challenge, $actor);
    }
    $this->post($url.'/'.$channel, ['code' => $code])->assertSessionHasNoErrors();
    $this->post($url.'/'.$channel, ['code' => $code])->assertSessionHasErrors($channel);
    expect($challenge->refresh()->getAttribute($channel.'_code_hash'))->toBeNull();
})->with(['email']);

test('expired challenges reject verification resend finalization and staff generation', function () {
    $event = verificationEvent(true, true);
    [$challenge, , $url] = startVerification($this, $event);
    $this->travel(24)->hours();
    $this->get($url)->assertInertia(fn (Assert $page) => $page->where('verification.status', 'expired'));
    foreach (['email', 'email/resend', 'complete'] as $action) {
        $this->post($url.'/'.$action, ['code' => Mail::sent(RegistrationVerificationOtpMail::class)->sole()->code])->assertSessionHasErrors('verification');
    }
    expect(Participant::count())->toBe(0);
});

test('missing forged and event-mismatched tokens cannot access a challenge', function () {
    $event = verificationEvent();
    [$challenge, $token, $url] = startVerification($this, $event);
    $other = verificationEvent();
    $this->withCookie(Verification::cookieName($other), $token)->get('/r/'.$other->registration_slug.'/verification')
        ->assertInertia(fn (Assert $page) => $page->where('verification.status', 'missing'));
    foreach (['', 'bad-token', str_repeat('a', 64)] as $invalid) {
        $this->withCookie(Verification::cookieName($event), $invalid)->post($url.'/complete')->assertSessionHasErrors('verification');
    }
    expect($challenge->refresh()->finalized_at)->toBeNull();
});

test('optional contacts are snapshots and cannot resolve or update participant identities', function (bool $emailRequired) {
    $event = verificationEvent($emailRequired, ! $emailRequired);
    $existing = Participant::create(['first_name' => 'Other', 'last_name' => 'Person', 'age' => 40,
        'email' => $emailRequired ? 'other@example.com' : 'maria@example.com',
        'contact_number' => $emailRequired ? '09171234567' : '09179999999']);
    completePublicRegistration($this, $event, verificationPayload())->assertSessionHasNoErrors();
    $registration = Registration::sole();
    expect($registration->participant_id)->not->toBe($existing->id);
    expect($registration->submitted_email)->toBe('maria@example.com');
    expect($registration->submitted_contact_number)->toBe('+63 917 123 4567');
    $participant = $registration->participant;
    expect($participant->email)->toBe($emailRequired ? 'maria@example.com' : null);
    expect($participant->contact_number_normalized)->toBe($emailRequired ? null : '09171234567');
    expect($existing->refresh()->first_name)->toBe('Other');
    expect(Challenge::sole()->getAttribute($emailRequired ? 'phone_issued_at' : 'email_issued_at'))->toBeNull();
})->with([false]);

test('optional phone does not trigger Firebase verification or resolve participant identity', function () {
    $event = verificationEvent(true, false);
    $existing = Participant::create([
        'first_name' => 'Existing', 'last_name' => 'Runner', 'age' => 40,
        'email' => 'existing@example.com', 'contact_number' => '09171234567',
    ]);
    [$challenge, , $url] = startVerification($this, $event);

    expect($challenge->phone_required)->toBeFalse();
    $this->post($url.'/phone/firebase', ['id_token' => 'not-a-firebase-token'])
        ->assertSessionHasErrors('phone');
    expect($challenge->fresh()->phone_verified_at)->toBeNull();

    $this->post($url.'/email', ['code' => Mail::sent(RegistrationVerificationOtpMail::class)->sole()->code])
        ->assertSessionHasNoErrors();
    $this->post($url.'/complete')->assertSessionHasNoErrors();

    $registration = Registration::sole();
    expect($registration->participant_id)->not->toBe($existing->id)
        ->and($registration->submitted_contact_number)->toBe('+63 917 123 4567')
        ->and($registration->participant->contact_number_normalized)->toBeNull();
});

test('two verified identifiers reuse the same participant without rewriting details', function () {
    $event = verificationEvent(true, true);
    $participant = Participant::create(['first_name' => 'Maria', 'last_name' => 'Santos', 'age' => 40, 'email' => 'maria@example.com', 'contact_number' => '09171234567']);
    completePublicRegistration($this, $event, verificationPayload())->assertSessionHasNoErrors();
    expect(Registration::sole()->participant_id)->toBe($participant->id);
    expect(Participant::count())->toBe(1);
    expect($participant->refresh()->age)->toBe(40);
});

test('verified identifiers belonging to different participants fail without merging', function () {
    $event = verificationEvent(true, true);
    $a = Participant::create(['first_name' => 'Maria', 'last_name' => 'Santos', 'age' => 40, 'email' => 'maria@example.com']);
    $b = Participant::create(['first_name' => 'Maria', 'last_name' => 'Santos', 'age' => 30, 'contact_number' => '09171234567']);
    $before = Participant::orderBy('id')->get()->toArray();
    completePublicRegistration($this, $event, verificationPayload())->assertSessionHasErrors('verification');
    expect(Participant::orderBy('id')->get()->toArray())->toBe($before);
    expect(Registration::count())->toBe(0);
    expect(Challenge::sole()->finalized_at)->toBeNull();
});

test('delivery failure persists a safe retry state without completing anything', function () {
    Mail::shouldReceive('mailer')->with('resend')->once()->andThrow(new RuntimeException('provider unavailable'));
    $event = verificationEvent();
    [$challenge, , $url] = startVerification($this, $event);
    expect($challenge->email_delivery_failed)->toBeTrue();
    expect($challenge->email_code_hash)->toBeNull();
    expect($challenge->email_verified_at)->toBeNull();
    expect(Participant::count())->toBe(0);
    $this->post($url.'/complete')->assertSessionHasErrors('verification');
    $this->travel(100)->seconds();
    Mail::swap(new MailManager(app()));
    Mail::fake();
    $this->post($url.'/email/resend')->assertSessionHasNoErrors();
    expect($challenge->refresh()->email_delivery_failed)->toBeFalse();
});

test('repeated pending submissions preserve challenge cooldown and attempts', function () {
    $event = verificationEvent();
    [$challenge, $token] = startVerification($this, $event);
    $challenge->forceFill(['email_attempts' => 3])->save();
    [$again, $same] = startVerification($this, $event);
    expect($again->id)->toBe($challenge->id);
    expect($same)->toBe($token);
    expect($again->email_attempts)->toBe(3);
    Mail::assertSentCount(1);
});

test('changed payload supersedes the old challenge and invalidates its token', function () {
    $event = verificationEvent();
    [$first, $token] = startVerification($this, $event);
    startVerification($this, $event, ['email' => 'new@example.com']);
    expect($first->refresh()->payload)->toBeNull();
    expect(app(Verification::class)->publicState($event, $token)['status'])->toBe('expired');
});

test('current event configuration and field validation are checked again before finalizing', function (string $change) {
    $event = verificationEvent();
    [, , $url] = startVerification($this, $event);
    $this->post($url.'/email', ['code' => Mail::sent(RegistrationVerificationOtpMail::class)->sole()->code])->assertSessionHasNoErrors();
    match ($change) {
        'cancelled' => $event->update(['status' => 'cancelled']),
        'requirements' => $event->update(['registration_common_field_requirements' => ['email' => true, 'contact_number' => true]]),
        'field' => RegistrationField::create(['event_id' => $event->id, 'label' => 'Required answer', 'field_type' => 'text', 'is_required' => true, 'display_order' => 0]),
    };
    $this->post($url.'/complete')->assertSessionHasErrors('verification');
    expect(Participant::count())->toBe(0);
})->with(['cancelled', 'requirements', 'field']);

test('failure during answer creation rolls back participant registration and finalization', function () {
    $event = verificationEvent();
    $field = RegistrationField::create(['event_id' => $event->id, 'label' => 'Answer', 'field_type' => 'text', 'is_required' => true, 'display_order' => 0]);
    [$challenge, , $url] = startVerification($this, $event, ['responses' => [$field->id => 'Yes']]);
    $this->post($url.'/email', ['code' => Mail::sent(RegistrationVerificationOtpMail::class)->sole()->code]);
    // Firebase Phone Auth cannot be exercised from the test suite without
    // stubbing the Admin SDK. Write the verified marker directly.
    $challenge->forceFill(['phone_verified_at' => now()])->save();
    RegistrationFieldResponse::creating(fn () => throw new RuntimeException('simulated write failure'));
    try {
        $this->post($url.'/complete')->assertSessionHasErrors('verification');
        expect(Participant::count())->toBe(0);
        expect(Registration::count())->toBe(0);
        expect($challenge->refresh()->finalized_at)->toBeNull();
    } finally {
        RegistrationFieldResponse::flushEventListeners();
    }
    $this->post($url.'/complete')->assertSessionHasNoErrors();
    expect(RegistrationFieldResponse::count())->toBe(1);
});

test('verification endpoints throttle and never flash OTP input', function () {
    $event = verificationEvent();
    [, , $url] = startVerification($this, $event);
    $this->post($url.'/email', ['code' => 'invalid'])->assertSessionHasErrors('email')->assertSessionMissing('_old_input.code');
    for ($i = 0; $i < 9; $i++) {
        $this->post($url.'/complete');
    }
    $this->post($url.'/complete')->assertTooManyRequests();
});

test('public and staff mutation routes enforce CSRF outside the test bypass', function () {
    $event = verificationEvent(false, true);
    [$challenge, , $url] = startVerification($this, $event);
    $this->app->bind(PreventRequestForgery::class, function ($app) {
        return new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        };
    });
    $this->post($url.'/email/resend')->assertStatus(419);
    $this->post($url.'/complete')->assertStatus(419);
    expect($challenge->refresh()->phone_code_hash)->toBeNull();
});

test('pruning removes abandoned expired payloads and preserves active challenges', function () {
    $event = verificationEvent(false, true);
    [$old] = startVerification($this, $event);
    $this->travel(24)->hours();
    [$active] = startVerification($this, $event);
    $this->artisan('model:prune', ['--model' => [Challenge::class]])->assertSuccessful();
    expect(Challenge::find($old->id))->toBeNull();
    expect(Challenge::find($active->id))->not->toBeNull();
});

test('version two refuses missing invalid and wrong-host captcha without sending email', function () {
    $event = verificationEvent();
    $event->forceFill(['registration_form_version' => 2])->save();
    [, , $url] = startVerification($this, $event, [
        'birthday' => '2000-01-15', 'emergency_contact_first_name' => 'Jose',
        'emergency_contact_last_name' => 'Santos', 'emergency_contact_relationship' => 'Parent',
    ]);
    config(['services.recaptcha.site_key' => 'test-site', 'services.recaptcha.secret_key' => 'test-secret']);
    \Illuminate\Support\Facades\Http::fakeSequence()
        ->push(['success' => false])
        ->push(['success' => true, 'hostname' => 'another-host.example']);
    $this->post($url.'/email/resend')->assertSessionHasErrors('email');
    \Illuminate\Support\Facades\Http::assertNothingSent();
    $this->post($url.'/email/resend', ['recaptcha_token' => 'invalid'])->assertSessionHasErrors('email');
    $this->post($url.'/email/resend', ['recaptcha_token' => 'wrong-host'])->assertSessionHasErrors('email');
    Mail::assertNothingSent();
});

test('version two email lockout survives resends and a replacement challenge', function () {
    $event = verificationEvent();
    $event->forceFill(['registration_form_version' => 2])->save();
    $payload = ['birthday' => '2000-01-15', 'emergency_contact_first_name' => 'Jose',
        'emergency_contact_last_name' => 'Santos', 'emergency_contact_relationship' => 'Parent'];
    [$challenge, $token] = startVerification($this, $event, $payload);
    config(['services.recaptcha.site_key' => 'test-site', 'services.recaptcha.secret_key' => 'test-secret']);
    \Illuminate\Support\Facades\Http::fake(['www.google.com/recaptcha/api/siteverify' =>
        \Illuminate\Support\Facades\Http::response(['success' => true, 'hostname' => parse_url(config('app.url'), PHP_URL_HOST)])]);
    $service = app(Verification::class);
    expect($service->sendEmail($event, $token, null, 'captcha'))->toBeNull();
    for ($attempt = 0; $attempt < 3; $attempt++) {
        expect($service->verify($event, $token, 'email', 'wrong'))->toContain('incorrect');
    }
    $this->travel(101)->seconds();
    expect($service->sendEmail($event, $token, null, 'captcha'))->toBeNull();
    expect($service->verify($event, $token, 'email', 'wrong'))->toContain('1 attempts left');
    [, $replacement] = startVerification($this, $event, [...$payload, 'address' => 'Updated Manila address']);
    expect($service->sendEmail($event, $replacement, null, 'captcha'))->toBeNull();
    expect($service->verify($event, $replacement, 'email', 'wrong'))->toContain('2 hours');
    expect($service->sendEmail($event, $replacement, null, 'captcha'))->toContain('2 hours');
    expect($service->verify($event, $replacement, 'email', Mail::sent(RegistrationVerificationOtpMail::class)->last()->code))->toContain('2 hours');
    expect(Registration::count())->toBe(0);
    $this->travel(2)->hours();
    expect($service->sendEmail($event, $replacement, null, 'captcha'))->toBeNull();
});

test('closing registration honors existing challenges but rejects new submissions', function () {
    $event = verificationEvent();
    [$challenge, , $url] = startVerification($this, $event);
    $code = Mail::sent(RegistrationVerificationOtpMail::class)->sole()->code;
    $event->update(['status' => 'registration_closed', 'registration_end' => now()]);
    $this->post('/r/'.$event->registration_slug, verificationPayload(['email' => 'new@example.com']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('registrations/closed'));
    expect(Challenge::count())->toBe(1);
    $this->post($url.'/email', ['code' => $code])->assertSessionHasNoErrors();
    $this->post($url.'/complete')->assertSessionHasNoErrors();
    expect($challenge->fresh()->finalized_at)->not->toBeNull();
});

test('closure never extends a pending challenge beyond its original lifetime', function () {
    $event = verificationEvent();
    [$challenge, $token] = startVerification($this, $event);
    $event->update(['status' => 'registration_closed', 'registration_end' => now()]);
    $this->travel(24)->hours();
    expect(app(Verification::class)->publicState($event, $token)['status'])->toBe('expired');
});

test('similarity warning is private until verification and allows explicit continuation', function () {
    $event = verificationEvent();
    $person = Participant::create(['first_name' => 'Marya', 'last_name' => 'Santos', 'email' => 'maria@example.com']);
    Registration::create(['event_id' => $event->id, 'participant_id' => $person->id, 'registration_status' => 'confirmed', 'source' => 'form']);
    [$challenge, $token, $url] = startVerification($this, $event);
    expect(app(Verification::class)->publicState($event, $token)['similarity_warning'])->toBeFalse();
    $challenge->forceFill(['email_verified_at' => now()])->save();
    expect(app(Verification::class)->publicState($event, $token)['similarity_warning'])->toBeTrue();
    $this->post($url.'/complete')->assertSessionHasErrors('verification');
    expect(Registration::count())->toBe(1);
    $this->post($url.'/complete', ['similarity_acknowledged' => true])->assertSessionHasNoErrors();
    expect(Registration::count())->toBe(2);
});
