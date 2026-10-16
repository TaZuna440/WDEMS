<?php

namespace App\Http\Controllers;

use App\Enums\DiscipleshipEnrollmentStatus;
use App\Http\Requests\Discipleship\StoreDiscipleshipEnrollmentRequest;
use App\Http\Requests\Discipleship\UpdateDiscipleshipEnrollmentRequest;
use App\Models\DiscipleshipBatch;
use App\Models\DiscipleshipEnrollment;
use App\Models\DiscipleshipProgram;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Enrollment CRUD for Discipleship programs.
 *
 * Enrollment is keyed on (participant_id, program_id) with a
 * total unique constraint. A participant can hold at most one
 * enrollment row per program across their lifetime.
 *
 * Withdraw semantics: destroy() sets status = withdrawn. The row
 * survives. The unique constraint means a re-enroll must reset
 * the same row's status, not create a second row. This is
 * deliberate -- see the Phase 5B progress entry for the
 * program-level-with-batch-pointer decision.
 *
 * batch_id is a pointer, not a parent. The controller asserts it
 * belongs to the same program as the enrollment. A deleted batch
 * nulls the pointer (nullOnDelete in the migration) without
 * touching the enrollment.
 *
 * Phase 5C.
 */
class DiscipleshipEnrollmentController extends Controller
{
    public function store(
        StoreDiscipleshipEnrollmentRequest $request,
        DiscipleshipProgram $program,
    ): RedirectResponse {
        $validated = $request->validated();

        $batchId = $validated['batch_id'] ?? null;

        if ($batchId !== null) {
            $this->assertBatchBelongsToProgram((int) $batchId, $program->id);
        }

        // The DB unique constraint would catch a duplicate as a
        // QueryException. A friendly 422 is better.
        $exists = DiscipleshipEnrollment::query()
            ->where('participant_id', $validated['participant_id'])
            ->where('program_id', $program->id)
            ->exists();

        if ($exists) {
            abort(422, 'This participant is already enrolled in this program.');
        }

        DiscipleshipEnrollment::create([
            'participant_id' => $validated['participant_id'],
            'program_id' => $program->id,
            'batch_id' => $batchId,
            'status' => DiscipleshipEnrollmentStatus::Enrolled->value,
            'enrolled_at' => $validated['enrolled_at'] ?? now(),
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('discipleship.programs.show', ['program' => $program->slug])
            ->with('success', 'Participant enrolled.');
    }

    public function update(
        UpdateDiscipleshipEnrollmentRequest $request,
        DiscipleshipEnrollment $enrollment,
    ): RedirectResponse {
        $validated = $request->validated();

        $batchId = $validated['batch_id'] ?? null;

        if ($batchId !== null) {
            $this->assertBatchBelongsToProgram((int) $batchId, $enrollment->program_id);
        }

        $enrollment->update([
            'status' => $validated['status'],
            'batch_id' => $batchId,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('discipleship.programs.show', ['program' => $enrollment->program->slug])
            ->with('success', 'Enrollment updated.');
    }

    /**
     * Withdraw -- does not delete the row.
     *
     * Sets status = withdrawn. The unique on (participant_id,
     * program_id) means a re-enroll resets the same row. Deleting
     * would allow a second enrollment row to be created, breaking
     * the "one row per program across lifetime" invariant the
     * migration was designed to enforce.
     */
    public function destroy(DiscipleshipEnrollment $enrollment): RedirectResponse
    {
        $slug = $enrollment->program->slug;

        $enrollment->update([
            'status' => DiscipleshipEnrollmentStatus::Withdrawn->value,
        ]);

        return redirect()
            ->route('discipleship.programs.show', ['program' => $slug])
            ->with('success', 'Participant withdrawn.');
    }

    /**
     * Guard: the batch must belong to the same program as the
     * enrollment. Prevents cross-program batch assignment via
     * payload tampering.
     */
    private function assertBatchBelongsToProgram(int $batchId, int $programId): void
    {
        $belongs = DiscipleshipBatch::query()
            ->where('id', $batchId)
            ->where('program_id', $programId)
            ->exists();

        if (! $belongs) {
            throw new NotFoundHttpException();
        }
    }
}
