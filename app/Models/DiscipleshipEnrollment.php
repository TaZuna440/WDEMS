<?php

namespace App\Models;

use App\Enums\DiscipleshipEnrollmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One participant's enrollment in a Discipleship program.
 *
 * Keyed on (participant_id, program_id) with a total unique
 * constraint. A participant can hold at most one enrollment row
 * per program across their lifetime. Withdrawn and on_hold are
 * statuses, not deletions.
 *
 * batch_id is the "batch pointer" -- which cohort the participant
 * is currently in. Nullable when no batch is chosen yet, and
 * cleared to NULL if the batch is deleted.
 *
 * attendances() added in 5E. One row per session attended (or
 * not) by this enrollment.
 *
 * Phase 5C.
 */
#[Fillable([
    'participant_id',
    'program_id',
    'batch_id',
    'status',
    'enrolled_at',
    'notes',
    'created_by',
])]
class DiscipleshipEnrollment extends Model
{
    protected function casts(): array
    {
        return [
            'status' => DiscipleshipEnrollmentStatus::class,
            'enrolled_at' => 'immutable_datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipProgram::class, 'program_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipBatch::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(DiscipleshipAttendance::class, 'enrollment_id');
    }
}
