<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * First-visit record attached to a participant.
 *
 * A participant may have zero or more first_timer records over time.
 * Promotion creates a members row for the same participant and fills
 * promoted_at + promoted_to_member_id on this record.
 *
 * Per docs/church-events/06 sub-decisions Q3a + Q3b (2026-10-16).
 */
#[Fillable([
    'participant_id',
    'first_service_date',
    'invited_by',
    'follow_up_notes',
    'promoted_at',
    'promoted_to_member_id',
])]
class FirstTimer extends Model
{
    protected function casts(): array
    {
        return [
            'first_service_date' => 'date',
            'promoted_at' => 'immutable_datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /**
     * The member this first-timer was promoted to.
     *
     * Null while unpromoted. Non-null after promotion. The FK is on
     * this table (promoted_to_member_id), so the relation is a plain
     * belongsTo with an explicit foreign key name.
     */
    public function promotedToMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'promoted_to_member_id');
    }

    /**
     * True when this first-timer has been promoted to membership.
     */
    public function isPromoted(): bool
    {
        return $this->promoted_at !== null;
    }
}
