<?php

use App\Enums\RegistrationStatus;
use App\Mail\RegistrationConfirmationMail;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use App\Services\Participants\DuplicateReview;
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

it('holds an omitted middle name through real email verification finalization', function () {
    // Remove the unrelated review fixture; the new held row must come from the endpoints.
    $this->held->delete();
    completePublicRegistration($this, $this->event, ['first_name' => 'Maria', 'last_name' => 'Santos', 'age' => 28,
        'email' => 'maria@example.com', 'similarity_acknowledged' => true, 'address' => 'Manila', 'emergency_contact_name' => 'Jose Santos',
        'emergency_contact_relationship' => 'Father', 'emergency_contact_number' => '09171234567'])
        ->assertSessionHasNoErrors();
    $held = Registration::held()->sole();
    expect($held->held_candidate_registration_id)->toBe($this->master->id)
        ->and($held->attendance)->toBeNull()
        ->and(Registration::active()->count())->toBe(1);
});

it('rejects explicit bulk marking and single check-in of held submissions', function () {
    $this->actingAs($this->staff)->post("/events/{$this->event->id}/attendance/bulk-mark?force_open=1", [
        'registration_ids' => [$this->master->id, $this->held->id],
    ])->assertStatus(422);
    $this->post("/events/{$this->event->id}/attendance/{$this->held->id}/mark?force_open=1", ['status' => 'present'])->assertStatus(422);
    expect($this->master->fresh()->attendance)->toBeNull()->and($this->held->fresh()->attendance)->toBeNull();
});

it('makes confirmed duplicates terminal and excludes them from attendance and audit', function () {
    $this->actingAs($this->staff)->post("/events/{$this->event->id}/possible-duplicates/{$this->held->id}/resolve", ['action' => 'confirm_duplicate'])->assertRedirect();
    $duplicate = $this->held->fresh();
    expect($duplicate->registration_status)->toBe(RegistrationStatus::Duplicate)
        ->and($duplicate->duplicate_of_registration_id)->toBe($this->master->id)
        ->and(app(DuplicateReview::class)->registrations($this->event)->pluck('id')->all())->toBe([$this->master->id]);
    $this->post("/events/{$this->event->id}/attendance/{$duplicate->id}/mark?force_open=1", ['status' => 'present'])->assertStatus(422);
    $this->post("/events/{$this->event->id}/attendance/bulk-mark?force_open=1", ['registration_ids' => [$duplicate->id]])->assertStatus(422);
    expect(fn () => $duplicate->update(['registration_status' => 'confirmed']))->toThrow(DomainException::class);
    expect(fn () => $duplicate->attendance()->create(['attendance_status' => 'present']))->toThrow(ValidationException::class);
});

it('rechecks corrections and preserves every review attempt', function () {
    $url = "/events/{$this->event->id}/possible-duplicates/{$this->held->id}/resolve";
    $this->actingAs($this->staff)->post($url, ['action' => 'correct_details', 'first_name' => 'Maria', 'last_name' => 'Santos'])
        ->assertSessionHas('status', fn ($message) => str_contains($message, 'remains held'));
    expect($this->held->fresh()->isHeld())->toBeTrue();
    $this->post($url, ['action' => 'correct_details', 'first_name' => 'Jose', 'last_name' => 'Garcia'])->assertSessionHasNoErrors();
    expect($this->held->fresh()->isActive())->toBeTrue()->and($this->held->fresh()->attendance)->toBeNull();
    expect(DB::table('registration_review_resolutions')->where('registration_id', $this->held->id)->count())->toBe(2);
});

it('keeps an exact correction held even when separate is requested afterwards', function () {
    $url = "/events/{$this->event->id}/possible-duplicates/{$this->held->id}/resolve";
    $this->actingAs($this->staff)->post($url, ['action' => 'correct_details', 'first_name' => 'Maria', 'middle_name' => 'Reyes', 'last_name' => 'Santos'])->assertSessionHasNoErrors();
    $this->post($url, ['action' => 'confirm_separate'])->assertSessionHasNoErrors();
    expect($this->held->fresh()->isHeld())->toBeTrue();
});

it('rejects review actions on an active registration', function (string $action) {
    $this->actingAs($this->staff)->post("/events/{$this->event->id}/possible-duplicates/{$this->master->id}/resolve", [
        'action' => $action, 'first_name' => 'Maria', 'last_name' => 'Santos',
    ])->assertStatus(422);
    expect(DB::table('registration_review_resolutions')->count())->toBe(0);
})->with(['confirm_duplicate', 'confirm_separate', 'correct_details', 'remove']);

it('releases a separate person as unmarked and audits the actor', function () {
    Mail::fake();
    $this->held->update(['submitted_email' => 'maria.registration@example.com']);

    $this->actingAs($this->staff)->post("/events/{$this->event->id}/possible-duplicates/{$this->held->id}/resolve", ['action' => 'confirm_separate', 'reason' => 'Staff checked both people'])->assertRedirect();
    expect($this->held->fresh()->isActive())->toBeTrue()->and($this->held->fresh()->attendance)->toBeNull();
    $this->assertDatabaseHas('registration_review_resolutions', ['registration_id' => $this->held->id, 'actor_id' => $this->staff->id, 'reason' => 'Staff checked both people']);
    Mail::assertSent(RegistrationConfirmationMail::class, function (RegistrationConfirmationMail $mail) {
        return $mail->hasTo('maria.registration@example.com')
            && $mail->code === 'ABCD-1234'
            && $mail->eventName === 'Recovery run';
    });
});

it('does not resend confirmation mail when an already released registration is reviewed again', function () {
    Mail::fake();
    $this->held->update(['submitted_email' => 'maria.registration@example.com']);
    $url = "/events/{$this->event->id}/possible-duplicates/{$this->held->id}/resolve";

    $this->actingAs($this->staff)->post($url, ['action' => 'confirm_separate'])->assertRedirect();
    $this->post($url, ['action' => 'confirm_separate'])->assertStatus(422);

    Mail::assertSent(RegistrationConfirmationMail::class, 1);
});

it('preserves removed submissions and before and after audit snapshots', function () {
    $this->actingAs($this->staff)->post("/events/{$this->event->id}/possible-duplicates/{$this->held->id}/resolve", ['action' => 'remove', 'reason' => 'Participant withdrew'])->assertRedirect();
    expect($this->held->fresh()->registration_status)->toBe(RegistrationStatus::Removed);
    $audit = DB::table('registration_review_resolutions')->sole();
    expect(json_decode($audit->before_snapshot, true)['registration']['registration_status'])->toBe('held')
        ->and(json_decode($audit->after_snapshot, true)['registration']['registration_status'])->toBe('removed')
        ->and($audit->actor_id)->toBe($this->staff->id)->and($audit->created_at)->not->toBeNull();
});

it('never holds on birthdate alone or clearly different family names', function () {
    $review = app(DuplicateReview::class);
    $this->master->participant->update(['birthday' => '2000-01-01']);
    expect($review->findCandidate($this->event, ['first_name' => 'Maria', 'last_name' => 'Santos', 'birthday' => '2000-01-01', 'email' => 'different@example.com']))->toBeNull();
    expect($review->findCandidate($this->event, ['first_name' => 'Jose', 'last_name' => 'Santos', 'email' => 'maria@example.com']))->toBeNull();
});
