<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One member's attendance at one service occurrence.
 *
 * attendance_status is stored as a string ('present', 'absent',
 * 'excused'). Not an enum because the allowed set may grow; the
 * controller validates against an inline list.
 */
#[Fillable([
    'service_occurrence_id',
    'participant_id',
    'attendance_status',
    'recorded_by',
    'recorded_at',
    'notes',
])]
class ServiceAttendanceMember extends Model
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
