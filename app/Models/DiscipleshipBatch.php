<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One cohort of a Discipleship program.
 *
 * A batch is the unit of session scheduling (5D) and progress
 * reporting (5F). Enrollment is keyed at the program level (5C);
 * a participant's enrollment row carries a nullable batch_id that
 * points at the batch they are currently attending.
 *
 * sessions() added in 5D. enrollments() added in 5C.
 *
 * Phase 5B.
 */
#[Fillable([
    'program_id',
    'name',
    'start_date',
    'created_by',
])]
class DiscipleshipBatch extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipProgram::class, 'program_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(DiscipleshipEnrollment::class, 'batch_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(DiscipleshipSession::class, 'batch_id');
    }
}
