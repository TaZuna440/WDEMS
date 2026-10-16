<?php

namespace App\Http\Controllers;

use App\Enums\DiscipleshipAttendanceStatus;
use App\Http\Requests\Discipleship\StoreDiscipleshipAttendanceBulkRequest;
use App\Models\DiscipleshipAttendance;
use App\Models\DiscipleshipBatch;
use App\Models\DiscipleshipEnrollment;
use App\Models\DiscipleshipSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Attendance recording for a Discipleship batch.
 *
 * Bulk upsert: the operator edits cells on the batch matrix,
 * then saves once. The request carries an array of
 * {enrollment_id, session_id, status} triples. Each triple is
 * upserted on (enrollment_id, session_id) -- re-marking a cell
 * updates the existing row rather than duplicating.
 *
 * Cross-batch guard: every enrollment_id must belong to a
 * participant whose enrollment points at this batch (or is
 * program-level but batch-less and belongs to the same program),
 * and every session_id must belong to this batch. The guard is
 * strict -- a cell referencing a foreign enrollment or session
 * aborts the whole request rather than silently skipping.
 *
 * Phase 5E.
 */
class DiscipleshipAttendanceController extends Controller
{
    public function bulkUpsert(
        StoreDiscipleshipAttendanceBulkRequest $request,
        DiscipleshipBatch $batch,
    ): RedirectResponse {
        $validated = $request->validated();

        // Collect the ids the payload references.
        $enrollmentIds = collect($validated['cells'])
            ->pluck('enrollment_id')
            ->unique()
            ->values()
            ->all();

        $sessionIds = collect($validated['cells'])
            ->pluck('session_id')
            ->unique()
            ->values()
            ->all();

        // Every enrollment must belong to this batch's program.
        // A batch is one cohort of a program; enrollments are
        // program-scoped with an optional batch pointer. The check
        // is "enrollment.program_id == batch.program_id" -- the
        // batch pointer is not required to match because a
        // participant may be enrolled but not yet assigned to a
        // batch.
        $validEnrollmentCount = DiscipleshipEnrollment::query()
            ->whereIn('id', $enrollmentIds)
            ->where('program_id', $batch->program_id)
            ->count();

        if ($validEnrollmentCount !== count($enrollmentIds)) {
            throw new NotFoundHttpException();
        }

        // Every session must belong to this batch.
        $validSessionCount = DiscipleshipSession::query()
            ->whereIn('id', $sessionIds)
            ->where('batch_id', $batch->id)
            ->count();

        if ($validSessionCount !== count($sessionIds)) {
            throw new NotFoundHttpException();
        }

        $userId = $request->user()->id;
        $now = now();

        DB::transaction(function () use ($validated, $userId, $now): void {
            foreach ($validated['cells'] as $cell) {
                DiscipleshipAttendance::updateOrCreate(
                    [
                        'enrollment_id' => $cell['enrollment_id'],
                        'session_id' => $cell['session_id'],
                    ],
                    [
                        'status' => $cell['status'],
                        'recorded_by' => $userId,
                        'recorded_at' => $now,
                    ],
                );
            }
        });

        return redirect()
            ->route('discipleship.batches.show', ['batch' => $batch->id])
            ->with('success', 'Attendance saved.');
    }

    /**
     * Remove one attendance cell.
     *
     * Used when the operator clears a status -- the row is
     * deleted, returning the cell to its "not marked" state.
     * Contrast with enrollment destroy, which sets a status
     * rather than deleting. Attendance is a fact about one
     * session; clearing it is legitimately "no record."
     */
    public function destroy(DiscipleshipAttendance $attendance): RedirectResponse
    {
        $batchId = $attendance->session->batch_id;
        $attendance->delete();

        return redirect()
            ->route('discipleship.batches.show', ['batch' => $batchId])
            ->with('success', 'Attendance cleared.');
    }
}
