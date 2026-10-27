<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'encoded_by',
    'contact_attested_at',
    'event_id',
    'participant_id',
    'registration_date',
    'registration_status',
    'source',
    'participant_type',
    'fellowship_group_id',
    'guardian_name',
    'guardian_contact',
    'registered_at',
    'flagged_at',
    'notes',
    'submitted_email',
    'submitted_contact_number',
    'attendance_code',
    'confirmation_email_sent_at',
    'consent_accepted_at',
    'privacy_notice_version',
    'emergency_contact_name',
    'tshirt_choice',
    'emergency_contact_first_name',
    'emergency_contact_last_name',
    'emergency_contact_relationship_other',
    'emergency_contact_relationship',
    'emergency_contact_number',
    'duplicate_of_registration_id',
    'held_reason',
    'held_candidate_registration_id',
    'resolved_by',
    'resolved_at',
    'resolution_action',
])]
class Registration extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $registration): void {
            if (in_array($registration->getRawOriginal('registration_status'), ['duplicate', 'removed'], true)
                && $registration->isDirty('registration_status')) {
                throw new \DomainException('A terminal registration cannot be reactivated.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'registration_date' => 'datetime',
            'registered_at' => 'datetime',
            'flagged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'confirmation_email_sent_at' => 'datetime',
            'registration_status' => RegistrationStatus::class,
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<Participant, $this> */
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

    /** @return BelongsTo<self, $this> */
    public function heldCandidate(): BelongsTo
    {
        return $this->belongsTo(self::class, 'held_candidate_registration_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isHeld(): bool
    {
        return $this->registration_status === RegistrationStatus::Held;
    }

    public function isConfirmed(): bool
    {
        return $this->registration_status === RegistrationStatus::Confirmed;
    }

    public function isActive(): bool
    {
        return $this->registration_status === RegistrationStatus::Confirmed;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('registration_status'), RegistrationStatus::Confirmed);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('registration_status', RegistrationStatus::Confirmed);
    }

    public function scopeHeld(Builder $query): Builder
    {
        return $query->where('registration_status', RegistrationStatus::Held);
    }
}
