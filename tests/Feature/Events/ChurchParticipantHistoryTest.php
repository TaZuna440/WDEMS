<?php

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
use App\Services\Church\ParticipantHistoryService;
use App\Services\Church\RegistrationBridge;

beforeEach(function () {
    $this->staff = User::factory()->create(['role' => 'admin', 'email_two_factor_enabled' => false]);
    $this->actingAs($this->staff);
    $this->person = Participant::create([
        'pool' => 'church',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'church-history@example.com',
    ]);
});

it('aggregates attended history across generic Events, unbridged modules, and Discipleship only', function () {
    $communityEvent = Event::create([
        'created_by' => $this->staff->id,
        'event_kind' => 'running',
        'event_type' => 'community_run',
        'event_name' => 'Community Run',
        'event_date' => today()->subDays(3)->toDateString(),
        'status' => 'completed',
    ]);
    $runningRegistration = Registration::create([
        'event_id' => $communityEvent->id,
        'participant_id' => $this->person->id,
        'source' => 'form',
        'registration_status' => 'confirmed',
    ]);
    $runningRegistration->attendance()->create(['attendance_status' => 'present']);

    $presentChurchEvent = Event::create([
        'created_by' => $this->staff->id,
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Young Adult',
        'church_status' => 'completed',
        'event_name' => 'Young Adult Service',
        'event_date' => today()->subDays(2)->toDateString(),
    ]);
    $presentRegistration = Registration::create([
        'event_id' => $presentChurchEvent->id,
        'participant_id' => $this->person->id,
        'source' => 'manual',
        'registration_status' => 'confirmed',
    ]);
    $presentRegistration->attendance()->create(['attendance_status' => 'present']);

    $absentChurchEvent = Event::create([
        'created_by' => $this->staff->id,
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Kids Ministry',
        'church_status' => 'completed',
        'event_name' => 'Kids Service',
        'event_date' => today()->subDay()->toDateString(),
    ]);
    $absentRegistration = Registration::create([
        'event_id' => $absentChurchEvent->id,
        'participant_id' => $this->person->id,
        'source' => 'manual',
        'registration_status' => 'confirmed',
    ]);
    $absentRegistration->attendance()->create(['attendance_status' => 'absent']);

    $service = ServiceOccurrence::create([
        'service_type' => 'morning',
        'occurrence_date' => today()->subWeek()->toDateString(),
        'created_by' => $this->staff->id,
    ]);
    ServiceAttendanceMember::create([
        'service_occurrence_id' => $service->id,
        'participant_id' => $this->person->id,
        'attendance_status' => 'present',
        'recorded_by' => $this->staff->id,
        'recorded_at' => now()->subWeek(),
    ]);
    ServiceAttendanceMember::create([
        'service_occurrence_id' => $service->id,
        'participant_id' => Participant::create(['pool' => 'church', 'first_name' => 'Jose', 'last_name' => 'Santos'])->id,
        'attendance_status' => 'excused',
        'recorded_by' => $this->staff->id,
    ]);

    $fellowship = FellowshipEvent::create([
        'fellowship_type' => 'family',
        'name' => 'Family Fellowship',
        'event_date' => today()->subWeeks(2)->toDateString(),
        'created_by' => $this->staff->id,
    ]);
    FellowshipAttendance::create([
        'fellowship_event_id' => $fellowship->id,
        'participant_id' => $this->person->id,
        'attendance_status' => 'present',
        'recorded_by' => $this->staff->id,
        'recorded_at' => now()->subWeeks(2),
    ]);

    $program = DiscipleshipProgram::create([
        'name' => 'Life Class',
        'slug' => 'life-class-history-test',
        'description' => 'History test program',
        'display_order' => 1,
    ]);
    $batch = DiscipleshipBatch::create([
        'program_id' => $program->id,
        'name' => 'Spring Cohort',
        'start_date' => today()->subWeeks(3)->toDateString(),
        'created_by' => $this->staff->id,
    ]);
    $enrollment = DiscipleshipEnrollment::create([
        'participant_id' => $this->person->id,
        'program_id' => $program->id,
        'batch_id' => $batch->id,
        'status' => 'completed',
        'enrolled_at' => now()->subWeeks(3),
        'created_by' => $this->staff->id,
    ]);
    $session = DiscipleshipSession::create([
        'batch_id' => $batch->id,
        'session_date' => today()->subWeeks(3)->toDateString(),
        'topic' => 'Welcome',
        'created_by' => $this->staff->id,
    ]);
    DiscipleshipAttendance::create([
        'enrollment_id' => $enrollment->id,
        'session_id' => $session->id,
        'status' => 'present',
        'recorded_by' => $this->staff->id,
    ]);

    $history = app(ParticipantHistoryService::class)->build($this->person);

    expect($history['attendance_count'])->toBe(4)
        ->and($history['event_types'])->toContain('Young Adult', 'Services', 'Fellowship', 'Discipleship')
        ->and(collect($history['events'])->pluck('event_name'))->toContain(
            'Young Adult Service', 'Morning Service', 'Family Fellowship', 'Life Class — Spring Cohort — Welcome',
        )
        ->and(collect($history['events'])->pluck('event_name'))->not->toContain('Community Run', 'Kids Service');
});

it('counts a bridged legacy service attendance only once', function () {
    $occurrence = ServiceOccurrence::create([
        'service_type' => 'morning',
        'occurrence_date' => today()->subDay()->toDateString(),
        'created_by' => $this->staff->id,
    ]);
    ServiceAttendanceMember::create([
        'service_occurrence_id' => $occurrence->id,
        'participant_id' => $this->person->id,
        'attendance_status' => 'present',
        'recorded_by' => $this->staff->id,
        'recorded_at' => now()->subDay(),
    ]);

    app(RegistrationBridge::class)->activate($occurrence);

    $history = app(ParticipantHistoryService::class)->build($this->person);
    expect($history['attendance_count'])->toBe(1)
        ->and($history['events'])->toHaveCount(1)
        ->and($history['events'][0]['event_type'])->toBe('Services');
});

it('serves the history page to staff and hides running and provisional participants', function () {
    $genericEvent = Event::create([
        'created_by' => $this->staff->id,
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Fellowship',
        'church_status' => 'completed',
        'event_name' => 'Church gathering',
        'event_date' => today()->subDay()->toDateString(),
    ]);
    $registration = Registration::create([
        'event_id' => $genericEvent->id,
        'participant_id' => $this->person->id,
        'source' => 'manual',
        'registration_status' => 'confirmed',
    ]);
    $registration->attendance()->create(['attendance_status' => 'present']);

    $this->get(route('church.participants.history', $this->person))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/church/participants/history')
            ->where('participant.full_name', 'Maria Santos')
            ->where('attendance_count', 1)
            ->where('event_types.0', 'Fellowship'));

    $running = Participant::create(['pool' => 'running', 'first_name' => 'Run', 'last_name' => 'Person']);
    $this->get(route('church.participants.history', $running))->assertNotFound();

    $provisional = Participant::create([
        'pool' => 'church',
        'first_name' => 'Quick',
        'last_name' => 'Walkin',
        'provisional_event_id' => $genericEvent->id,
    ]);
    $this->get(route('church.participants.history', $provisional))->assertNotFound();
});
