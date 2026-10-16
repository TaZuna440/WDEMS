<?php

namespace App\Http\Controllers;

use App\Http\Requests\Discipleship\StoreDiscipleshipBatchRequest;
use App\Http\Requests\Discipleship\UpdateDiscipleshipBatchRequest;
use App\Models\DiscipleshipAttendance;
use App\Models\DiscipleshipBatch;
use App\Models\DiscipleshipEnrollment;
use App\Models\DiscipleshipProgram;
use App\Models\DiscipleshipSession;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD for Discipleship batches, plus the batch detail page that
 * hosts the session list and the attendance matrix.
 *
 * The store, update, and destroy actions redirect back to the
 * program show page. The show action is the batch detail page
 * itself.
 *
 * Phase 5B -- store, update, destroy. 5D adds show(). 5E adds
 * enrollments and attendances to show().
 */
class DiscipleshipBatchController extends Controller
{
    /**
     * Batch detail page: header, session list, and the attendance
     * matrix.
     *
     * Enrollments in the payload are those whose batch_id points
     * at this batch, plus program-level enrollments with a null
     * batch pointer. Why both: a batch is a cohort of a program;
     * enrollments are program-scoped with an optional batch
     * pointer. An operator may want to mark a not-yet-batched
     * enrollee who shows up. The matrix tolerates both.
     *
     * Attendances are the sparse set of marked cells. The frontend
     * renders an empty dropdown for any (enrollment, session) pair
     * not present in the payload.
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

        $enrollments = DiscipleshipEnrollment::query()
            ->where('program_id', $batch->program_id)
            ->where(function ($q) use ($batch): void {
                $q->where('batch_id', $batch->id)
                    ->orWhereNull('batch_id');
            })
            ->with('participant:id,first_name,last_name,email,contact_number')
            ->orderBy('id')
            ->get()
            ->map(fn (DiscipleshipEnrollment $e) => [
                'id' => $e->id,
                'participant_id' => $e->participant_id,
                'full_name' => $e->participant?->fullName(),
                'email' => $e->participant?->email,
                'status' => $e->status->value,
            ]);

        $attendances = DiscipleshipAttendance::query()
            ->whereIn('enrollment_id', $enrollments->pluck('id'))
            ->whereIn('session_id', $sessions->pluck('id'))
            ->get()
            ->map(fn (DiscipleshipAttendance $a) => [
                'enrollment_id' => $a->enrollment_id,
                'session_id' => $a->session_id,
                'status' => $a->status->value,
            ]);

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
            'enrollments' => $enrollments,
            'enrollments_count' => $enrollments->count(),
            'attendances' => $attendances,
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
