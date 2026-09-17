<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'registration_id',
    'event_option_id',
    'option_value',
])]
class RegistrationOption extends Model
{
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function eventOption(): BelongsTo
    {
        return $this->belongsTo(EventOption::class);
    }
}