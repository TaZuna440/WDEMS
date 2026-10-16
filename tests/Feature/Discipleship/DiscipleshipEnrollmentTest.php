<?php

use App\Enums\DiscipleshipEnrollmentStatus;
use App\Models\DiscipleshipBatch;
use App\Models\DiscipleshipEnrollment;
use App\Models\DiscipleshipProgram;
use App\Models\Participant;
use App\Models\User;
use Database\Seeders\DiscipleshipProgramSeeder;

/**
 * Phase 5C: enrollment CRUD, withdraw-as-status, and the
 * participant-search endpoint.
 *
 * Fixtures via beforeEach only -- no file-scope helper functions.
 * Phase 4 paid the collision tax once; not repeating.
 *
 * beforeEach creates:
 *   - $this->staff        an authenticated user
 *   - $this->program      the seeded "life-class" program
 *   - $this->otherProgram the seeded "encounter-god-retreat" (for
 *                         cross-program batch tests)
 *   - $this->batch        one batch on $this->program
 *   - $this->otherBatch   one batch on $this->otherProgram
 *   - $this->participant  a bare participant, not yet enrolled
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
        'name' => 'Different Program Batch',
        'start_date' => now()->addWeek()->toDateString(),
        'created_by' => $this->staff->id,
    ]);

    $this->participant = Participant::create([
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'email' => 'juan.'.uniqid().'@example.com',
    ]);
});

// ----------------------------------------------------------------
// Store
// ----------------------------------------------------------------

test('an enrollment can be created on a program', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.enrollments.store', [
            'program' => $this->program->slug,
        ]), [
            'participant_id' => $this->participant->id,
            'batch_id' => $this->batch->id,
        ])
        ->assertRedirect(route('discipleship.programs.show', [
            'program' => $this->program->slug,
        ]));

    expect(DiscipleshipEnrollment::count())->toBe(1);

    $enrollment = DiscipleshipEnrollment::first();
    expect($enrollment->participant_id)->toBe($this->participant->id);
    expect($enrollment->program_id)->toBe($this->program->id);
    expect($enrollment->batch_id)->toBe($this->batch->id);
    expect($enrollment->status)->toBe(DiscipleshipEnrollmentStatus::Enrolled);
    expect($enrollment->created_by)->toBe($this->staff->id);
});

test('an enrollment can be created without a batch', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.enrollments.store', [
            'program' => $this->program->slug,
        ]), [
            'participant_id' => $this->participant->id,
        ])
        ->assertRedirect();

    expect(DiscipleshipEnrollment::first()->batch_id)->toBeNull();
});

test('enrollment creation requires a participant', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.enrollments.store', [
            'program' => $this->program->slug,
        ]), [])
        ->assertSessionHasErrors('participant_id');

    expect(DiscipleshipEnrollment::count())->toBe(0);
});

test('enrollment creation rejects a non-existent participant', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.enrollments.store', [
            'program' => $this->program->slug,
        ]), [
            'participant_id' => 99999,
        ])
        ->assertSessionHasErrors('participant_id');

    expect(DiscipleshipEnrollment::count())->toBe(0);
});

test('enrollment creation accepts a batch that belongs to the same program', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.enrollments.store', [
            'program' => $this->program->slug,
        ]), [
            'participant_id' => $this->participant->id,
            'batch_id' => $this->batch->id,
        ])
        ->assertRedirect();

    expect(DiscipleshipEnrollment::first()->batch_id)->toBe($this->batch->id);
});

test('enrollment creation rejects a batch from a different program', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.enrollments.store', [
            'program' => $this->program->slug,
        ]), [
            'participant_id' => $this->participant->id,
            'batch_id' => $this->otherBatch->id,
        ])
        ->assertNotFound();

    expect(DiscipleshipEnrollment::count())->toBe(0);
});

test('enrollment creation rejects a duplicate participant on the same program', function () {
    DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'status' => DiscipleshipEnrollmentStatus::Enrolled->value,
        'created_by' => $this->staff->id,
    ]);

    $this->actingAs($this->staff)
        ->post(route('discipleship.enrollments.store', [
            'program' => $this->program->slug,
        ]), [
            'participant_id' => $this->participant->id,
        ])
        ->assertStatus(422);

    expect(DiscipleshipEnrollment::count())->toBe(1);
});

test('the same participant can be enrolled in two different programs', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.enrollments.store', [
            'program' => $this->program->slug,
        ]), ['participant_id' => $this->participant->id])
        ->assertRedirect();

    $this->actingAs($this->staff)
        ->post(route('discipleship.enrollments.store', [
            'program' => $this->otherProgram->slug,
        ]), ['participant_id' => $this->participant->id])
        ->assertRedirect();

    expect(DiscipleshipEnrollment::count())->toBe(2);
});

test('enrollment creation allows a past enrolled_at for retroactive logging', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.enrollments.store', [
            'program' => $this->program->slug,
        ]), [
            'participant_id' => $this->participant->id,
            'enrolled_at' => now()->subMonths(3)->toDateString(),
        ])
        ->assertRedirect();

    $expected = now()->subMonths(3)->toDateString();
    expect(DiscipleshipEnrollment::first()->enrolled_at->toDateString())->toBe($expected);
});

test('enrollment creation rejects a future enrolled_at', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.enrollments.store', [
            'program' => $this->program->slug,
        ]), [
            'participant_id' => $this->participant->id,
            'enrolled_at' => now()->addWeek()->toDateString(),
        ])
        ->assertSessionHasErrors('enrolled_at');
});

// ----------------------------------------------------------------
// Update
// ----------------------------------------------------------------

test('an enrollment status can be changed', function () {
    $enrollment = DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'status' => DiscipleshipEnrollmentStatus::Enrolled->value,
        'created_by' => $this->staff->id,
    ]);

    $this->actingAs($this->staff)
        ->put(route('discipleship.enrollments.update', [
            'enrollment' => $enrollment->id,
        ]), [
            'status' => DiscipleshipEnrollmentStatus::InProgress->value,
        ])
        ->assertRedirect();

    expect($enrollment->fresh()->status)->toBe(DiscipleshipEnrollmentStatus::InProgress);
});

test('an enrollment batch can be assigned', function () {
    $enrollment = DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'status' => DiscipleshipEnrollmentStatus::Enrolled->value,
        'created_by' => $this->staff->id,
    ]);

    $this->actingAs($this->staff)
        ->put(route('discipleship.enrollments.update', [
            'enrollment' => $enrollment->id,
        ]), [
            'status' => DiscipleshipEnrollmentStatus::Enrolled->value,
            'batch_id' => $this->batch->id,
        ])
        ->assertRedirect();

    expect($enrollment->fresh()->batch_id)->toBe($this->batch->id);
});

test('an enrollment batch can be cleared', function () {
    $enrollment = DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'batch_id' => $this->batch->id,
        'status' => DiscipleshipEnrollmentStatus::Enrolled->value,
        'created_by' => $this->staff->id,
    ]);

    $this->actingAs($this->staff)
        ->put(route('discipleship.enrollments.update', [
            'enrollment' => $enrollment->id,
        ]), [
            'status' => DiscipleshipEnrollmentStatus::Enrolled->value,
            'batch_id' => null,
        ])
        ->assertRedirect();

    expect($enrollment->fresh()->batch_id)->toBeNull();
});

test('enrollment update rejects an invalid status', function () {
    $enrollment = DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'status' => DiscipleshipEnrollmentStatus::Enrolled->value,
        'created_by' => $this->staff->id,
    ]);

    $this->actingAs($this->staff)
        ->put(route('discipleship.enrollments.update', [
            'enrollment' => $enrollment->id,
        ]), [
            'status' => 'invented-status',
        ])
        ->assertSessionHasErrors('status');
});

test('enrollment update rejects a batch from a different program', function () {
    $enrollment = DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'status' => DiscipleshipEnrollmentStatus::Enrolled->value,
        'created_by' => $this->staff->id,
    ]);

    $this->actingAs($this->staff)
        ->put(route('discipleship.enrollments.update', [
            'enrollment' => $enrollment->id,
        ]), [
            'status' => DiscipleshipEnrollmentStatus::Enrolled->value,
            'batch_id' => $this->otherBatch->id,
        ])
        ->assertNotFound();
});

// ----------------------------------------------------------------
// Destroy (withdraw) -- row survives
// ----------------------------------------------------------------

test('withdrawing an enrollment sets status to withdrawn and keeps the row', function () {
    $enrollment = DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'status' => DiscipleshipEnrollmentStatus::Enrolled->value,
        'created_by' => $this->staff->id,
    ]);

    $id = $enrollment->id;

    $this->actingAs($this->staff)
        ->delete(route('discipleship.enrollments.withdraw', [
            'enrollment' => $id,
        ]))
        ->assertRedirect(route('discipleship.programs.show', [
            'program' => $this->program->slug,
        ]));

    expect(DiscipleshipEnrollment::find($id))->not->toBeNull();
    expect(DiscipleshipEnrollment::find($id)->status)
        ->toBe(DiscipleshipEnrollmentStatus::Withdrawn);
    expect(DiscipleshipEnrollment::count())->toBe(1);
});

test('a withdrawn participant cannot be re-enrolled without resetting the row', function () {
    $enrollment = DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'status' => DiscipleshipEnrollmentStatus::Withdrawn->value,
        'created_by' => $this->staff->id,
    ]);

    // The unique constraint is total, not partial. A second store
    // for the same (participant, program) is rejected even though
    // the first row is withdrawn.
    $this->actingAs($this->staff)
        ->post(route('discipleship.enrollments.store', [
            'program' => $this->program->slug,
        ]), ['participant_id' => $this->participant->id])
        ->assertStatus(422);

    expect(DiscipleshipEnrollment::count())->toBe(1);
});

// ----------------------------------------------------------------
// Cascade
// ----------------------------------------------------------------

test('deleting a program cascades to its enrollments', function () {
    DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'status' => DiscipleshipEnrollmentStatus::Enrolled->value,
        'created_by' => $this->staff->id,
    ]);

    expect(DiscipleshipEnrollment::count())->toBe(1);
    $this->program->delete();
    expect(DiscipleshipEnrollment::count())->toBe(0);
});

test('deleting a participant cascades to their enrollments', function () {
    DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'status' => DiscipleshipEnrollmentStatus::Enrolled->value,
        'created_by' => $this->staff->id,
    ]);

    expect(DiscipleshipEnrollment::count())->toBe(1);
    $this->participant->delete();
    expect(DiscipleshipEnrollment::count())->toBe(0);
});

test('deleting a batch nulls the batch pointer on its enrollments', function () {
    $enrollment = DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'batch_id' => $this->batch->id,
        'status' => DiscipleshipEnrollmentStatus::Enrolled->value,
        'created_by' => $this->staff->id,
    ]);

    $this->batch->delete();

    expect(DiscipleshipEnrollment::find($enrollment->id))->not->toBeNull();
    expect(DiscipleshipEnrollment::find($enrollment->id)->batch_id)->toBeNull();
});

// ----------------------------------------------------------------
// Participant search
// ----------------------------------------------------------------

test('participant search returns matching participants as JSON', function () {
    $response = $this->actingAs($this->staff)
        ->getJson('/events/church/discipleship/programs/'.$this->program->slug.'/participant-search?q=Juan');

    $response->assertOk();
    $response->assertJsonPath('participants.0.id', $this->participant->id);
    $response->assertJsonPath('participants.0.already_enrolled', false);
});

test('participant search marks already-enrolled participants', function () {
    DiscipleshipEnrollment::create([
        'participant_id' => $this->participant->id,
        'program_id' => $this->program->id,
        'status' => DiscipleshipEnrollmentStatus::Enrolled->value,
        'created_by' => $this->staff->id,
    ]);

    $response = $this->actingAs($this->staff)
        ->getJson('/events/church/discipleship/programs/'.$this->program->slug.'/participant-search?q=Juan');

    $response->assertOk();
    $response->assertJsonPath('participants.0.already_enrolled', true);
});

test('participant search with an empty query returns up to 20 results', function () {
    for ($i = 0; $i < 25; $i++) {
        Participant::create([
            'first_name' => 'Filler',
            'last_name' => 'Person'.$i,
            'email' => 'filler-'.$i.'-'.uniqid().'@example.com',
        ]);
    }

    $response = $this->actingAs($this->staff)
        ->getJson('/events/church/discipleship/programs/'.$this->program->slug.'/participant-search');

    $response->assertOk();
    $response->assertJsonCount(20, 'participants');
});

// ----------------------------------------------------------------
// Auth
// ----------------------------------------------------------------

test('guests cannot create an enrollment', function () {
    $this->post(route('discipleship.enrollments.store', [
        'program' => $this->program->slug,
    ]), [
        'participant_id' => $this->participant->id,
    ])->assertRedirect(route('login'));

    expect(DiscipleshipEnrollment::count())->toBe(0);
});
