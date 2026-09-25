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
    'distance_value',
    'distance_unit',
    'course_url',
    'venue',
    'venue_address',
    'venue_map_url',
    'venue_latitude',
    'venue_longitude',
    'status',
    'registration_start',
    'registration_end',
    'registration_form_saved_at',
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
            'event_type' => EventType::class,
            'event_date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'distance_value' => 'decimal:2',
            'registration_start' => 'datetime',
            'registration_end' => 'datetime',
            'registration_form_saved_at' => 'datetime',
            'status' => EventStatus::class,

            'venue_latitude' => 'decimal:7',
            'venue_longitude' => 'decimal:7',

            'partners' => 'array',
            'faq' => 'array',

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

    public function registrationFields(): HasMany
    {
        return $this->hasMany(RegistrationField::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * Counts shown on the deletion confirmation dialog.
     *
     * Only keys whose models still exist appear here. event_options
     * and registration_options were dropped in Phase 2 of the
     * registration plan.
     */
    public function relatedRecordCounts(): array
    {
        $registrationIds = $this->registrations()->pluck('id');

        return [
            'registration_fields' => $this->registrationFields()->count(),
            'registrations' => $registrationIds->count(),
            'attendances' => Attendance::whereIn('registration_id', $registrationIds)->count(),
        ];
    }

    public function canEdit(): bool
    {
        return in_array($this->status, [
            EventStatus::Draft,
            EventStatus::RegistrationOpen,
            EventStatus::RegistrationClosed,
        ], true);
    }

    /**
     * Can the organizer edit the registration form for this event?
     *
     * Renamed from canConfigure() in Phase 1 of the registration plan.
     * The workflow no longer has a "configured" state — an event is
     * either still editable (Draft) or has been published to the public
     * registration URL (registration_open and beyond).
     */
    public function canEditRegistrationForm(): bool
    {
        return $this->status === EventStatus::Draft;
    }

    /**
     * Can registration be opened for this event?
     *
     * Two conditions must hold:
     *
     *  1. The event is in Draft — the only state from which
     *     RegistrationOpen is reachable.
     *  2. The registration form has been saved at least once.
     *     `registration_form_saved_at` is set by
     *     RegistrationFormController::update() on every successful save.
     *     A null value means the organizer has not built the form yet,
     *     so participants have nothing to fill in.
     *
     * Without the second condition, an event could move to
     * registration_open with zero custom fields configured — a state
     * where registration is nominally "open" but no submission surface
     * exists.
     *
     * See the migration for
     * events.registration_form_saved_at for the backfill trade-off and
     * the known risk to pre-existing Draft events.
     */
    public function canOpenRegistration(): bool
    {
        return $this->status === EventStatus::Draft
            && $this->registration_form_saved_at !== null;
    }

    public function canCloseRegistration(): bool
    {
        return $this->status === EventStatus::RegistrationOpen;
    }

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

    /**
     * Human-readable distance label — e.g. "5.00 KM" or "3.11 Miles".
     * Returns null if no distance is set.
     */
    public function distanceLabel(): ?string
    {
        if ($this->distance_value === null) {
            return null;
        }

        $unit = $this->distance_unit === 'mi' ? 'Miles' : 'KM';
        $value = rtrim(rtrim(number_format((float) $this->distance_value, 2, '.', ''), '0'), '.');

        return "{$value} {$unit}";
    }
}
