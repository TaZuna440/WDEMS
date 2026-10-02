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
    'registration_slug',
    'registration_common_field_requirements',
    'partners',
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
    /**
     * Common participant fields that are always required and cannot
     * be toggled off by the organizer. A participant without a name
     * or an age is unusable for attendance tracking.
     *
     * @var array<int, string>
     */
    public const COMMON_FIELDS_ALWAYS_REQUIRED = [
        'first_name',
        'last_name',
        'age',
    ];

    /**
     * Common participant fields the organizer can mark optional per
     * event. Stored in registration_common_field_requirements as a
     * JSON boolean map.
     *
     * @var array<int, string>
     */
    public const COMMON_FIELDS_TOGGLEABLE = [
        'email',
        'contact_number',
        'address',
    ];

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
            'registration_common_field_requirements' => 'array',
            'status' => EventStatus::class,

            'venue_latitude' => 'decimal:7',
            'venue_longitude' => 'decimal:7',

            'partners' => 'array',

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

    /**
     * Is a common participant field required on this event's public
     * registration form?
     *
     * Always-required fields (first_name, last_name, age) return true
     * regardless of the stored map. Toggleable fields (email,
     * contact_number, address) read the map, falling back to true
     * for a missing key or a NULL column.
     *
     * Unknown field names return true — a defensive default so a typo
     * does not silently make a field optional.
     */
    public function isCommonFieldRequired(string $field): bool
    {
        if (in_array($field, self::COMMON_FIELDS_ALWAYS_REQUIRED, true)) {
            return true;
        }

        if (! in_array($field, self::COMMON_FIELDS_TOGGLEABLE, true)) {
            return true;
        }

        $requirements = $this->registration_common_field_requirements ?? [];

        return $requirements[$field] ?? true;
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

    /**
     * Can attendance be recorded for this event right now?
     *
     * Composes the existing canRecordAttendance() (status is
     * registration_open, registration_closed, or ongoing) with a
     * date gate: the event day must have arrived.
     *
     * The gate exists so an organizer cannot pre-mark attendance for
     * a race that has not happened yet. A same-day registration_open
     * event passes — the race is underway. A future event does not.
     *
     * Distinct from canRecordAttendance() which answers the status
     * question alone.
     */
    public function canRecordAttendanceToday(): bool
    {
        if (! $this->canRecordAttendance()) {
            return false;
        }

        if ($this->event_date === null) {
            return false;
        }

        return $this->event_date->lte(today());
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
