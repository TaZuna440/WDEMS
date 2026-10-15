<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Church membership record attached to a participant.
 *
 * One member per participant (unique participant_id). The participants
 * table owns identity; this table carries church-specific fields.
 *
 * Per docs/church-events/06 sub-decision Q2a (2026-10-16).
 */
#[Fillable([
    'participant_id',
    'membership_status',
    'membership_date',
    'cell_group',
    'ministry',
    'cell_leader',
    'joined_via',
    'notes',
])]
class Member extends Model
{
    protected function casts(): array
    {
        return [
            'membership_date' => 'date',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
