<?php

use App\Enums\RegistrationStatus;
use App\Mail\RegistrationConfirmationMail;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use App\Services\Participants\AutomaticIdentityResolution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../../Support/PublicVerification.php';

beforeEach(function () {
    $this->staff = User::factory()->create(['role' => 'admin', 'email_two_factor_enabled' => false]);
    $this->event = Event::create(['event_name' => 'Recovery run', 'event_type' => 'community_run', 'event_kind' => 'running',
        'status' => 'registration_open', 'event_date' => today(), 'created_by' => $this->staff->id,
        'registration_slug' => 'recovery-run', 'registration_form_saved_at' => now(),
        'registration_common_field_requirements' => ['email' => true, 'contact_number' => false]]);
    $this->master = Registration::create(['event_id' => $this->event->id,
        'participant_id' => Participant::create(['pool' => 'running', 'first_name' => 'Maria', 'middle_name' => 'Reyes', 'last_name' => 'Santos', 'email' => 'maria@example.com'])->id,
        'registration_status' => 'confirmed', 'source' => 'form']);
    $this->held = Registration::create(['event_id' => $this->event->id,
        'participant_id' => Participant::create(['pool' => 'running', 'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@example.com'])->id,
        'registration_status' => 'held', 'source' => 'form', 'held_candidate_registration_id' => $this->master->id,
        'attendance_code' => 'ABCD-1234']);
});

it('accepts omitted middle names separately without organizer review', function () {
    $this->held->delete();
    completePublicRegistration($this, $this->event, ['first_name' => 'Maria', 'last_name' => 'Santos', 'age' => 28,
        'email' => 'maria@example.com', 'address' => 'Manila', 'emergency_contact_name' => 'Jose Santos',
        'emergency_contact_relationship' => 'Father', 'emergency_contact_number' => '09171234567'])->assertSessionHasNoErrors();
    $row = Registration::latest('id')->firstOrFail();
    expect($row->isActive())->toBeTrue()->and($row->participant_id)->not->toBe($this->master->participant_id)
        ->and(Registration::held()->count())->toBe(0)->and(Registration::active()->count())->toBe(2);
    $this->assertDatabaseHas('registration_identity_decisions', ['registration_id' => $row->id, 'action' => 'kept_separate']);
});

it('releases a legacy uncertain identity once and sends confirmation once', function () {
    Mail::fake();
    $resolver = app(AutomaticIdentityResolution::class);
    expect($resolver->resolveHeld($this->held->id))->toBeTrue()
        ->and($resolver->resolveHeld($this->held->id))->toBeFalse()
        ->and($this->held->fresh()->isActive())->toBeTrue()
        ->and($this->held->fresh()->attendance)->toBeNull();
    expect(DB::table('registration_identity_decisions')->where('registration_id', $this->held->id)->count())->toBe(1);
    Mail::assertSent(RegistrationConfirmationMail::class, 1);
});

it('automatically makes an exact legacy duplicate terminal and preserves both records', function () {
    $this->held->participant->update(['middle_name' => 'Reyes']);
    app(AutomaticIdentityResolution::class)->resolveHeld($this->held->id);
    $duplicate = $this->held->fresh();
    expect($duplicate->registration_status)->toBe(RegistrationStatus::Duplicate)
        ->and($duplicate->duplicate_of_registration_id)->toBe($this->master->id)
        ->and(Registration::count())->toBe(2)
        ->and(Participant::count())->toBe(2);
    $this->actingAs($this->staff)->post("/events/{$this->event->id}/attendance/{$duplicate->id}/mark?force_open=1", ['status' => 'present'])->assertStatus(422);
    expect(fn () => $duplicate->update(['registration_status' => 'confirmed']))->toThrow(DomainException::class);
    expect(fn () => $duplicate->attendance()->create(['attendance_status' => 'present']))->toThrow(ValidationException::class);
});

it('keeps conflicting birthdates separate when releasing legacy holds', function () {
    $this->master->participant->update(['birthday' => '2000-01-01']);
    $this->held->participant->update(['middle_name' => 'Reyes', 'birthday' => '2002-01-01']);
    app(AutomaticIdentityResolution::class)->resolveHeld($this->held->id);
    expect($this->held->fresh()->isActive())->toBeTrue()->and($this->held->fresh()->duplicate_of_registration_id)->toBeNull();
});

it('does not resolve a legacy hold using unverified contact snapshots', function () {
    $this->held->participant->update(['middle_name' => 'Reyes', 'email' => 'different@example.com']);
    $this->held->update(['submitted_email' => 'maria@example.com']);
    app(AutomaticIdentityResolution::class)->resolveHeld($this->held->id);
    expect($this->held->fresh()->isActive())->toBeTrue();
});

it('does not reactivate cancelled or removed registrations', function (string $status) {
    $this->held->update(['registration_status' => $status]);
    expect(app(AutomaticIdentityResolution::class)->resolveHeld($this->held->id))->toBeFalse()
        ->and($this->held->fresh()->registration_status->value)->toBe($status);
})->with(['cancelled', 'removed', 'duplicate']);

it('rejects manual duplicate decisions and keeps the history read only', function (string $action) {
    $this->actingAs($this->staff)->post("/events/{$this->event->id}/possible-duplicates/{$this->held->id}/resolve", ['action' => $action])->assertStatus(410);
    expect($this->held->fresh()->isHeld())->toBeTrue();
})->with(['confirm_duplicate', 'confirm_separate', 'correct_details', 'link_candidate', 'remove']);

it('shows automatic decisions only to authenticated staff', function () {
    $this->get("/events/{$this->event->id}/possible-duplicates")->assertRedirect('/login');
    $this->actingAs($this->staff)->get("/events/{$this->event->id}/possible-duplicates")
        ->assertOk()->assertInertia(fn ($page) => $page->component('events/duplicates')
        ->where('decisions.data.0.registration_id', $this->master->id)
        ->where('decisions.data.0.rule_version', AutomaticIdentityResolution::VERSION)
        ->missing('held_registrations'));
});

it('supports a read only rollout count and an idempotent legacy command', function () {
    Mail::fake();
    $this->artisan('registrations:resolve-held --dry-run')->assertSuccessful();
    expect($this->held->fresh()->isHeld())->toBeTrue();
    $this->artisan('registrations:resolve-held')->assertSuccessful();
    $this->artisan('registrations:resolve-held')->assertSuccessful();
    expect(Registration::held()->count())->toBe(0);
    Mail::assertSent(RegistrationConfirmationMail::class, 1);
});

it('rejects cross-pool legacy identity resolution without changing history', function () {
    $this->held->participant->update(['pool' => 'church']);
    expect(fn () => app(AutomaticIdentityResolution::class)->resolveHeld($this->held->id))->toThrow(DomainException::class);
    expect($this->held->fresh()->isHeld())->toBeTrue();
});

it('preserves existing attendance while releasing a legacy hold', function () {
    $this->held->update(['registration_status' => 'confirmed']);
    $attendance = $this->held->attendance()->create(['attendance_status' => 'present', 'attendance_time' => now()]);
    $this->held->update(['registration_status' => 'held']);
    $this->held->participant->update(['middle_name' => 'Reyes']);
    app(AutomaticIdentityResolution::class)->resolveHeld($this->held->id);
    expect($this->held->fresh()->isActive())->toBeTrue()
        ->and($attendance->fresh()->registration_id)->toBe($this->held->id);
});

it('does not bypass an inactive exact registration when processing a legacy hold', function () {
    $this->master->update(['registration_status' => 'cancelled']);
    $this->held->participant->update(['middle_name' => 'Reyes']);
    app(AutomaticIdentityResolution::class)->resolveHeld($this->held->id);
    expect($this->held->fresh()->registration_status)->toBe(RegistrationStatus::Duplicate)
        ->and($this->master->fresh()->registration_status)->toBe(RegistrationStatus::Cancelled);
});

it('assigns a missing attendance code when releasing a legacy hold', function () {
    $this->held->update(['attendance_code' => null]);
    app(AutomaticIdentityResolution::class)->resolveHeld($this->held->id);
    expect($this->held->fresh()->attendance_code)->toMatch('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/');
});

it('keeps one active registration when two legacy holds match each other exactly', function () {
    $this->master->update(['registration_status' => 'held']);
    $this->held->participant->update(['middle_name' => 'Reyes']);
    $this->artisan('registrations:resolve-held')->assertSuccessful();
    expect(Registration::active()->count())->toBe(1)
        ->and(Registration::where('registration_status', 'duplicate')->count())->toBe(1)
        ->and(Registration::held()->count())->toBe(0);
    $duplicate = Registration::where('registration_status', 'duplicate')->sole();
    expect($duplicate->duplicate_of_registration_id)->toBe(Registration::active()->sole()->id);
});
