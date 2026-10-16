<?php

namespace App\Models;

use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One service date. The parent record for both attendance tracks.
 *
 * Service occurrences are created two ways:
 *   - Manually by the organizer (one date at a time).
 *   - In a batch via the recurring-schedule generator.
 *
 * There is no series concept in Phase 3. Once created, each row
 * is independent.
 */
#[Fillable([
    'service_type',
    'occurrence_date',
    'start_time',
    'end_time',
    'service_leader',
    'notes',
    'created_by',
])]
class ServiceOccurrence extends Model
{
    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'occurrence_date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function memberAttendances(): HasMany
    {
        return $this->hasMany(ServiceAttendanceMember::class);
    }

    public function firstTimerAttendances(): HasMany
    {
        return $this->hasMany(ServiceAttendanceFirstTimer::class);
    }
}
