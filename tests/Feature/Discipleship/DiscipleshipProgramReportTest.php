<?php

use App\Models\DiscipleshipBatch;
use App\Models\DiscipleshipEnrollment;
use App\Models\DiscipleshipProgram;
use App\Models\Participant;
use App\Models\User;
use Database\Seeders\DiscipleshipProgramSeeder;

/**
 * Phase 5G: search, filter, and CSV export on the program show page.
 *
 * Fixtures via beforeEach only. Creates a program, one batch on it,
 * and six enrollments across three statuses so both the counts and
 * the filtered lists have something to assert against.
 *
 * No file-scope helper functions -- Pest loads every test file
 * into one process; global names collide. The fixture helper is a
 * local closure inside beforeEach, which is the only scope where
 * $this resolves to the test case.
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

    $createEnrollment = function (
        string $first,
        string $last,
        string $email,
        string $status,
        ?int $batchId,
    ): DiscipleshipEnrollment {
        $participant = Participant::create(['pool' => 'church',
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
        ]);

        return DiscipleshipEnrollment::create([
            'participant_id' => $participant->id,
            'program_id' => $this->program->id,
            'batch_id' => $batchId,
            'status' => $status,
            'enrolled_at' => now()->subMonth(),
            'created_by' => $this->staff->id,
        ]);
    };

    // Six enrollments:
    //   - 3 enrolled (2 assigned to batch, 1 unassigned)
    //   - 2 completed (1 assigned to batch, 1 unassigned)
    //   - 1 withdrawn (assigned to batch)
    $this->enrollments = [
        $createEnrollment('Juan', 'Dela Cruz', 'juan@example.com', 'enrolled', $this->batch->id),
        $createEnrollment('Maria', 'Santos', 'maria@example.com', 'enrolled', $this->batch->id),
        $createEnrollment('Pedro', 'Reyes', 'pedro@example.com', 'enrolled', null),
        $createEnrollment('Ana', 'Lopez', 'ana@example.com', 'completed', $this->batch->id),
        $createEnrollment('Carlos', 'Ramos', 'carlos@example.com', 'completed', null),
        $createEnrollment('Elena', 'Cruz', 'elena@example.com', 'withdrawn', $this->batch->id),
    ];
});

// ----------------------------------------------------------------
// Search
// ----------------------------------------------------------------

test('search by first name filters the enrollment list', function () {
    $this->actingAs($this->staff)
        ->get(route('discipleship.programs.show', [
            'program' => $this->program->slug,
            'q' => 'Juan',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('enrollments', 1)
            ->where('enrollments.0.full_name', 'Juan Dela Cruz')
        );
});

test('search by last name filters the enrollment list', function () {
    $this->actingAs($this->staff)
        ->get(route('discipleship.programs.show', [
            'program' => $this->program->slug,
            'q' => 'Santos',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('enrollments', 1)
            ->where('enrollments.0.full_name', 'Maria Santos')
        );
});

test('search by email filters the enrollment list', function () {
    $this->actingAs($this->staff)
        ->get(route('discipleship.programs.show', [
            'program' => $this->program->slug,
            'q' => 'pedro@example.com',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('enrollments', 1)
            ->where('enrollments.0.full_name', 'Pedro Reyes')
        );
});

// ----------------------------------------------------------------
// Status filter
// ----------------------------------------------------------------

test('status filter shows only enrollments with the matching status', function () {
    $this->actingAs($this->staff)
        ->get(route('discipleship.programs.show', [
            'program' => $this->program->slug,
            'status' => 'completed',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('enrollments', 2)
            ->where('filters.status', 'completed')
        );
});

test('status=all returns every enrollment', function () {
    $this->actingAs($this->staff)
        ->get(route('discipleship.programs.show', [
            'program' => $this->program->slug,
            'status' => 'all',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('enrollments', 6)
        );
});

// ----------------------------------------------------------------
// Batch filter
// ----------------------------------------------------------------

test('batch filter shows only enrollments on that batch', function () {
    $this->actingAs($this->staff)
        ->get(route('discipleship.programs.show', [
            'program' => $this->program->slug,
            'batch' => (string) $this->batch->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('enrollments', 4)
        );
});

test('batch=unassigned shows only enrollments with a null batch', function () {
    $this->actingAs($this->staff)
        ->get(route('discipleship.programs.show', [
            'program' => $this->program->slug,
            'batch' => 'unassigned',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('enrollments', 2)
        );
});

// ----------------------------------------------------------------
// Combined filters
// ----------------------------------------------------------------

test('combined search and status filters intersect', function () {
    // "a" matches Maria, Ana, Carlos, Elena. Completed narrows to Ana, Carlos.
    $this->actingAs($this->staff)
        ->get(route('discipleship.programs.show', [
            'program' => $this->program->slug,
            'q' => 'a',
            'status' => 'completed',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('enrollments', 2)
        );
});

// ----------------------------------------------------------------
// Counts
// ----------------------------------------------------------------

test('counts reflect the current search scope, ignoring the status filter', function () {
    $this->actingAs($this->staff)
        ->get(route('discipleship.programs.show', [
            'program' => $this->program->slug,
            'status' => 'completed',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('counts.all', 6)
            ->where('counts.enrolled', 3)
            ->where('counts.completed', 2)
            ->where('counts.withdrawn', 1)
            ->has('enrollments', 2)
        );
});

test('counts narrow when a search is applied', function () {
    $this->actingAs($this->staff)
        ->get(route('discipleship.programs.show', [
            'program' => $this->program->slug,
            'q' => 'Maria',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('counts.all', 1)
            ->where('counts.enrolled', 1)
            ->where('counts.completed', 0)
        );
});

// ----------------------------------------------------------------
// CSV export
// ----------------------------------------------------------------

test('csv export returns a text/csv response', function () {
    $response = $this->actingAs($this->staff)
        ->get(route('discipleship.enrollments.export', [
            'program' => $this->program->slug,
        ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=utf-8');
});

test('csv export includes a header row and one row per enrollment', function () {
    $response = $this->actingAs($this->staff)
        ->get(route('discipleship.enrollments.export', [
            'program' => $this->program->slug,
        ]));

    $content = $response->streamedContent();

    expect($content)->toContain('Participant');
    expect($content)->toContain('Attended');
    expect($content)->toContain('Percentage');
    expect($content)->toContain('Juan Dela Cruz');
    expect($content)->toContain('Maria Santos');
    expect($content)->toContain('Elena Cruz');
});

test('csv export respects the status filter', function () {
    $response = $this->actingAs($this->staff)
        ->get(route('discipleship.enrollments.export', [
            'program' => $this->program->slug,
            'status' => 'completed',
        ]));

    $content = $response->streamedContent();

    expect($content)->toContain('Ana Lopez');
    expect($content)->toContain('Carlos Ramos');
    expect($content)->not->toContain('Juan Dela Cruz');
});

test('csv export respects the search filter', function () {
    $response = $this->actingAs($this->staff)
        ->get(route('discipleship.enrollments.export', [
            'program' => $this->program->slug,
            'q' => 'Santos',
        ]));

    $content = $response->streamedContent();

    expect($content)->toContain('Maria Santos');
    expect($content)->not->toContain('Juan Dela Cruz');
    expect($content)->not->toContain('Ana Lopez');
});

// ----------------------------------------------------------------
// Auth
// ----------------------------------------------------------------

test('guests cannot access the export route', function () {
    $this->get(route('discipleship.enrollments.export', [
        'program' => $this->program->slug,
    ]))->assertRedirect(route('login'));
});
