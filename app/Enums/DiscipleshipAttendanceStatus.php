<?php

namespace App\Enums;

/**
 * Status of a participant's attendance at one session.
 *
 * Three values, mirroring the Services module's allowed set.
 * Absence and excuse are distinguished because a future report
 * may want to separate "did not show" from "told us in advance".
 *
 * Present and excused both count toward the progress percentage
 * in Phase 5F. Absent does not. That decision lives in the
 * progress service, not here.
 *
 * Phase 5E, per docs/church-events/07 section 3.6 (5E.1).
 */
enum DiscipleshipAttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Excused = 'excused';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::Excused => 'Excused',
        };
    }
}
