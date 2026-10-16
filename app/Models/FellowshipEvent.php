<?php

namespace App\Models;

use App\Enums\FellowshipType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One Fellowship gathering. The parent record for groups and
 * attendance.
 *
 * Groups per event are in fellowship_groups. Attendance per
 * participant per event is in fellowship_attendance.
 *
 * Phase 4.
 */
#[Fillable([
    'fellowship_type',
    'name',
    'description',
    'event_date',
    'start_time',
    'end_time',
    'venue',
    'facilitator',
    'notes',
    'created_by',
])]
class FellowshipEvent extends Model
{
    protected function casts(): array
    {
        return [
            'fellowship_type' => FellowshipType::class,
            'event_date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(FellowshipGroup::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(FellowshipAttendance::class);
    }
}
