<?php

use App\Models\DiscipleshipAttendance;
use App\Models\DiscipleshipBatch;
use App\Models\DiscipleshipEnrollment;
use App\Models\DiscipleshipProgram;
use App\Models\DiscipleshipSession;
use App\Models\Participant;
use App\Models\User;
use Database\Seeders\DiscipleshipProgramSeeder;

/**
 * Phase 5E: bulk attendance upsert and single-cell clear.
 *
 * Fixtures via beforeEach only.
 *
 * beforeEach creates:
 *   - $this->staff        authenticated user
 *   - $this->program      seeded "life-class"
 *   - $this->otherProgram seeded "encounter-god-retreat"
 *   - $this->batch        one batch on $this->program
 *   - $this->otherBatch   one batch on $this->otherProgram
 *   - $this->session      first session on $this->batch
 *   - $this->session2     second session on $this->batch
 *   - $this->foreignSession session on $this->otherBatch
 *   - $this->participant  a bare participant
 *   - $this->enrollment   enrollment on $this->program, batch pointer = $this->batch
 */
beforeEach(function () {
    $this->staff = User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
    ]);

    $this->seed(DiscipleshipProgramSeeder::class);

    $this->program = DiscipleshipProgram::where('slug', 'life-class')
        ->firstOrFail();

    $this->otherProgram = DiscipleshipProgram::where('slug', 'encounter-god-retreat')
        ->firstOrFail();

    $this->batch = DiscipleshipBatch::create([
        'program_id' => $this->program->id,
        'name' => 'Spring 2026 Cohort',
        'start_date' => now()->addWeek()->toDateString(),
        'created_by' => $this->staff->id,
    ]);

    $this->otherBatch = DiscipleshipBatch::create([
        'program_id' => $this->otherProgram->id,
        'name' => 'Other Batch',
        'start_date' => now()->addWeek()->toDateString(),
        'created_by' => $this->staff->id,
    ]);

    $this->session = DiscipleshipSession::create([
        'batch_id' => $this->batch->id,
        'session_date' => now()->addWeek()->toDateString(),
        'topic' => 'Session 1',
        'created_by' => $this->staff->id,
    ]);

    $this->session2 = DiscipleshipSession::create([
        'batch_id' => $this->batch->id,
        'session_date' => now()->addWeeks(2)->toDateString(),
        'topic' => 'Session 2',
        'created_by' => $this->staff->id,
    ]);

    $this->foreignSession = DiscipleshipSession::create([
        'batch_id' => $this->otherBatch->id,
        'session_date' => now()->addWeek()->toDateString(),
        'topic' => 'Foreign Session',
        'created_by' => $this->staff->id,
    ]);

    $this->participant = Participant::create([
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'email' => 'juan.'.uniqid().'@example.com',
    ]);

    $this->enrollment = DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'batch_id' => $this->batch->id,
        'status' => 'enrolled',
        'created_by' => $this->staff->id,
    ]);
});

// ----------------------------------------------------------------
// Bulk upsert
// ----------------------------------------------------------------

test('bulk upsert creates one attendance cell', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.attendances.bulk', [
            'batch' => $this->batch->id,
        ]), [
            'cells' => [
                [
                    'enrollment_id' => $this->enrollment->id,
                    'session_id' => $this->session->id,
                    'status' => 'present',
                ],
            ],
        ])
        ->assertRedirect(route('discipleship.batches.show', [
            'batch' => $this->batch->id,
        ]));

    expect(DiscipleshipAttendance::count())->toBe(1);

    $cell = DiscipleshipAttendance::first();
    expect($cell->enrollment_id)->toBe($this->enrollment->id);
    expect($cell->session_id)->toBe($this->session->id);
    expect($cell->status->value)->toBe('present');
    expect($cell->recorded_by)->toBe($this->staff->id);
});

test('bulk upsert creates multiple cells in one request', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.attendances.bulk', [
            'batch' => $this->batch->id,
        ]), [
            'cells' => [
                [
                    'enrollment_id' => $this->enrollment->id,
                    'session_id' => $this->session->id,
                    'status' => 'present',
                ],
                [
                    'enrollment_id' => $this->enrollment->id,
                    'session_id' => $this->session2->id,
                    'status' => 'absent',
                ],
            ],
        ])
        ->assertRedirect();

    expect(DiscipleshipAttendance::count())->toBe(2);
});

test('bulk upsert is idempotent — same payload twice does not duplicate', function () {
    $payload = [
        'cells' => [
            [
                'enrollment_id' => $this->enrollment->id,
                'session_id' => $this->session->id,
                'status' => 'present',
            ],
        ],
    ];

    $this->actingAs($this->staff)
        ->post(route('discipleship.attendances.bulk', ['batch' => $this->batch->id]), $payload);

    $this->actingAs($this->staff)
        ->post(route('discipleship.attendances.bulk', ['batch' => $this->batch->id]), $payload);

    expect(DiscipleshipAttendance::count())->toBe(1);
});

test('bulk upsert updates an existing cell status', function () {
    DiscipleshipAttendance::create([
        'enrollment_id' => $this->enrollment->id,
        'session_id' => $this->session->id,
        'status' => 'present',
        'recorded_by' => $this->staff->id,
    ]);

    $this->actingAs($this->staff)
        ->post(route('discipleship.attendances.bulk', [
            'batch' => $this->batch->id,
        ]), [
            'cells' => [
                [
                    'enrollment_id' => $this->enrollment->id,
                    'session_id' => $this->session->id,
                    'status' => 'excused',
                ],
            ],
        ])
        ->assertRedirect();

    expect(DiscipleshipAttendance::count())->toBe(1);
    expect(DiscipleshipAttendance::first()->status->value)->toBe('excused');
});

// ----------------------------------------------------------------
// Cross-batch guards
// ----------------------------------------------------------------

test('bulk upsert rejects a session that belongs to a different batch', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.attendances.bulk', [
            'batch' => $this->batch->id,
        ]), [
            'cells' => [
                [
                    'enrollment_id' => $this->enrollment->id,
                    'session_id' => $this->foreignSession->id,
                    'status' => 'present',
                ],
            ],
        ])
        ->assertNotFound();

    expect(DiscipleshipAttendance::count())->toBe(0);
});

test('bulk upsert rejects an enrollment from a different program', function () {
    $foreignParticipant = Participant::create([
        'first_name' => 'Foreign',
        'last_name' => 'Enrollee',
        'email' => 'foreign.'.uniqid().'@example.com',
    ]);

    $foreignEnrollment = DiscipleshipEnrollment::create([
        'participant_id' => $foreignParticipant->id,
        'program_id' => $this->otherProgram->id,
        'status' => 'enrolled',
        'created_by' => $this->staff->id,
    ]);

    $this->actingAs($this->staff)
        ->post(route('discipleship.attendances.bulk', [
            'batch' => $this->batch->id,
        ]), [
            'cells' => [
                [
                    'enrollment_id' => $foreignEnrollment->id,
                    'session_id' => $this->session->id,
                    'status' => 'present',
                ],
            ],
        ])
        ->assertNotFound();

    expect(DiscipleshipAttendance::count())->toBe(0);
});

test('bulk upsert accepts a program-level enrollment with a null batch pointer', function () {
    $unassignedParticipant = Participant::create([
        'first_name' => 'Unassigned',
        'last_name' => 'Enrollee',
        'email' => 'unassigned.'.uniqid().'@example.com',
    ]);

    $unassignedEnrollment = DiscipleshipEnrollment::create([
        'participant_id' => $unassignedParticipant->id,
        'program_id' => $this->program->id,
        'batch_id' => null,
        'status' => 'enrolled',
        'created_by' => $this->staff->id,
    ]);

    $this->actingAs($this->staff)
        ->post(route('discipleship.attendances.bulk', [
            'batch' => $this->batch->id,
        ]), [
            'cells' => [
                [
                    'enrollment_id' => $unassignedEnrollment->id,
                    'session_id' => $this->session->id,
                    'status' => 'present',
                ],
            ],
        ])
        ->assertRedirect();

    expect(DiscipleshipAttendance::count())->toBe(1);
});

// ----------------------------------------------------------------
// Validation
// ----------------------------------------------------------------

test('bulk upsert rejects an empty cells array', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.attendances.bulk', [
            'batch' => $this->batch->id,
        ]), [
            'cells' => [],
        ])
        ->assertSessionHasErrors('cells');

    expect(DiscipleshipAttendance::count())->toBe(0);
});

test('bulk upsert rejects an invalid status value', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.attendances.bulk', [
            'batch' => $this->batch->id,
        ]), [
            'cells' => [
                [
                    'enrollment_id' => $this->enrollment->id,
                    'session_id' => $this->session->id,
                    'status' => 'invented-status',
                ],
            ],
        ])
        ->assertSessionHasErrors('cells.0.status');
});

// ----------------------------------------------------------------
// Destroy (single cell clear)
// ----------------------------------------------------------------

test('an attendance cell can be cleared', function () {
    $cell = DiscipleshipAttendance::create([
        'enrollment_id' => $this->enrollment->id,
        'session_id' => $this->session->id,
        'status' => 'present',
        'recorded_by' => $this->staff->id,
    ]);

    $this->actingAs($this->staff)
        ->delete(route('discipleship.attendances.destroy', [
            'attendance' => $cell->id,
        ]))
        ->assertRedirect(route('discipleship.batches.show', [
            'batch' => $this->batch->id,
        ]));

    expect(DiscipleshipAttendance::count())->toBe(0);
});

// ----------------------------------------------------------------
// Cascade
// ----------------------------------------------------------------

test('deleting an enrollment cascades to its attendance rows', function () {
    DiscipleshipAttendance::create([
        'enrollment_id' => $this->enrollment->id,
        'session_id' => $this->session->id,
        'status' => 'present',
        'recorded_by' => $this->staff->id,
    ]);

    expect(DiscipleshipAttendance::count())->toBe(1);

    $this->enrollment->delete();

    expect(DiscipleshipAttendance::count())->toBe(0);
});

test('deleting a session cascades to its attendance rows', function () {
    DiscipleshipAttendance::create([
        'enrollment_id' => $this->enrollment->id,
        'session_id' => $this->session->id,
        'status' => 'present',
        'recorded_by' => $this->staff->id,
    ]);

    expect(DiscipleshipAttendance::count())->toBe(1);

    $this->session->delete();

    expect(DiscipleshipAttendance::count())->toBe(0);
});

// ----------------------------------------------------------------
// Auth
// ----------------------------------------------------------------

test('guests cannot bulk-upsert attendance', function () {
    $this->post(route('discipleship.attendances.bulk', [
        'batch' => $this->batch->id,
    ]), [
        'cells' => [
            [
                'enrollment_id' => $this->enrollment->id,
                'session_id' => $this->session->id,
                'status' => 'present',
            ],
        ],
    ])->assertRedirect(route('login'));

    expect(DiscipleshipAttendance::count())->toBe(0);
});
