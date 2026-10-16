<?php

namespace App\Http\Controllers;

use App\Models\DiscipleshipProgram;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Discipleship module -- landing and per-program view.
 *
 * Programs are seeded, not created through the UI. There is no
 * create, update, or destroy path for programs. If the client
 * later adds a program, the change point is
 * DiscipleshipProgramSeeder.
 *
 * Phase 5A. Batches (5B), enrollment (5C), sessions (5D),
 * attendance (5E), and progress/completion (5F) extend this
 * controller or add new ones in later sub-phases.
 */
class DiscipleshipController extends Controller
{
    /**
     * Landing: program cards.
     *
     * Shows each of the seeded programs in display order. The
     * card links to the per-program view.
     */
    public function index(): Response
    {
        $programs = DiscipleshipProgram::query()
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(fn (DiscipleshipProgram $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'description' => $p->description,
            ]);

        return Inertia::render('discipleship/index', [
            'programs' => $programs,
        ]);
    }

    /**
     * Per-program view.
     *
     * At 5A the payload is only the program. 5B adds the
     * program's batches to the payload alongside the batch model
     * and routes.
     */
    public function show(DiscipleshipProgram $program): Response
    {
        return Inertia::render('discipleship/show', [
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
                'slug' => $program->slug,
                'description' => $program->description,
            ],
        ]);
    }
}
