<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One cohort of a Discipleship program.
 *
 * A batch is the unit of enrollment (5C), session scheduling (5D),
 * and progress reporting (5F). The program is the parent; the
 * batch is where the participant actually participates.
 *
 * Sessions (5D) will add a hasMany relation here in that
 * sub-phase. Enrollments (5C) will add another. Neither relation
 * is present at 5B -- forward-referencing classes that do not yet
 * exist would break the autoloader.
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
}
