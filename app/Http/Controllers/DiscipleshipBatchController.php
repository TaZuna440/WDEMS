<?php

namespace App\Http\Controllers;

use App\Http\Requests\Discipleship\StoreDiscipleshipBatchRequest;
use App\Http\Requests\Discipleship\UpdateDiscipleshipBatchRequest;
use App\Models\DiscipleshipBatch;
use App\Models\DiscipleshipProgram;
use Illuminate\Http\RedirectResponse;

/**
 * CRUD for Discipleship batches.
 *
 * There is no index action: batches render on the program show
 * page, loaded via DiscipleshipController::show. This controller
 * handles only the write actions.
 *
 * All three actions redirect back to the program show page so the
 * operator sees the batch list after the write.
 *
 * Phase 5B.
 */
class DiscipleshipBatchController extends Controller
{
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
