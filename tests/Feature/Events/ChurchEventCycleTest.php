<?php

use App\Enums\FellowshipType;
use App\Enums\ServiceType;
use App\Mail\RegistrationVerificationOtpMail;
use App\Models\Event;
use App\Models\FellowshipEvent;
use App\Models\Registration;
use App\Models\ServiceOccurrence;
use App\Models\User;
use App\Services\Church\RegistrationBridge;
use App\Services\RegistrationVerification\RegistrationVerificationService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

function amendment_f_cycle_register(TestCase $test, Event $event, array $payload): Registration
{
    $response = $test->post('/r/'.$event->registration_slug, $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect('/r/'.$event->registration_slug.'/verification');
    $cookieName = RegistrationVerificationService::cookieName($event);
    $test->withCookie($cookieName, $response->getCookie($cookieName)->getValue());
    $verificationUrl = '/r/'.$event->registration_slug.'/verification';

    $test->post($verificationUrl.'/email/resend', ['recaptcha_token' => 'valid-test-token'])
        ->assertSessionHasNoErrors();
    $code = Mail::sent(RegistrationVerificationOtpMail::class)->last()->code;
    $test->post($verificationUrl.'/email', ['code' => $code])->assertSessionHasNoErrors();
    $test->post($verificationUrl.'/complete')->assertSessionHasNoErrors();

    return Registration::query()->where('event_id', $event->id)
        ->where('submitted_email', $payload['email'])->sole();
}

function amendment_f_cycle_run(TestCase $test, Event $event): void
{
    $staff = User::factory()->create(['role' => 'admin', 'email_two_factor_enabled' => false]);
    $test->actingAs($staff);
    Mail::fake();
    config(['services.recaptcha.site_key' => 'site-key', 'services.recaptcha.secret_key' => 'secret-key']);
    Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response([
        'success' => true,
        'hostname' => parse_url(config('app.url'), PHP_URL_HOST),
    ])]);

    $test->get('/r/'.$event->registration_slug)->assertNotFound();
    $test->post(route('church.registration.publish', $event), ['open' => true])
        ->assertRedirect()->assertSessionHasNoErrors();
    $event->refresh();
    $test->get('/r/'.$event->registration_slug)->assertOk();

    $payload = [
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria-cycle@example.com',
        'contact_number' => '09171234567',
        'birthday' => '1995-05-12',
        'address' => 'Barangay Uno, Manila',
        'participant_type' => 'Guest',
        'emergency_contact_first_name' => 'Jose',
        'emergency_contact_last_name' => 'Santos',
        'emergency_contact_relationship' => 'Parent',
        'emergency_contact_number' => '09181234567',
        'consent' => true,
    ];
    $publicRegistration = amendment_f_cycle_register($test, $event, $payload);
    $test->post('/r/'.$event->registration_slug, $payload)->assertSessionHasErrors('identity');

    $likelyDuplicate = amendment_f_cycle_register($test, $event, [
        ...$payload,
        'middle_name' => 'Reyes',
        'email' => 'maria-reyes-cycle@example.com',
    ]);
    expect($likelyDuplicate->isHeld())->toBeTrue()
        ->and(Registration::active()->where('event_id', $event->id)->count())->toBe(1);
    $test->post(route('events.possible-duplicates.resolve', [$event, $likelyDuplicate]), [
        'action' => 'confirm_separate',
    ])->assertSessionHasNoErrors();
    expect($likelyDuplicate->fresh()->isActive())->toBeTrue();

    $test->post(route('church.registrations.store', $event), [
        'first_name' => 'Ana',
        'last_name' => 'Garcia',
        'email' => 'ana-cycle@example.com',
        'participant_type' => 'Member',
    ])->assertSessionHasNoErrors();
    $manualRegistration = Registration::query()
        ->where('event_id', $event->id)
        ->where('submitted_email', 'ana-cycle@example.com')->sole();

    $test->get(route('church.attendance', ['event' => $event, 'code' => $publicRegistration->attendance_code]))
        ->assertInertia(fn ($page) => $page->has('rows', 1)
            ->where('rows.0.id', $publicRegistration->id));
    $test->post(route('church.attendance.mark', [$event, $publicRegistration]), ['status' => 'present'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $test->post(route('church.attendance.mark', [$event, $manualRegistration]), ['status' => 'absent'])
        ->assertRedirect()->assertSessionHasNoErrors();

    $test->get(route('church.attendance', ['event' => $event, 'summary' => 1]))
        ->assertInertia(fn ($page) => $page
            ->where('counts.registered', 3)
            ->where('counts.present', 1)
            ->where('counts.absent', 1)
            ->where('counts.unmarked', 1));
    $test->get(route('church.participants.history', $publicRegistration->participant))
        ->assertInertia(fn ($page) => $page
            ->where('attendance_count', 1)
            ->where('event_types.0', $event->church_category));
}

it('completes the full public registration, duplicate, attendance and history cycle for a generic church event', function () {
    $staff = User::factory()->create(['role' => 'admin', 'email_two_factor_enabled' => false]);
    $event = Event::create([
        'created_by' => $staff->id,
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Kids Ministry',
        'church_status' => 'upcoming',
        'church_registration_open' => false,
        'event_name' => 'Kids Ministry Gathering',
        'event_date' => today()->addWeek()->toDateString(),
        'registration_slug' => 'kids-cycle-test',
        'registration_form_version' => 2,
        'registration_form_saved_at' => now(),
        'registration_common_field_requirements' => ['email' => true, 'contact_number' => false, 'address' => true],
    ]);

    amendment_f_cycle_run($this, $event);
});

it('completes the full public registration, duplicate, attendance and history cycle for a bridged Service occurrence', function () {
    $staff = User::factory()->create(['role' => 'admin', 'email_two_factor_enabled' => false]);
    $occurrence = ServiceOccurrence::create([
        'service_type' => ServiceType::Morning->value,
        'occurrence_date' => today()->addWeek()->toDateString(),
        'created_by' => $staff->id,
    ]);
    $event = app(RegistrationBridge::class)->activate($occurrence);

    amendment_f_cycle_run($this, $event);
});

it('completes the full public registration, duplicate, attendance and history cycle for a bridged Fellowship event', function () {
    $staff = User::factory()->create(['role' => 'admin', 'email_two_factor_enabled' => false]);
    $legacyEvent = FellowshipEvent::create([
        'fellowship_type' => FellowshipType::Family->value,
        'name' => 'Family Fellowship',
        'event_date' => today()->addWeek()->toDateString(),
        'created_by' => $staff->id,
    ]);
    $event = app(RegistrationBridge::class)->activate($legacyEvent);

    amendment_f_cycle_run($this, $event);
});

it('creates a Youth Camp event through its category and runs staff attendance and public email verification', function () {
    $staff = User::factory()->create(['role' => 'admin', 'email_two_factor_enabled' => false]);
    $this->actingAs($staff);

    $this->get('/events/create/church/youth/camp')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/create')
            ->where('preset_church_category', 'Youth')
            ->where('preset_church_activity_category', 'Youth Camp')
            ->where('lock_specialist_activity', true));

    $response = $this->post(route('events.store'), [
        'specialist_workflow' => true,
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Youth',
        'church_activity_category' => 'Youth Camp',
        'church_status' => 'upcoming',
        'event_name' => 'Youth Camp Specialist Test',
        'event_date' => today()->addWeek()->toDateString(),
        'start_time' => '09:00',
        'venue' => 'Youth Camp Grounds',
        'venue_address' => 'Youth Camp test address',
    ])->assertRedirect();

    $event = Event::query()->where('event_name', 'Youth Camp Specialist Test')->sole();
    $response->assertRedirect(route('events.show', $event));
    expect($event->church_category)->toBe('Youth')
        ->and($event->church_activity_category)->toBe('Youth Camp');

    $event->update([
        'registration_slug' => 'youth-camp-specialist-test',
        'registration_form_version' => 2,
        'registration_form_saved_at' => now(),
        'registration_common_field_requirements' => [
            'email' => true,
            'contact_number' => false,
            'address' => true,
        ],
    ]);

    $this->post(route('church.registrations.store', $event), [
        'first_name' => 'Ana',
        'last_name' => 'Garcia',
        'email' => 'ana-youth-specialist@example.com',
        'participant_type' => 'Member',
    ])->assertSessionHasNoErrors();
    $staffRegistration = Registration::query()
        ->where('event_id', $event->id)
        ->where('submitted_email', 'ana-youth-specialist@example.com')
        ->sole();

    $this->post(route('church.attendance.mark', [$event, $staffRegistration]), [
        'status' => 'present',
    ])->assertSessionHasNoErrors();
    $this->get(route('church.attendance', ['event' => $event, 'summary' => 1]))
        ->assertInertia(fn ($page) => $page
            ->where('counts.registered', 1)
            ->where('counts.present', 1)
            ->where('counts.absent', 0)
            ->where('counts.unmarked', 0));

    Mail::fake();
    config(['services.recaptcha.site_key' => 'site-key', 'services.recaptcha.secret_key' => 'secret-key']);
    Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response([
        'success' => true,
        'hostname' => parse_url(config('app.url'), PHP_URL_HOST),
    ])]);
    $this->post(route('church.registration.publish', $event), ['open' => true])
        ->assertSessionHasNoErrors();
    $publicRegistration = amendment_f_cycle_register($this, $event, [
        'first_name' => 'Daniel',
        'last_name' => 'Reyes',
        'email' => 'daniel-youth-specialist@example.com',
        'contact_number' => '09171234567',
        'birthday' => '1995-05-12',
        'address' => 'Youth Camp attendee address',
        'participant_type' => 'Guest',
        'emergency_contact_first_name' => 'Maria',
        'emergency_contact_last_name' => 'Reyes',
        'emergency_contact_relationship' => 'Friend',
        'emergency_contact_number' => '09181234567',
        'consent' => true,
    ]);

    expect($publicRegistration->attendance_code)->not->toBeNull()
        ->and($publicRegistration->isActive())->toBeTrue();
});
