<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'event_id',
    'participant_id',
    'registration_date',
    'registration_status',
    'source',
    'registered_at',
])]
class Registration extends Model
{
    protected function casts(): array
    {
        return [
            'registration_date' => 'datetime',
            'registered_at' => 'datetime',
            'registration_status' => RegistrationStatus::class,
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function registrationOptions(): HasMany
    {
        return $this->hasMany(RegistrationOption::class);
    }

    public function attendance(): HasOne
    {
        return $this->hasOne(Attendance::class);
    }
}
