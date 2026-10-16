<?php

namespace App\Http\Controllers;

use App\Http\Requests\Discipleship\StoreDiscipleshipBatchRequest;
use App\Http\Requests\Discipleship\UpdateDiscipleshipBatchRequest;
use App\Models\DiscipleshipBatch;
use App\Models\DiscipleshipProgram;
use App\Models\DiscipleshipSession;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD for Discipleship batches, plus the batch detail page that
 * hosts the session list.
 *
 * The store, update, and destroy actions redirect back to the
 * program show page. The show action is the batch detail page
 * itself.
 *
 * Phase 5B -- store, update, destroy. 5D adds show().
 */
class DiscipleshipBatchController extends Controller
{
    /**
     * Batch detail page: header plus the batch's sessions.
     *
     * Sessions are ordered by session_date ascending -- the
     * chronological progression through the batch. Tie-break on
     * id for stable ordering when two sessions share a date.
     *
     * Enrollments are counted but not listed here; the program
     * show page owns the enrollment list. This page owns the
     * sessions.
     */
    public function show(DiscipleshipBatch $batch): Response
    {
        $batch->load(['program:id,name,slug']);

        $sessions = $batch->sessions()
            ->orderBy('session_date')
            ->orderBy('id')
            ->get()
            ->map(fn (DiscipleshipSession $s) => [
                'id' => $s->id,
                'session_date' => $s->session_date->toDateString(),
                'topic' => $s->topic,
                'notes' => $s->notes,
            ]);

        $enrollmentsCount = $batch->enrollments()->count();

        return Inertia::render('discipleship/batch-show', [
            'batch' => [
                'id' => $batch->id,
                'name' => $batch->name,
                'start_date' => $batch->start_date->toDateString(),
            ],
            'program' => [
                'id' => $batch->program->id,
                'name' => $batch->program->name,
                'slug' => $batch->program->slug,
            ],
            'sessions' => $sessions,
            'sessions_count' => $sessions->count(),
            'enrollments_count' => $enrollmentsCount,
        ]);
    }

    public function store(
        StoreDiscipleshipBatchRequest $request,
        DiscipleshipProgram $program,
    ): RedirectResponse {
        $validated = $request->validated();

        DiscipleshipBatch::create([
            'program_id' => $program->id,
            'name' => $validated['name'],
            'start_date' => $validated['start_date'],
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('discipleship.programs.show', ['program' => $program->slug])
            ->with('success', 'Batch created.');
    }

    public function update(
        UpdateDiscipleshipBatchRequest $request,
        DiscipleshipBatch $batch,
    ): RedirectResponse {
        $validated = $request->validated();

        $batch->update([
            'name' => $validated['name'],
            'start_date' => $validated['start_date'],
        ]);

        return redirect()
            ->route('discipleship.programs.show', ['program' => $batch->program->slug])
            ->with('success', 'Batch updated.');
    }

    public function destroy(DiscipleshipBatch $batch): RedirectResponse
    {
        $slug = $batch->program->slug;
        $batch->delete();

        return redirect()
            ->route('discipleship.programs.show', ['program' => $slug])
            ->with('success', 'Batch deleted.');
    }
}
