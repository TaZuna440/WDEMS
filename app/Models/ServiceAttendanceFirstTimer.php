<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One first-timer's attendance at one service occurrence.
 *
 * Distinct from the Members track. A first-timer stays on this
 * track until an organizer promotes them to membership (see
 * FirstTimer::promoted_at). After promotion, future attendance
 * goes through ServiceAttendanceMember instead.
 *
 * attendance_status is stored as a string ('present', 'absent',
 * 'excused') for the same reason as the Members side.
 */
#[Fillable([
    'service_occurrence_id',
    'participant_id',
    'invited_by',
    'attendance_status',
    'recorded_by',
    'recorded_at',
    'notes',
])]
class ServiceAttendanceFirstTimer extends Model
{
    protected function casts(): array
    {
        return [
            'recorded_at' => 'immutable_datetime',
        ];
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ServiceOccurrence::class, 'service_occurrence_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
