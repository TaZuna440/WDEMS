<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\EventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'created_by',
    'event_type',
    'event_name',
    'description',
    'event_date',
    'start_time',
    'end_time',
    'venue',
    'status',
    'registration_start',
    'registration_end',
])]
class Event extends Model
{
    protected function casts(): array
    {
        return [
            'event_type' => EventType::class,
            'event_date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'registration_start' => 'datetime',
            'registration_end' => 'datetime',
            'status' => EventStatus::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function eventOptions(): HasMany
    {
        return $this->hasMany(EventOption::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function registrationSetup(): HasOne
    {
        return $this->hasOne(RegistrationSetup::class);
    }

    /**
     * Counts of records that will be deleted along with this event.
     *
     * Participants are intentionally excluded — participant records are
     * shared across events and must not be deleted when an event is removed.
     *
     * @return array{event_options: int, registrations: int, registration_options: int, attendances: int}
     */
    public function relatedRecordCounts(): array
    {
        $registrationIds = $this->registrations()->pluck('id');

        return [
            'event_options' => $this->eventOptions()->count(),
            'registrations' => $registrationIds->count(),
            'registration_options' => RegistrationOption::whereIn('registration_id', $registrationIds)->count(),
            'attendances' => Attendance::whereIn('registration_id', $registrationIds)->count(),
        ];
    }

    /**
     * Whether the event's descriptive fields can be edited.
     * Locked once the event is running or past.
     */
    public function canEdit(): bool
    {
        return in_array($this->status, [
            EventStatus::Draft,
            EventStatus::Configured,
            EventStatus::RegistrationOpen,
            EventStatus::RegistrationClosed,
        ], true);
    }

    /**
     * Whether the event's options can be added, edited, or removed.
     * Locked once registration opens.
     */
    public function canConfigure(): bool
    {
        return in_array($this->status, [
            EventStatus::Draft,
            EventStatus::Configured,
        ], true);
    }

    /**
     * Whether registration can be opened for this event.
     * Only from `configured`.
     */
    public function canOpenRegistration(): bool
    {
        return $this->status === EventStatus::Configured;
    }

    /**
     * Whether registration can be closed for this event.
     * Only from `registration_open`.
     */
    public function canCloseRegistration(): bool
    {
        return $this->status === EventStatus::RegistrationOpen;
    }

    /**
     * Whether this event is a Community Run.
     */
    public function isCommunityRun(): bool
    {
        return $this->event_type === EventType::CommunityRun;
    }

    /**
     * Whether this event is a Fun Run.
     */
    public function isFunRun(): bool
    {
        return $this->event_type === EventType::FunRun;
    }
}
