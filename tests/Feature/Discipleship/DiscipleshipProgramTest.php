<?php

use App\Models\DiscipleshipProgram;
use App\Models\User;
use Database\Seeders\DiscipleshipProgramSeeder;

/**
 * Phase 5A: the Discipleship programs landing and per-program view.
 *
 * Unlike the Services and Fellowship test files, which create
 * their fixtures with inline ::create() calls, this file seeds the
 * six programs via DiscipleshipProgramSeeder in beforeEach. The
 * programs are seeded data in production too -- no create UI
 * exists -- so the tests exercise the same code path the app uses.
 *
 * No helper functions declared at file scope: Pest loads every
 * test file into one PHP process, and a global function name
 * collides with any other test file that declares the same. The
 * Phase 4 session paid that tax once already; this file uses
 * beforeEach-bound state only.
 */
beforeEach(function () {
    $this->staff = User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
    ]);

    $this->seed(DiscipleshipProgramSeeder::class);
});

// ----------------------------------------------------------------
// Landing
// ----------------------------------------------------------------

test('the discipleship landing renders six programs', function () {
    $this->actingAs($this->staff)
        ->get(route('discipleship.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('discipleship/index')
            ->has('programs', 6)
        );
});

test('programs render in display order', function () {
    $this->actingAs($this->staff)
        ->get(route('discipleship.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('programs.0.slug', 'life-class')
            ->where('programs.1.slug', 'encounter-god-retreat')
            ->where('programs.2.slug', 'school-of-leaders-1')
            ->where('programs.3.slug', 'school-of-leaders-2')
            ->where('programs.4.slug', 'school-of-leaders-3')
            ->where('programs.5.slug', 'cell-convergence')
        );
});

// ----------------------------------------------------------------
// Per-program view
// ----------------------------------------------------------------

test('the per-program view binds by slug', function () {
    $this->actingAs($this->staff)
        ->get(route('discipleship.programs.show', ['program' => 'life-class']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('discipleship/show')
            ->where('program.slug', 'life-class')
            ->where('program.name', 'Life Class')
        );
});

test('the per-program view returns 404 for an unknown slug', function () {
    $this->actingAs($this->staff)
        ->get('/discipleship/programs/not-a-real-program')
        ->assertNotFound();
});

// ----------------------------------------------------------------
// Seeder idempotency
// ----------------------------------------------------------------

test('the seeder is idempotent -- a second run does not duplicate programs', function () {
    $this->seed(DiscipleshipProgramSeeder::class);

    expect(DiscipleshipProgram::count())->toBe(6);
});

// ----------------------------------------------------------------
// Auth
// ----------------------------------------------------------------

test('guests are redirected to login from the discipleship landing', function () {
    $this->get(route('discipleship.index'))->assertRedirect(route('login'));
});
