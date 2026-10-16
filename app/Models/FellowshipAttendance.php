<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One participant's attendance at one Fellowship event.
 *
 * One unified track (unlike Services, which has Members and
 * First-Timers). Attendance may optionally be assigned to a group
 * via fellowship_group_id — nullable because a participant may be
 * marked without a group, and because a deleted group leaves its
 * attendance rows in place with a NULL pointer.
 *
 * attendance_status is stored as a string ('present', 'absent',
 * 'excused'). Not an enum because the allowed set may grow; the
 * controller validates against an inline list.
 *
 * Phase 4.
 */
#[Fillable([
    'fellowship_event_id',
    'participant_id',
    'fellowship_group_id',
    'attendance_status',
    'recorded_by',
    'recorded_at',
    'notes',
])]
class FellowshipAttendance extends Model
{
    protected function casts(): array
    {
        return [
            'recorded_at' => 'immutable_datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(FellowshipEvent::class, 'fellowship_event_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(FellowshipGroup::class, 'fellowship_group_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
