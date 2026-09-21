<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\EventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'created_by',
    'event_type',
    'event_name',
    'description',
    'event_date',
    'start_time',
    'end_time',
    'venue',
    'venue_address',
    'venue_latitude',
    'venue_longitude',
    'distance_label',
    'course_url',
    'status',
    'registration_start',
    'registration_end',
    'rsvp_required',
    'partners',
    'faq',
    'walkers_welcome',
    'all_paces_welcome',
    'all_ages_welcome',
    'stroller_friendly',
    'wheelchair_accessible',
    'sweeper_present',
    'service_animals_allowed',
    'leashed_pets_allowed',
    'quiet_space_available',
])]
class Event extends Model
{
    protected function casts(): array
    {
        return [
            // Existing
            'event_type' => EventType::class,
            'event_date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'registration_start' => 'datetime',
            'registration_end' => 'datetime',
            'status' => EventStatus::class,

            // Venue coordinates
            'venue_latitude' => 'decimal:7',
            'venue_longitude' => 'decimal:7',

            // Registration extras
            'rsvp_required' => 'boolean',

            // Structured JSON
            'partners' => 'array',
            'faq' => 'array',

            // Accessibility booleans
            'walkers_welcome' => 'boolean',
            'all_paces_welcome' => 'boolean',
            'all_ages_welcome' => 'boolean',
            'stroller_friendly' => 'boolean',
            'wheelchair_accessible' => 'boolean',
            'sweeper_present' => 'boolean',
            'service_animals_allowed' => 'boolean',
            'leashed_pets_allowed' => 'boolean',
            'quiet_space_available' => 'boolean',
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

    public function canEdit(): bool
    {
        return in_array($this->status, [
            EventStatus::Draft,
            EventStatus::Configured,
            EventStatus::RegistrationOpen,
            EventStatus::RegistrationClosed,
        ], true);
    }

    public function canConfigure(): bool
    {
        return in_array($this->status, [
            EventStatus::Draft,
            EventStatus::Configured,
        ], true);
    }

    public function canOpenRegistration(): bool
    {
        return $this->status === EventStatus::Configured;
    }

    public function canCloseRegistration(): bool
    {
        return $this->status === EventStatus::RegistrationOpen;
    }

    /**
     * Whether the event is in a state where attendance can be recorded.
     * Attendance is meaningful once registration has opened.
     */
    public function canRecordAttendance(): bool
    {
        return in_array($this->status, [
            EventStatus::RegistrationOpen,
            EventStatus::RegistrationClosed,
            EventStatus::Ongoing,
        ], true);
    }

    public function isCommunityRun(): bool
    {
        return $this->event_type === EventType::CommunityRun;
    }

    public function isFunRun(): bool
    {
        return $this->event_type === EventType::FunRun;
    }
}
