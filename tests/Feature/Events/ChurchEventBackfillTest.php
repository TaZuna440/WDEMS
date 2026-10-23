<?php

use App\Enums\DiscipleshipAttendanceStatus;
use App\Enums\FellowshipType;
use App\Enums\ServiceType;
use App\Mail\RegistrationConfirmationMail;
use App\Models\Attendance;
use App\Models\DiscipleshipAttendance;
use App\Models\DiscipleshipBatch;
use App\Models\DiscipleshipEnrollment;
use App\Models\DiscipleshipProgram;
use App\Models\DiscipleshipSession;
use App\Models\Event;
use App\Models\FellowshipAttendance;
use App\Models\FellowshipEvent;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\ServiceAttendanceMember;
use App\Models\ServiceOccurrence;
use App\Models\User;
use App\Services\Church\ChurchEventBackfillService;
use Database\Seeders\DiscipleshipProgramSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->staff = User::factory()->create([
        'role' => 'admin',
        'email_two_factor_enabled' => false,
    ]);
});

it('dry runs without writing and reports the service attendance conversions', function () {
    $occurrence = ServiceOccurrence::create([
        'service_type' => ServiceType::Morning,
        'occurrence_date' => today()->toDateString(),
        'created_by' => $this->staff->id,
    ]);
    $late = Participant::create([
        'pool' => 'church',
        'first_name' => 'Lani',
        'last_name' => 'Late',
    ]);
    $excused = Participant::create([
        'pool' => 'church',
        'first_name' => 'Eli',
        'last_name' => 'Excused',
    ]);
    ServiceAttendanceMember::create([
        'service_occurrence_id' => $occurrence->id,
        'participant_id' => $late->id,
        'attendance_status' => 'late',
    ]);
    ServiceAttendanceMember::create([
        'service_occurrence_id' => $occurrence->id,
        'participant_id' => $excused->id,
        'attendance_status' => 'excused',
    ]);

    $report = app(ChurchEventBackfillService::class)->run('services', dryRun: true);

    expect($report['totals']['planned_events'])->toBe(1)
        ->and($report['totals']['planned_registrations'])->toBe(2)
        ->and($report['totals']['planned_attendances'])->toBe(1)
        ->and($report['status_conversions'])->toBe([
            'late->present' => 1,
            'excused->unmarked' => 1,
        ])
        ->and(Event::count())->toBe(0)
        ->and(Registration::count())->toBe(0)
        ->and(Attendance::count())->toBe(0)
        ->and($occurrence->fresh()->registration_event_id)->toBeNull();
});

it('maps late to present and preserves excused in the legacy row during execution', function () {
    Mail::fake();
    $occurrence = ServiceOccurrence::create([
        'service_type' => ServiceType::Morning,
        'occurrence_date' => today()->toDateString(),
        'created_by' => $this->staff->id,
    ]);
    $late = Participant::create([
        'pool' => 'church',
        'first_name' => 'Lani',
        'last_name' => 'Late',
        'email' => 'legacy@example.com',
    ]);
    $excused = Participant::create([
        'pool' => 'church',
        'first_name' => 'Eli',
        'last_name' => 'Excused',
    ]);
    ServiceAttendanceMember::create([
        'service_occurrence_id' => $occurrence->id,
        'participant_id' => $late->id,
        'attendance_status' => 'late',
    ]);
    ServiceAttendanceMember::create([
        'service_occurrence_id' => $occurrence->id,
        'participant_id' => $excused->id,
        'attendance_status' => 'excused',
    ]);

    $report = app(ChurchEventBackfillService::class)->run('services');
    $registrations = Registration::query()->orderBy('participant_id')->get();

    expect($report['totals']['migrated'])->toBe(1)
        ->and($registrations)->toHaveCount(2)
        ->and($registrations[0]->attendance->getRawOriginal('attendance_status'))->toBe('present')
        ->and($registrations[1]->attendance)->toBeNull()
        ->and($occurrence->fresh()->registration_event_id)->not->toBeNull()
        ->and(ServiceAttendanceMember::query()->where('attendance_status', 'excused')->exists())->toBeTrue();
    Mail::assertNotSent(RegistrationConfirmationMail::class);
});

it('maps each discipleship enrollment to one registration per session and is resumable', function () {
    $this->seed(DiscipleshipProgramSeeder::class);
    $program = DiscipleshipProgram::query()->where('slug', 'life-class')->firstOrFail();
    $batch = DiscipleshipBatch::create([
        'program_id' => $program->id,
        'name' => 'Backfill Batch',
        'start_date' => today()->toDateString(),
        'created_by' => $this->staff->id,
    ]);
    $firstSession = DiscipleshipSession::create([
        'batch_id' => $batch->id,
        'session_date' => today()->toDateString(),
        'topic' => 'Session One',
        'created_by' => $this->staff->id,
    ]);
    $secondSession = DiscipleshipSession::create([
        'batch_id' => $batch->id,
        'session_date' => today()->addDay()->toDateString(),
        'topic' => 'Session Two',
        'created_by' => $this->staff->id,
    ]);
    $participant = Participant::create([
        'pool' => 'church',
        'first_name' => 'Mina',
        'last_name' => 'Member',
    ]);
    $enrollment = DiscipleshipEnrollment::create([
        'participant_id' => $participant->id,
        'program_id' => $program->id,
        'batch_id' => $batch->id,
        'status' => 'enrolled',
        'created_by' => $this->staff->id,
    ]);
    DiscipleshipAttendance::create([
        'enrollment_id' => $enrollment->id,
        'session_id' => $firstSession->id,
        'status' => DiscipleshipAttendanceStatus::Present,
        'recorded_by' => $this->staff->id,
        'recorded_at' => now(),
    ]);
    DiscipleshipAttendance::create([
        'enrollment_id' => $enrollment->id,
        'session_id' => $secondSession->id,
        'status' => DiscipleshipAttendanceStatus::Excused,
        'recorded_by' => $this->staff->id,
        'recorded_at' => now(),
    ]);

    $service = app(ChurchEventBackfillService::class);
    $report = $service->run('discipleship');

    expect($report['totals']['migrated'])->toBe(2)
        ->and(Event::query()->where('discipleship_batch_id', $batch->id)->count())->toBe(2)
        ->and(Registration::count())->toBe(2)
        ->and(DB::table('discipleship_event_registration_links')->count())->toBe(2)
        ->and(Attendance::count())->toBe(1)
        ->and(DiscipleshipSession::query()->whereNotNull('registration_event_id')->count())->toBe(2)
        ->and($service->run('discipleship')['totals']['already_linked'])->toBe(2)
        ->and(Registration::count())->toBe(2)
        ->and(DB::table('discipleship_event_registration_links')->count())->toBe(2);
});

it('preserves Fellowship groups and creates generic attendance', function () {
    $legacy = FellowshipEvent::create([
        'fellowship_type' => FellowshipType::Family,
        'name' => 'Family Gathering',
        'event_date' => today()->toDateString(),
        'created_by' => $this->staff->id,
    ]);
    $participant = Participant::create([
        'pool' => 'church',
        'first_name' => 'Ana',
        'last_name' => 'Guest',
    ]);
    $attendance = FellowshipAttendance::create([
        'fellowship_event_id' => $legacy->id,
        'participant_id' => $participant->id,
        'attendance_status' => 'present',
        'recorded_by' => $this->staff->id,
        'recorded_at' => now(),
    ]);

    $report = app(ChurchEventBackfillService::class)->run('fellowship');
    $registration = Registration::sole();

    expect($report['totals']['migrated'])->toBe(1)
        ->and($registration->participant_type)->toBe('Guest')
        ->and($registration->attendance->getRawOriginal('attendance_status'))->toBe('present')
        ->and($attendance->fresh())->not->toBeNull()
        ->and($legacy->fresh()->registration_event_id)->not->toBeNull();
});

it('exposes the Artisan dry-run command without applying migrations to the app database', function () {
    $this->artisan('church:backfill-events', [
        '--dry-run' => true,
        '--module' => 'services',
    ])->assertSuccessful();
});
