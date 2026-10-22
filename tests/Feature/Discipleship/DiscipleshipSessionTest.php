<?php

use App\Models\DiscipleshipBatch;
use App\Models\DiscipleshipProgram;
use App\Models\DiscipleshipSession;
use App\Models\User;
use Database\Seeders\DiscipleshipProgramSeeder;

/**
 * Phase 5D: session CRUD on a Discipleship batch.
 *
 * Fixtures via beforeEach only -- no file-scope helper functions.
 *
 * beforeEach creates:
 *   - $this->staff     authenticated user
 *   - $this->program   seeded "life-class" program
 *   - $this->otherProgram seeded "encounter-god-retreat"
 *   - $this->batch     one batch on $this->program
 *   - $this->otherBatch one batch on $this->otherProgram
 *   - $this->session   one session on $this->batch
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
        'topic' => 'Existing Session',
        'created_by' => $this->staff->id,
    ]);
});

// ----------------------------------------------------------------
// Store
// ----------------------------------------------------------------

test('a session can be created on a batch', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.sessions.store', [
            'batch' => $this->batch->id,
        ]), [
            'session_date' => now()->addWeeks(2)->toDateString(),
            'topic' => 'Week 1 — Welcome',
            'notes' => 'Bring your Bible.',
        ])
        ->assertRedirect(route('discipleship.batches.show', [
            'batch' => $this->batch->id,
        ]));

    expect(DiscipleshipSession::count())->toBe(2);

    $created = DiscipleshipSession::where('topic', 'Week 1 — Welcome')->firstOrFail();
    expect($created->batch_id)->toBe($this->batch->id);
    expect($created->created_by)->toBe($this->staff->id);
});

test('session creation requires a date', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.sessions.store', [
            'batch' => $this->batch->id,
        ]), [
            'topic' => 'No Date',
        ])
        ->assertSessionHasErrors('session_date');

    expect(DiscipleshipSession::count())->toBe(1);
});

test('session creation requires a topic', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.sessions.store', [
            'batch' => $this->batch->id,
        ]), [
            'session_date' => now()->addWeeks(2)->toDateString(),
        ])
        ->assertSessionHasErrors('topic');

    expect(DiscipleshipSession::count())->toBe(1);
});

test('session creation rejects a date more than 2 years out', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.sessions.store', [
            'batch' => $this->batch->id,
        ]), [
            'session_date' => now()->addYears(3)->toDateString(),
            'topic' => 'Too Far',
        ])
        ->assertSessionHasErrors('session_date');
});

test('session creation rejects a past scheduling date', function () {
    $this->actingAs($this->staff)
        ->post(route('discipleship.sessions.store', [
            'batch' => $this->batch->id,
        ]), [
            'session_date' => now()->subMonths(2)->toDateString(),
            'topic' => 'Past Session',
        ])
        ->assertSessionHasErrors('session_date');

    expect(DiscipleshipSession::count())->toBe(1);
});

test('two sessions on the same batch can share a date', function () {
    $date = now()->addWeeks(2)->toDateString();

    $this->actingAs($this->staff)
        ->post(route('discipleship.sessions.store', [
            'batch' => $this->batch->id,
        ]), [
            'session_date' => $date,
            'topic' => 'Morning Session',
        ])
        ->assertRedirect();

    $this->actingAs($this->staff)
        ->post(route('discipleship.sessions.store', [
            'batch' => $this->batch->id,
        ]), [
            'session_date' => $date,
            'topic' => 'Evening Session',
        ])
        ->assertRedirect();

    expect(DiscipleshipSession::count())->toBe(3);
});

// ----------------------------------------------------------------
// Update
// ----------------------------------------------------------------

test('a session topic can be renamed', function () {
    $this->actingAs($this->staff)
        ->put(route('discipleship.sessions.update', [
            'session' => $this->session->id,
        ]), [
            'session_date' => $this->session->session_date->toDateString(),
            'topic' => 'Renamed Session',
        ])
        ->assertRedirect(route('discipleship.batches.show', [
            'batch' => $this->batch->id,
        ]));

    expect($this->session->fresh()->topic)->toBe('Renamed Session');
});

test('a session date can be changed', function () {
    $newDate = now()->addMonths(2)->toDateString();

    $this->actingAs($this->staff)
        ->put(route('discipleship.sessions.update', [
            'session' => $this->session->id,
        ]), [
            'session_date' => $newDate,
            'topic' => $this->session->topic,
        ])
        ->assertRedirect();

    expect($this->session->fresh()->session_date->toDateString())->toBe($newDate);
});

test('session update rejects a date more than 2 years out', function () {
    $this->actingAs($this->staff)
        ->put(route('discipleship.sessions.update', [
            'session' => $this->session->id,
        ]), [
            'session_date' => now()->addYears(3)->toDateString(),
            'topic' => $this->session->topic,
        ])
        ->assertSessionHasErrors('session_date');
});

// ----------------------------------------------------------------
// Destroy
// ----------------------------------------------------------------

test('a session can be deleted', function () {
    $this->actingAs($this->staff)
        ->delete(route('discipleship.sessions.destroy', [
            'session' => $this->session->id,
        ]))
        ->assertRedirect(route('discipleship.batches.show', [
            'batch' => $this->batch->id,
        ]));

    expect(DiscipleshipSession::count())->toBe(0);
});

// ----------------------------------------------------------------
// Cascade
// ----------------------------------------------------------------

test('deleting a batch cascades to its sessions', function () {
    DiscipleshipSession::create([
        'batch_id' => $this->batch->id,
        'session_date' => now()->addWeeks(2)->toDateString(),
        'topic' => 'Second Session',
        'created_by' => $this->staff->id,
    ]);

    expect(DiscipleshipSession::count())->toBe(2);

    $this->batch->delete();

    expect(DiscipleshipSession::count())->toBe(0);
});

// ----------------------------------------------------------------
// Ordering
// ----------------------------------------------------------------

test('sessions are ordered by date ascending', function () {
    DiscipleshipSession::where('id', $this->session->id)->update([
        'session_date' => now()->addWeeks(3)->toDateString(),
    ]);

    DiscipleshipSession::create([
        'batch_id' => $this->batch->id,
        'session_date' => now()->addWeeks(1)->toDateString(),
        'topic' => 'First Session',
        'created_by' => $this->staff->id,
    ]);

    $this->actingAs($this->staff)
        ->get(route('discipleship.batches.show', ['batch' => $this->batch->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sessions', 2)
            ->where('sessions.0.topic', 'First Session')
            ->where('sessions.1.topic', 'Existing Session')
        );
});

// ----------------------------------------------------------------
// Auth
// ----------------------------------------------------------------

test('guests cannot create a session', function () {
    $this->post(route('discipleship.sessions.store', [
        'batch' => $this->batch->id,
    ]), [
        'session_date' => now()->addWeeks(2)->toDateString(),
        'topic' => 'Guest Attempt',
    ])->assertRedirect(route('login'));

    expect(DiscipleshipSession::where('topic', 'Guest Attempt')->exists())->toBeFalse();
});
