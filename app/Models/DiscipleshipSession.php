<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One meeting of a Discipleship batch.
 *
 * A batch contains many sessions. Sessions are the unit that
 * session attendance records against -- one attendance row per
 * enrollment per session.
 *
 * Session order is derived from session_date, with id as a
 * tiebreak. There is no explicit session_number column.
 *
 * attendances() added in 5E.
 *
 * Phase 5D.
 */
#[Fillable([
    'batch_id',
    'session_date',
    'topic',
    'notes',
    'created_by',
])]
class DiscipleshipSession extends Model
{
    protected function casts(): array
    {
        return [
            'session_date' => 'date',
        ];
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
        return $this->hasMany(DiscipleshipAttendance::class, 'session_id');
    }
}
