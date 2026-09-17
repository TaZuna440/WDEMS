<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'event_id',
    'option_type',
    'option_name',
    'option_value',
    'is_required',
    'is_available',
])]
class EventOption extends Model
{
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_available' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function registrationOptions(): HasMany
    {
        return $this->hasMany(RegistrationOption::class);
    }
}