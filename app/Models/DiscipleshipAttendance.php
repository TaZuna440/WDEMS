<?php

namespace App\Models;

use App\Enums\DiscipleshipAttendanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attendance record: an enrollment at one session.
 *
 * Keyed on (enrollment_id, session_id) with a unique constraint.
 * Re-marking a cell updates the existing row -- idempotent.
 *
 * Note this is NOT keyed on participant_id. Attendance belongs to
 * the enrollment, not the participant. See the migration docblock
 * for the reasoning.
 *
 * recorded_by is nullable so attribution survives account
 * deletion. recorded_at is separate from updated_at so a
 * retroactive mark records both when it was made and, if the
 * operator chooses, when it applies.
 *
 * Phase 5E.
 */
#[Fillable([
    'enrollment_id',
    'session_id',
    'status',
    'recorded_by',
    'recorded_at',
    'notes',
])]
class DiscipleshipAttendance extends Model
{
    protected function casts(): array
    {
        return [
            'status' => DiscipleshipAttendanceStatus::class,
            'recorded_at' => 'immutable_datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipEnrollment::class, 'enrollment_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipSession::class, 'session_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
