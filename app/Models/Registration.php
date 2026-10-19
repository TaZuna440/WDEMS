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
    'flagged_at',
    'notes',
    'submitted_email',
    'submitted_contact_number',
    'attendance_code',
    'consent_accepted_at',
    'privacy_notice_version',
    'emergency_contact_name',
    'emergency_contact_relationship',
    'emergency_contact_number',
])]
class Registration extends Model
{
    protected function casts(): array
    {
        return [
            'registration_date' => 'datetime',
            'registered_at' => 'datetime',
            'flagged_at' => 'datetime',
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
     * Submitted contacts are registration snapshots; optional contacts never
     * become global Participant identity keys through public registration.
     * Other common participant fields live on the participants table.
     *
     * Cascade deletes at the schema level via the FK on
     * registration_id — deleting a registration removes its responses.
     */
    public function fieldResponses(): HasMany
    {
        return $this->hasMany(RegistrationFieldResponse::class);
    }

    /**
     * True when the organizer has flagged this registration for
     * review. Reads the `flagged_at` timestamp. Set and cleared by
     * the monitor's flag action.
     */
    public function isFlagged(): bool
    {
        return $this->flagged_at !== null;
    }
}
