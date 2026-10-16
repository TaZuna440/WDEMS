<?php

namespace App\Enums;

/**
 * Status of a participant's enrollment in a Discipleship program.
 *
 * Five fixed values, per the Phase 5F.4 task list. The transition
 * rules between them live in Phase 5F -- at 5C only the initial
 * state (Enrolled) and the shape of the value set are needed.
 *
 * "Active" enrollments for reporting purposes are Enrolled and
 * InProgress. Completed, Withdrawn, and OnHold are terminal or
 * paused states.
 *
 * Phase 5C, per docs/church-events/07 section 3.6 (5F.4).
 */
enum DiscipleshipEnrollmentStatus: string
{
    case Enrolled = 'enrolled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Withdrawn = 'withdrawn';
    case OnHold = 'on_hold';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<int, string> */
    public static function activeValues(): array
    {
        return [self::Enrolled->value, self::InProgress->value];
    }

    public function label(): string
    {
        return match ($this) {
            self::Enrolled => 'Enrolled',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Withdrawn => 'Withdrawn',
            self::OnHold => 'On Hold',
        };
    }
}
