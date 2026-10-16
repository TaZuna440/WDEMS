<?php

namespace App\Services\Discipleship;

use App\Enums\DiscipleshipAttendanceStatus;
use App\Enums\DiscipleshipEnrollmentStatus;
use App\Models\DiscipleshipAttendance;
use App\Models\DiscipleshipBatch;
use App\Models\DiscipleshipEnrollment;
use App\Models\DiscipleshipProgram;
use App\Models\DiscipleshipSession;

/**
 * Computes progress for Discipleship enrollments, batches, and
 * programs.
 *
 * Progress is attended / applicable:
 *   - applicable = sessions in the enrollment's batch whose
 *     session_date is between the enrollment's enrolled_at date
 *     and today (inclusive).
 *   - attended = attendance rows for this enrollment where
 *     session_id is in the applicable set and status is present
 *     or excused. Excused counts as attended.
 *
 * is_complete:
 *   - required_sessions set: attended >= required
 *   - required_sessions null: applicable > 0 AND attended ==
 *     applicable
 *
 * Pure calculator. No writes. No transition decisions.
 *
 * Phase 5F.
 */
class DiscipleshipProgressService
{
    /**
     * Progress for one enrollment.
     *
     * @return array{
     *     attended: int,
     *     applicable: int,
     *     percentage: float|null,
     *     required_sessions: int|null,
     *     is_complete: bool,
     * }
     */
    public function progress(DiscipleshipEnrollment $enrollment): array
    {
        $batchId = $enrollment->batch_id;

        if ($batchId === null) {
            return [
                'attended' => 0,
                'applicable' => 0,
                'percentage' => null,
                'required_sessions' => $enrollment->program?->required_sessions,
                'is_complete' => false,
            ];
        }

        $enrolledOn = $enrollment->enrolled_at?->toDateString()
            ?? $enrollment->created_at?->toDateString();

        $today = now()->toDateString();

        $applicableSessionIds = DiscipleshipSession::query()
            ->where('batch_id', $batchId)
            ->where('session_date', '<=', $today)
            ->when(
                $enrolledOn !== null,
                fn ($q) => $q->where('session_date', '>=', $enrolledOn),
            )
            ->pluck('id');

        $applicable = $applicableSessionIds->count();

        $attended = DiscipleshipAttendance::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('session_id', $applicableSessionIds)
            ->whereIn('status', [
                DiscipleshipAttendanceStatus::Present->value,
                DiscipleshipAttendanceStatus::Excused->value,
            ])
            ->count();

        $percentage = $applicable > 0
            ? round(($attended / $applicable) * 100, 1)
            : null;

        $required = $enrollment->program?->required_sessions;

        $isComplete = $required !== null
            ? $attended >= $required
            : ($applicable > 0 && $attended === $applicable);

        return [
            'attended' => $attended,
            'applicable' => $applicable,
            'percentage' => $percentage,
            'required_sessions' => $required,
            'is_complete' => $isComplete,
        ];
    }

    /**
     * Per-enrollment progress for every enrollment visible on a
     * batch detail page. Same enrollment set the batch-show
     * controller returns: batch-assigned plus program-level
     * (null batch pointer).
     *
     * N+1 note: each enrollment's progress runs its own queries.
     * A 20-person batch is ~40 queries. Acceptable at church
     * scale; if a batch ever exceeds ~100 enrollees, batch the
     * applicable-session lookup and the attended-count lookup by
     * enrollment_id.
     *
     * @return array<int, array{
     *     enrollment_id: int,
     *     attended: int,
     *     applicable: int,
     *     percentage: float|null,
     *     required_sessions: int|null,
     *     is_complete: bool,
     * }>
     */
    public function batchSummary(DiscipleshipBatch $batch): array
    {
        $enrollments = DiscipleshipEnrollment::query()
            ->where('program_id', $batch->program_id)
            ->where(function ($q) use ($batch): void {
                $q->where('batch_id', $batch->id)
                    ->orWhereNull('batch_id');
            })
            ->with('program')
            ->orderBy('id')
            ->get();

        return $enrollments
            ->map(fn (DiscipleshipEnrollment $e) => array_merge(
                ['enrollment_id' => $e->id],
                $this->progress($e),
            ))
            ->all();
    }

    /**
     * Status counts and completion rate for a program. Used by
     * the Program Overview card on the program show page.
     *
     * Cheap — counts statuses, does not compute per-enrollment
     * progress. The mockup's Program Overview card shows
     * "Total / Completed / In Progress / Not Started", all of
     * which are derivable from the enrollment status column
     * alone.
     *
     * "Not Started" maps to Enrolled — a participant who is
     * enrolled but has not been marked In Progress yet.
     * "In Progress" maps to InProgress. Both are statuses; the
     * completion criteria do not participate.
     *
     * completion_rate is completed / total, as a percentage.
     * Null when the program has no enrollments.
     *
     * @return array{
     *     total: int,
     *     completed: int,
     *     in_progress: int,
     *     not_started: int,
     *     withdrawn: int,
     *     on_hold: int,
     *     completion_rate: float|null,
     * }
     */
    public function programOverview(DiscipleshipProgram $program): array
    {
        $enrollments = $program->enrollments()->get();

        $counts = [
            'total' => $enrollments->count(),
            'completed' => 0,
            'in_progress' => 0,
            'not_started' => 0,
            'withdrawn' => 0,
            'on_hold' => 0,
        ];

        foreach ($enrollments as $enrollment) {
            match ($enrollment->status) {
                DiscipleshipEnrollmentStatus::Completed => $counts['completed']++,
                DiscipleshipEnrollmentStatus::InProgress => $counts['in_progress']++,
                DiscipleshipEnrollmentStatus::Enrolled => $counts['not_started']++,
                DiscipleshipEnrollmentStatus::Withdrawn => $counts['withdrawn']++,
                DiscipleshipEnrollmentStatus::OnHold => $counts['on_hold']++,
            };
        }

        $counts['completion_rate'] = $counts['total'] > 0
            ? round(($counts['completed'] / $counts['total']) * 100, 1)
            : null;

        return $counts;
    }
}
