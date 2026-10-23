<?php

use App\Mail\RegistrationConfirmationMail;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\RegistrationVerificationChallenge;
use App\Models\User;
use App\Services\RegistrationConfirmationService;
use App\Services\RegistrationVerification\RegistrationVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * Pins the confirmation-mail behavior on successful finalize.
 *
 * The mail is a durable second copy of the attendance code. It is
 * dispatched via DB::afterCommit() inside finalize(), so a Resend
 * transport failure cannot roll back the registration. It only fires
 * when the participant provided an email — the code is optional in
 * some events, and the participant always sees it on the confirmation
 * page regardless.
 *
 * Helpers are prefixed confmail_ to avoid Pest function collisions.
 */
function confmail_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function confmail_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => confmail_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'Confmail Test Run',
        'event_date' => today()->addDays(3)->toDateString(),
        'status' => 'registration_open',
        'registration_form_saved_at' => now(),
        'registration_slug' => 'confmail-'.bin2hex(random_bytes(4)),
        'registration_common_field_requirements' => [
            'email' => true,
            'contact_number' => true,
            'address' => false,
        ],
    ], $overrides));
}

/**
 * A verified challenge ready for finalize. Both required channels are
 * marked verified so allVerified() returns true.
 *
 * @param  array<string, mixed>  $payload
 * @return array{0: string, 1: RegistrationVerificationChallenge}
 */
function confmail_verified_challenge(Event $event, array $payload): array
{
    $token = bin2hex(random_bytes(32));

    $challenge = RegistrationVerificationChallenge::forceCreate([
        'event_id' => $event->id,
        'token_hash' => hash('sha256', $token),
        'payload' => $payload,
        'email_required' => $event->isCommonFieldRequired('email'),
        'phone_required' => $event->isCommonFieldRequired('contact_number'),
        'expires_at' => now()->addHours(24),
        'email_verified_at' => $event->isCommonFieldRequired('email') ? now() : null,
        'phone_verified_at' => $event->isCommonFieldRequired('contact_number') ? now() : null,
    ]);

    return [$token, $challenge];
}

it('renders the Registration Code wording and code in the mail body', function () {
    $mail = new RegistrationConfirmationMail('KM4X-P2N7', 'Test Event');

    $html = $mail->render();

    expect($html)->toContain('KM4X-P2N7', 'Registration Code')
        ->and($html)->toContain('Test Event')
        ->and($mail->envelope()->subject)->toBe('WDEMS — Your Test Event Registration Code');
});

it('sends the confirmation mail on successful finalize', function () {
    Mail::fake();

    $event = confmail_event();
    $payload = [
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'age' => 30,
        'email' => 'maria@example.com',
        'contact_number' => '09171234567',
        'address' => '123 Test St',
        'emergency_contact_name' => 'Jose Santos', 'emergency_contact_relationship' => 'Father', 'emergency_contact_number' => '+63 917 765 4321',
    ];

    [$token] = confmail_verified_challenge($event, $payload);

    app(RegistrationVerificationService::class)->finalize($event, $token);

    $registration = Registration::where('event_id', $event->id)->first();
    expect($registration)->not->toBeNull()
        ->and($registration->attendance_code)->not->toBeNull()
        ->and($registration->confirmation_email_sent_at)->not->toBeNull();

    Mail::assertSent(
        RegistrationConfirmationMail::class,
        function (RegistrationConfirmationMail $mail) use ($registration) {
            return $mail->hasTo('maria@example.com')
                && $mail->code === $registration->attendance_code
                && $mail->eventName === 'Confmail Test Run';
        },
    );
});

it('does not send the confirmation mail when the participant did not provide an email', function () {
    Mail::fake();

    $event = confmail_event([
        'registration_common_field_requirements' => [
            'email' => false,
            'contact_number' => true,
            'address' => false,
        ],
    ]);

    $payload = [
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'age' => 30,
        'contact_number' => '09171234567',
        'address' => '123 Test St',
        'emergency_contact_name' => 'Jose Santos', 'emergency_contact_relationship' => 'Father', 'emergency_contact_number' => '+63 917 765 4321',
    ];

    [$token] = confmail_verified_challenge($event, $payload);

    app(RegistrationVerificationService::class)->finalize($event, $token);

    $registration = Registration::where('event_id', $event->id)->first();
    expect($registration)->not->toBeNull()
        ->and($registration->submitted_email)->toBeNull()
        ->and($registration->attendance_code)->not->toBeNull();

    Mail::assertNotSent(RegistrationConfirmationMail::class);
});

it('does not send confirmation email while a registration is held', function () {
    Mail::fake();
    $event = confmail_event();
    $participant = Participant::create([
        'pool' => 'running',
        'first_name' => 'Held',
        'last_name' => 'Participant',
        'email' => 'held@example.com',
    ]);
    $registration = Registration::create([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'registration_status' => 'held',
        'source' => 'form',
        'submitted_email' => 'held@example.com',
        'attendance_code' => 'HELD-CODE',
    ]);

    app(RegistrationConfirmationService::class)->sendAfterCommit($registration);

    Mail::assertNotSent(RegistrationConfirmationMail::class);
});

it('logs a delivery failure without undoing the confirmed registration', function () {
    $event = confmail_event();
    $participant = Participant::create([
        'pool' => 'running',
        'first_name' => 'Email',
        'last_name' => 'Failure',
        'email' => 'failure@example.com',
    ]);
    $registration = Registration::create([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'registration_status' => 'confirmed',
        'source' => 'form',
        'submitted_email' => 'failure@example.com',
        'attendance_code' => 'FAIL-CODE',
    ]);
    Mail::shouldReceive('to')->once()->with('failure@example.com')->andThrow(new RuntimeException('SMTP unavailable'));
    Log::shouldReceive('error')->once()->with(
        'Registration confirmation email delivery failed.',
        Mockery::on(fn (array $context): bool => $context['registration_id'] === $registration->id
            && $context['exception'] === RuntimeException::class),
    );

    app(RegistrationConfirmationService::class)->sendAfterCommit($registration);

    expect($registration->fresh()->isActive())->toBeTrue()
        ->and($registration->fresh()->confirmation_email_sent_at)->toBeNull()
        ->and(AuditLog::query()
            ->where('actor_type', 'system')
            ->where('action', 'email.failed')
            ->where('details->to', 'failure@example.com')
            ->where('details->error', 'SMTP unavailable')
            ->exists())->toBeTrue();
});
