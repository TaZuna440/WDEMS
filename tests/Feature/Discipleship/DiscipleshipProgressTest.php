<?php

use App\Models\DiscipleshipAttendance;
use App\Models\DiscipleshipBatch;
use App\Models\DiscipleshipEnrollment;
use App\Models\DiscipleshipProgram;
use App\Models\DiscipleshipSession;
use App\Models\Participant;
use App\Models\User;
use App\Services\Discipleship\DiscipleshipProgressService;
use Database\Seeders\DiscipleshipProgramSeeder;

/**
 * Phase 5F: the DiscipleshipProgressService.
 *
 * Fixtures via beforeEach only. No file-scope helper functions.
 *
 * The service is a pure calculator -- no writes, no side effects.
 * Most tests call progress() directly and assert on the returned
 * array. Two tests exercise batchSummary() and programOverview()
 * which are aggregations.
 *
 * Timing note: sessions created with a session_date in the past
 * are "applicable" (already happened). A session in the future is
 * not yet applicable. The enrollment's enrolled_at date further
 * narrows the window -- sessions before enrollment do not count.
 */
beforeEach(function () {
    $this->staff = User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
    ]);

    $this->seed(DiscipleshipProgramSeeder::class);

    $this->program = DiscipleshipProgram::where('slug', 'life-class')
        ->firstOrFail();

    $this->batch = DiscipleshipBatch::create([
        'program_id' => $this->program->id,
        'name' => 'Spring 2026',
        'start_date' => now()->subMonth()->toDateString(),
        'created_by' => $this->staff->id,
    ]);

    $this->participant = Participant::create(['pool' => 'church',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'email' => 'juan.'.uniqid().'@example.com',
    ]);

    $this->service = app(DiscipleshipProgressService::class);
});

// ----------------------------------------------------------------
// progress() -- core calculation
// ----------------------------------------------------------------

test('progress returns zero-applicable when the enrollment has no batch', function () {
    $enrollment = DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'batch_id' => null,
        'status' => 'enrolled',
        'created_by' => $this->staff->id,
    ]);

    $result = $this->service->progress($enrollment);

    expect($result['attended'])->toBe(0);
    expect($result['applicable'])->toBe(0);
    expect($result['percentage'])->toBeNull();
    expect($result['is_complete'])->toBeFalse();
});

test('progress returns null percentage when the batch has no past sessions', function () {
    $enrollment = DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'batch_id' => $this->batch->id,
        'status' => 'enrolled',
        'created_by' => $this->staff->id,
    ]);

    DiscipleshipSession::create([
        'batch_id' => $this->batch->id,
        'session_date' => now()->addWeek()->toDateString(),
        'topic' => 'Future Session',
        'created_by' => $this->staff->id,
    ]);

    $result = $this->service->progress($enrollment);

    expect($result['attended'])->toBe(0);
    expect($result['applicable'])->toBe(0);
    expect($result['percentage'])->toBeNull();
    expect($result['is_complete'])->toBeFalse();
});

test('progress counts present attendance as attended', function () {
    $session = DiscipleshipSession::create([
        'batch_id' => $this->batch->id,
        'session_date' => now()->subWeek()->toDateString(),
        'topic' => 'Session 1',
        'created_by' => $this->staff->id,
    ]);

    $enrollment = DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'batch_id' => $this->batch->id,
        'status' => 'enrolled',
        'enrolled_at' => now()->subMonth(),
        'created_by' => $this->staff->id,
    ]);

    DiscipleshipAttendance::create([
        'enrollment_id' => $enrollment->id,
        'session_id' => $session->id,
        'status' => 'present',
        'recorded_by' => $this->staff->id,
    ]);

    $result = $this->service->progress($enrollment);

    expect($result['attended'])->toBe(1);
    expect($result['applicable'])->toBe(1);
    expect($result['percentage'])->toBe(100.0);
    expect($result['is_complete'])->toBeTrue();
});

test('a stored excused attendance row still counts as attended', function () {
    $session = DiscipleshipSession::create([
        'batch_id' => $this->batch->id,
        'session_date' => now()->subWeek()->toDateString(),
        'topic' => 'Session 1',
        'created_by' => $this->staff->id,
    ]);

    $enrollment = DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'batch_id' => $this->batch->id,
        'status' => 'enrolled',
        'enrolled_at' => now()->subMonth(),
        'created_by' => $this->staff->id,
    ]);

    DiscipleshipAttendance::create([
        'enrollment_id' => $enrollment->id,
        'session_id' => $session->id,
        'status' => 'excused',
        'recorded_by' => $this->staff->id,
    ]);

    $result = $this->service->progress($enrollment);

    expect($result['attended'])->toBe(1);
    expect($result['applicable'])->toBe(1);
    expect($result['percentage'])->toBe(100.0);
});

test('progress does NOT count absent attendance as attended', function () {
    $session = DiscipleshipSession::create([
        'batch_id' => $this->batch->id,
        'session_date' => now()->subWeek()->toDateString(),
        'topic' => 'Session 1',
        'created_by' => $this->staff->id,
    ]);

    $enrollment = DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'batch_id' => $this->batch->id,
        'status' => 'enrolled',
        'enrolled_at' => now()->subMonth(),
        'created_by' => $this->staff->id,
    ]);

    DiscipleshipAttendance::create([
        'enrollment_id' => $enrollment->id,
        'session_id' => $session->id,
        'status' => 'absent',
        'recorded_by' => $this->staff->id,
    ]);

    $result = $this->service->progress($enrollment);

    expect($result['attended'])->toBe(0);
    expect($result['applicable'])->toBe(1);
    expect($result['percentage'])->toBe(0.0);
    expect($result['is_complete'])->toBeFalse();
});

test('progress excludes sessions that happened before the enrollment date', function () {
    DiscipleshipSession::create([
        'batch_id' => $this->batch->id,
        'session_date' => now()->subMonths(2)->toDateString(),
        'topic' => 'Before Enrollment',
        'created_by' => $this->staff->id,
    ]);

    $session = DiscipleshipSession::create([
        'batch_id' => $this->batch->id,
        'session_date' => now()->subWeek()->toDateString(),
        'topic' => 'After Enrollment',
        'created_by' => $this->staff->id,
    ]);

    $enrollment = DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'batch_id' => $this->batch->id,
        'status' => 'enrolled',
        'enrolled_at' => now()->subMonth(),
        'created_by' => $this->staff->id,
    ]);

    DiscipleshipAttendance::create([
        'enrollment_id' => $enrollment->id,
        'session_id' => $session->id,
        'status' => 'present',
        'recorded_by' => $this->staff->id,
    ]);

    $result = $this->service->progress($enrollment);

    // Only the post-enrollment session is applicable.
    expect($result['applicable'])->toBe(1);
    expect($result['attended'])->toBe(1);
});

test('progress excludes future sessions', function () {
    DiscipleshipSession::create([
        'batch_id' => $this->batch->id,
        'session_date' => now()->addWeek()->toDateString(),
        'topic' => 'Future',
        'created_by' => $this->staff->id,
    ]);

    $enrollment = DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'batch_id' => $this->batch->id,
        'status' => 'enrolled',
        'enrolled_at' => now()->subMonth(),
        'created_by' => $this->staff->id,
    ]);

    $result = $this->service->progress($enrollment);

    expect($result['applicable'])->toBe(0);
    expect($result['percentage'])->toBeNull();
});

test('progress with required_sessions set is complete when attended meets the threshold', function () {
    $this->program->update(['required_sessions' => 2]);
    $this->program->refresh();

    $s1 = DiscipleshipSession::create([
        'batch_id' => $this->batch->id,
        'session_date' => now()->subWeeks(3)->toDateString(),
        'topic' => 'S1',
        'created_by' => $this->staff->id,
    ]);
    $s2 = DiscipleshipSession::create([
        'batch_id' => $this->batch->id,
        'session_date' => now()->subWeeks(2)->toDateString(),
        'topic' => 'S2',
        'created_by' => $this->staff->id,
    ]);
    DiscipleshipSession::create([
        'batch_id' => $this->batch->id,
        'session_date' => now()->subWeek()->toDateString(),
        'topic' => 'S3',
        'created_by' => $this->staff->id,
    ]);

    $enrollment = DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'batch_id' => $this->batch->id,
        'status' => 'enrolled',
        'enrolled_at' => now()->subMonth(),
        'created_by' => $this->staff->id,
    ]);

    DiscipleshipAttendance::create([
        'enrollment_id' => $enrollment->id,
        'session_id' => $s1->id,
        'status' => 'present',
        'recorded_by' => $this->staff->id,
    ]);
    DiscipleshipAttendance::create([
        'enrollment_id' => $enrollment->id,
        'session_id' => $s2->id,
        'status' => 'present',
        'recorded_by' => $this->staff->id,
    ]);

    $result = $this->service->progress($enrollment);

    expect($result['attended'])->toBe(2);
    expect($result['applicable'])->toBe(3);
    expect($result['is_complete'])->toBeTrue();
    expect($result['required_sessions'])->toBe(2);
    expect($result['percentage'])->toBe(66.7);
});

test('progress with required_sessions set is NOT complete when attended is below the threshold', function () {
    $this->program->update(['required_sessions' => 5]);
    $this->program->refresh();

    $session = DiscipleshipSession::create([
        'batch_id' => $this->batch->id,
        'session_date' => now()->subWeek()->toDateString(),
        'topic' => 'S1',
        'created_by' => $this->staff->id,
    ]);

    $enrollment = DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'batch_id' => $this->batch->id,
        'status' => 'enrolled',
        'enrolled_at' => now()->subMonth(),
        'created_by' => $this->staff->id,
    ]);

    DiscipleshipAttendance::create([
        'enrollment_id' => $enrollment->id,
        'session_id' => $session->id,
        'status' => 'present',
        'recorded_by' => $this->staff->id,
    ]);

    $result = $this->service->progress($enrollment);

    expect($result['attended'])->toBe(1);
    expect($result['is_complete'])->toBeFalse();
});

// ----------------------------------------------------------------
// batchSummary()
// ----------------------------------------------------------------

test('batchSummary returns one row per enrollment on the batch', function () {
    DiscipleshipSession::create([
        'batch_id' => $this->batch->id,
        'session_date' => now()->subWeek()->toDateString(),
        'topic' => 'S1',
        'created_by' => $this->staff->id,
    ]);

    $e1 = DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'batch_id' => $this->batch->id,
        'status' => 'enrolled',
        'enrolled_at' => now()->subMonth(),
        'created_by' => $this->staff->id,
    ]);

    $p2 = Participant::create(['pool' => 'church',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria.'.uniqid().'@example.com',
    ]);
    $e2 = DiscipleshipEnrollment::create([
        'participant_id' => $p2->id,
        'program_id' => $this->program->id,
        'batch_id' => $this->batch->id,
        'status' => 'enrolled',
        'enrolled_at' => now()->subMonth(),
        'created_by' => $this->staff->id,
    ]);

    $summary = $this->service->batchSummary($this->batch);

    expect($summary)->toHaveCount(2);

    $ids = collect($summary)->pluck('enrollment_id')->all();
    expect($ids)->toContain($e1->id);
    expect($ids)->toContain($e2->id);

    foreach ($summary as $row) {
        expect($row)->toHaveKey('attended');
        expect($row)->toHaveKey('applicable');
        expect($row)->toHaveKey('percentage');
        expect($row)->toHaveKey('is_complete');
    }
});

test('batchSummary includes program-level enrollments with no batch pointer', function () {
    $e = DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'batch_id' => null,
        'status' => 'enrolled',
        'created_by' => $this->staff->id,
    ]);

    $summary = $this->service->batchSummary($this->batch);

    expect($summary)->toHaveCount(1);
    expect($summary[0]['enrollment_id'])->toBe($e->id);
    expect($summary[0]['applicable'])->toBe(0);
});

// ----------------------------------------------------------------
// programOverview()
// ----------------------------------------------------------------

test('programOverview counts enrollments by status', function () {
    DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'status' => 'completed',
        'created_by' => $this->staff->id,
    ]);

    foreach (['enrolled', 'enrolled', 'in_progress', 'in_progress', 'withdrawn'] as $status) {
        $p = Participant::create(['pool' => 'church',
            'first_name' => 'P',
            'last_name' => 'X'.uniqid(),
            'email' => 'p.'.uniqid().'@example.com',
        ]);
        DiscipleshipEnrollment::create([
            'participant_id' => $p->id,
            'program_id' => $this->program->id,
            'status' => $status,
            'created_by' => $this->staff->id,
        ]);
    }

    $overview = $this->service->programOverview($this->program);

    expect($overview['total'])->toBe(6);
    expect($overview['completed'])->toBe(1);
    expect($overview['in_progress'])->toBe(2);
    expect($overview['not_started'])->toBe(2);
    expect($overview['withdrawn'])->toBe(1);
    expect($overview['on_hold'])->toBe(0);
    expect($overview['completion_rate'])->toBe(16.7);
});

test('programOverview returns null completion rate when the program has no enrollments', function () {
    $overview = $this->service->programOverview($this->program);

    expect($overview['total'])->toBe(0);
    expect($overview['completion_rate'])->toBeNull();
});
