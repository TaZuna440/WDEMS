<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One named group inside a Fellowship event.
 *
 * Group names are chosen by the organizer (Parents, Youth, Children,
 * Other Participants). Not a fixed list. The UNIQUE on
 * (fellowship_event_id, name) prevents duplicate names within one
 * event.
 *
 * Phase 4.
 */
#[Fillable([
    'fellowship_event_id',
    'name',
    'display_order',
])]
class FellowshipGroup extends Model
{
    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(FellowshipEvent::class, 'fellowship_event_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(FellowshipAttendance::class);
    }
}
