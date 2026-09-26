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

    public function attendance(): HasOne
    {
        return $this->hasOne(Attendance::class);
    }

    /**
     * Custom field answers submitted with this registration.
     *
     * One row per custom field the participant filled in. The six
     * common participant fields (first_name, last_name, email,
     * contact_number, age, address) live on the participants table —
     * they are not stored here.
     *
     * Cascade deletes at the schema level via the FK on
     * registration_id — deleting a registration removes its responses.
     */
    public function fieldResponses(): HasMany
    {
        return $this->hasMany(RegistrationFieldResponse::class);
    }
}
