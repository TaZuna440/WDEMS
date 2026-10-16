<?php

namespace App\Http\Controllers;

use App\Models\DiscipleshipBatch;
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
 * Phase 5A. 5B added batches -- index gains batches_count per
 * card, show gains the batch list plus a batches_count.
 * Enrollment (5C), sessions (5D), attendance (5E), and progress
 * (5F) extend this in later sub-phases.
 */
class DiscipleshipController extends Controller
{
    /**
     * Landing: program cards with batch counts.
     */
    public function index(): Response
    {
        $programs = DiscipleshipProgram::query()
            ->withCount('batches')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(fn (DiscipleshipProgram $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'description' => $p->description,
                'batches_count' => $p->batches_count,
            ]);

        return Inertia::render('discipleship/index', [
            'programs' => $programs,
        ]);
    }

    /**
     * Per-program view: header plus the program's batches.
     *
     * Batches are ordered by start_date ascending -- the earliest
     * cohort first, matching how a program's history reads. Tie-
     * break on id for stable ordering when two batches share a
     * start_date.
     */
    public function show(DiscipleshipProgram $program): Response
    {
        $batches = $program->batches()
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->map(fn (DiscipleshipBatch $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'start_date' => $b->start_date->toDateString(),
            ]);

        return Inertia::render('discipleship/show', [
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
                'slug' => $program->slug,
                'description' => $program->description,
            ],
            'batches' => $batches,
            'batches_count' => $batches->count(),
        ]);
    }
}
