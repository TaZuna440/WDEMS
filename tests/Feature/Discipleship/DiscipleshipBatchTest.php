<?php

use App\Models\DiscipleshipBatch;
use App\Models\DiscipleshipProgram;
use App\Models\User;
use Database\Seeders\DiscipleshipProgramSeeder;

/**
 * Phase 5B: batch CRUD on a Discipleship program.
 *
 * Fixtures come from beforeEach only -- no file-scope helper
 * functions. Pest loads every test file into one PHP process; a
 * global function collides with same-name declarations in other
 * test files. Phase 4 paid that tax once already.
 *
 * The program is seeded via DiscipleshipProgramSeeder (the same
 * code path production uses); the batch is created inline so each
 * test operates on a known fixture.
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
        'name' => 'Existing Batch',
        'start_date' => now()->addWeek()->toDateString(),
        'created_by' => $this->staff->id,
    ]);
});

// ----------------------------------------------------------------
// Store
// ----------------------------------------------------------------

test('a batch can be created on a program', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.batches.store', [
            'program' => $this->program->slug,
        ]), [
            'name' => 'Spring 2026 Cohort',
            'start_date' => now()->addMonth()->toDateString(),
        ])
        ->assertRedirect(route('discipleship.programs.show', [
            'program' => $this->program->slug,
        ]));

    expect(DiscipleshipBatch::count())->toBe(2);

    $created = DiscipleshipBatch::where('name', 'Spring 2026 Cohort')->firstOrFail();
    expect($created->program_id)->toBe($this->program->id);
    expect($created->created_by)->toBe($this->staff->id);
});

test('batch creation requires a name', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.batches.store', [
            'program' => $this->program->slug,
        ]), [
            'start_date' => now()->addMonth()->toDateString(),
        ])
        ->assertSessionHasErrors('name');

    expect(DiscipleshipBatch::count())->toBe(1);
});

test('batch creation requires a start date', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.batches.store', [
            'program' => $this->program->slug,
        ]), [
            'name' => 'Incomplete Batch',
        ])
        ->assertSessionHasErrors('start_date');

    expect(DiscipleshipBatch::count())->toBe(1);
});

test('batch creation rejects a start date more than 2 years out', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.batches.store', [
            'program' => $this->program->slug,
        ]), [
            'name' => 'Too Far Batch',
            'start_date' => now()->addYears(3)->toDateString(),
        ])
        ->assertSessionHasErrors('start_date');
});

test('batch creation allows a past start date for retroactive logging', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.batches.store', [
            'program' => $this->program->slug,
        ]), [
            'name' => 'Past Cohort',
            'start_date' => now()->subMonths(6)->toDateString(),
        ])
        ->assertRedirect();

    expect(DiscipleshipBatch::count())->toBe(2);
});

test('two batches on the same program can share a name', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.batches.store', [
            'program' => $this->program->slug,
        ]), [
            'name' => 'Existing Batch',
            'start_date' => now()->addYear()->toDateString(),
        ])
        ->assertRedirect();

    expect(DiscipleshipBatch::where('name', 'Existing Batch')->count())->toBe(2);
});

// ----------------------------------------------------------------
// Update
// ----------------------------------------------------------------

test('a batch can be renamed', function () {
    $this->actingAs($this->staff)
        ->put(route('discipleship.batches.update', [
            'batch' => $this->batch->id,
        ]), [
            'name' => 'Renamed Batch',
            'start_date' => $this->batch->start_date->toDateString(),
        ])
        ->assertRedirect(route('discipleship.programs.show', [
            'program' => $this->program->slug,
        ]));

    expect($this->batch->fresh()->name)->toBe('Renamed Batch');
});

test('a batch start date can be changed', function () {
    $newDate = now()->addMonths(3)->toDateString();

    $this->actingAs($this->staff)
        ->put(route('discipleship.batches.update', [
            'batch' => $this->batch->id,
        ]), [
            'name' => $this->batch->name,
            'start_date' => $newDate,
        ])
        ->assertRedirect();

    expect($this->batch->fresh()->start_date->toDateString())->toBe($newDate);
});

test('batch update rejects a start date more than 2 years out', function () {
    $this->actingAs($this->staff)
        ->put(route('discipleship.batches.update', [
            'batch' => $this->batch->id,
        ]), [
            'name' => $this->batch->name,
            'start_date' => now()->addYears(3)->toDateString(),
        ])
        ->assertSessionHasErrors('start_date');
});

// ----------------------------------------------------------------
// Destroy
// ----------------------------------------------------------------

test('a batch can be deleted', function () {
    $this->actingAs($this->staff)
        ->delete(route('discipleship.batches.destroy', [
            'batch' => $this->batch->id,
        ]))
        ->assertRedirect(route('discipleship.programs.show', [
            'program' => $this->program->slug,
        ]));

    expect(DiscipleshipBatch::count())->toBe(0);
});

// ----------------------------------------------------------------
// Cascade
// ----------------------------------------------------------------

test('deleting a program cascades to its batches', function () {
    DiscipleshipBatch::create([
        'program_id' => $this->program->id,
        'name' => 'Second Batch',
        'start_date' => now()->addMonths(2)->toDateString(),
        'created_by' => $this->staff->id,
    ]);

    expect(DiscipleshipBatch::count())->toBe(2);

    $this->program->delete();

    expect(DiscipleshipBatch::count())->toBe(0);
});

// ----------------------------------------------------------------
// Auth
// ----------------------------------------------------------------

test('guests cannot create a batch', function () {
    $this->post(route('discipleship.batches.store', [
        'program' => $this->program->slug,
    ]), [
        'name' => 'Guest Attempt',
        'start_date' => now()->addMonth()->toDateString(),
    ])->assertRedirect(route('login'));

    expect(DiscipleshipBatch::where('name', 'Guest Attempt')->exists())->toBeFalse();
});
