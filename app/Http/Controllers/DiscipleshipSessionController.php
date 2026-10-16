<?php

namespace App\Http\Controllers;

use App\Http\Requests\Discipleship\StoreDiscipleshipSessionRequest;
use App\Http\Requests\Discipleship\UpdateDiscipleshipSessionRequest;
use App\Models\DiscipleshipBatch;
use App\Models\DiscipleshipSession;
use Illuminate\Http\RedirectResponse;

/**
 * CRUD for Discipleship sessions on a batch.
 *
 * There is no index action: sessions render on the batch detail
 * page, loaded via DiscipleshipBatchController::show. This
 * controller handles only the write actions.
 *
 * All three actions redirect back to the batch detail page.
 *
 * Phase 5D.
 */
class DiscipleshipSessionController extends Controller
{
    public function store(
        StoreDiscipleshipSessionRequest $request,
        DiscipleshipBatch $batch,
    ): RedirectResponse {
        $validated = $request->validated();

        DiscipleshipSession::create([
            'batch_id' => $batch->id,
            'session_date' => $validated['session_date'],
            'topic' => $validated['topic'],
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('discipleship.batches.show', ['batch' => $batch->id])
            ->with('success', 'Session created.');
    }

    public function update(
        UpdateDiscipleshipSessionRequest $request,
        DiscipleshipSession $session,
    ): RedirectResponse {
        $validated = $request->validated();

        $session->update([
            'session_date' => $validated['session_date'],
            'topic' => $validated['topic'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('discipleship.batches.show', ['batch' => $session->batch_id])
            ->with('success', 'Session updated.');
    }

    public function destroy(DiscipleshipSession $session): RedirectResponse
    {
        $batchId = $session->batch_id;
        $session->delete();

        return redirect()
            ->route('discipleship.batches.show', ['batch' => $batchId])
            ->with('success', 'Session deleted.');
    }
}
