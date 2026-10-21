<?php

use App\Models\DiscipleshipEnrollment;
use App\Models\DiscipleshipProgram;
use App\Models\Event;
use App\Models\FellowshipEvent;
use App\Models\FirstTimer;
use App\Models\Member;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\ServiceOccurrence;
use App\Models\User;
use Database\Seeders\DiscipleshipProgramSeeder;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../../Support/PublicVerification.php';

it('registers an existing church identity as a separate running person and blocks a repeat', function () {
    $person = Participant::resolveFrom('church', 'pool@example.com', '09171234567', ['first_name' => 'Maria', 'last_name' => 'Santos']);
    $event = Event::create(['created_by' => User::factory()->create()->id, 'event_kind' => 'running', 'event_type' => 'community_run',
        'event_name' => 'Pool run', 'status' => 'registration_open', 'registration_slug' => 'pool-run', 'registration_form_saved_at' => now(),
        'registration_common_field_requirements' => ['email' => true, 'contact_number' => false]]);
    $payload = ['first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'pool@example.com', 'contact_number' => '09171234567', 'age' => 28,
        'address' => 'Manila', 'emergency_contact_name' => 'Jose Santos', 'emergency_contact_relationship' => 'Father', 'emergency_contact_number' => '09171234567'];
    completePublicRegistration($this, $event, $payload)->assertSessionHasNoErrors();
    expect(Participant::count())->toBe(2)->and(Registration::sole()->isActive())->toBeTrue()
        ->and(Registration::sole()->participant_id)->not->toBe($person->id)
        ->and(Registration::sole()->participant->pool)->toBe('running');
    $this->post('/r/pool-run', $payload)->assertSessionHasErrors('identity');
});

it('rejects absent or invalid pool creation and separates resolver matches', function () {
    expect(fn () => Participant::create(['first_name' => 'Maria', 'last_name' => 'Santos']))->toThrow(InvalidArgumentException::class);
    expect(fn () => Participant::findByComposite('invalid', null, null, null, null))->toThrow(InvalidArgumentException::class);
    $attributes = ['first_name' => 'Maria', 'last_name' => 'Santos'];
    $church = Participant::resolveFrom('church', 'same@example.com', '09171234567', $attributes);
    $running = Participant::resolveFrom('running', 'same@example.com', '09171234567', $attributes);
    expect($church->id)->not->toBe($running->id)
        ->and(Participant::resolveFrom('running', 'SAME@example.com', '09171234567', $attributes)->id)->toBe($running->id);
});

it('separates shared history and repairs standalone church references idempotently', function () {
    $staff = User::factory()->create();
    $shared = Participant::create(['pool' => 'church', 'first_name' => 'Shared', 'last_name' => 'Person', 'email' => 'shared@example.com']);
    $runningEvent = Event::create(['created_by' => $staff->id, 'event_name' => 'Run', 'event_type' => 'community_run', 'event_kind' => 'running']);
    $churchEvent = Event::create(['created_by' => $staff->id, 'event_name' => 'Church', 'event_type' => 'community_run', 'event_kind' => 'church']);
    $run = Registration::create(['event_id' => $runningEvent->id, 'participant_id' => $shared->id, 'registration_status' => 'confirmed', 'source' => 'form']);
    $church = Registration::create(['event_id' => $churchEvent->id, 'participant_id' => $shared->id, 'registration_status' => 'confirmed', 'source' => 'form']);
    $attendance = $run->attendance()->create(['attendance_status' => 'present']);
    $member = Member::create(['participant_id' => $shared->id]);
    $first = FirstTimer::create(['participant_id' => $shared->id]);
    $occurrence = ServiceOccurrence::create(['service_type' => 'morning', 'occurrence_date' => today(), 'created_by' => $staff->id]);
    foreach (['service_attendance_members', 'service_attendance_first_timers'] as $table) {
        DB::table($table)->insert(['service_occurrence_id' => $occurrence->id, 'participant_id' => $shared->id, 'attendance_status' => 'present']);
    }
    $fellowship = FellowshipEvent::create(['fellowship_type' => 'family', 'name' => 'Family', 'event_date' => today(), 'created_by' => $staff->id]);
    DB::table('fellowship_attendances')->insert(['fellowship_event_id' => $fellowship->id, 'participant_id' => $shared->id, 'attendance_status' => 'present']);
    $this->seed(DiscipleshipProgramSeeder::class);
    $program = DiscipleshipProgram::firstOrFail();
    $enrollment = DiscipleshipEnrollment::create(['participant_id' => $shared->id, 'program_id' => $program->id, 'status' => 'enrolled', 'created_by' => $staff->id]);
    $standalone = Participant::create(['pool' => 'running', 'first_name' => 'Standalone', 'last_name' => 'Church']);
    $standaloneMember = Member::create(['participant_id' => $standalone->id]);
    $standaloneFirst = FirstTimer::create(['participant_id' => $standalone->id]);
    $tables = ['registrations', 'attendances', 'members', 'first_timers', 'service_attendance_members', 'service_attendance_first_timers', 'fellowship_attendances', 'discipleship_enrollments'];
    $counts = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]);
    $migration = require database_path('migrations/2026_10_22_100200_repair_participant_pools_and_preserve_review_audits.php');
    $migration->up();
    $churchId = $church->fresh()->participant_id;
    expect($churchId)->not->toBe($shared->id)->and($shared->fresh()->pool)->toBe('running')
        ->and($run->fresh()->participant_id)->toBe($shared->id)->and($attendance->fresh()->registration_id)->toBe($run->id)
        ->and($member->fresh()->participant_id)->toBe($churchId)->and($first->fresh()->participant_id)->toBe($churchId)
        ->and($enrollment->fresh()->participant_id)->toBe($churchId)->and($standalone->fresh()->pool)->toBe('church')
        ->and($standaloneMember->fresh()->participant_id)->toBe($standalone->id)->and($standaloneFirst->fresh()->participant_id)->toBe($standalone->id);
    foreach (['service_attendance_members', 'service_attendance_first_timers', 'fellowship_attendances'] as $table) {
        expect(DB::table($table)->value('participant_id'))->toBe($churchId);
    }
    $migration->up();
    expect(Participant::count())->toBe(3);
    foreach ($counts as $table => $count) {
        expect(DB::table($table)->count())->toBe($count);
    }
});
