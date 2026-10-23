<?php

namespace App\Models;

use App\Enums\ChurchEventStatus;
use App\Enums\EventStatus;
use App\Enums\EventType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $registration_count Added by event queries that count active registrations.
 */
#[Fillable([
    'created_by',
    'event_kind',
    'church_registration_open',
    'church_category',
    'church_activity_category',
    'church_status',
    'event_type',
    'event_name',
    'description',
    'coordinator',
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
    'emergency_contact_name',
    'emergency_contact_number',
    'medical_notes',
    'parental_consent',
    'child_name',
    'mother_name',
    'father_name',
    'officiating_pastor',
    'assigned_teacher',
    'program_day_session',
])]
class Event extends Model
{
    /**
     * Common participant fields that are always required and cannot
     * be toggled off by the organizer. A participant without a name
     * or a date of birth is unusable for attendance tracking.
     *
     * `birthday` replaced `age` on 2026-10-19 when the public
     * registration form switched from a free-typed age to a bounded
     * date picker (age 3 to 120). `Participant::getAgeAttribute()`
     * derives age from birthday on read, so downstream consumers
     * see the same integer age they always did.
     *
     * `age` was removed from this list — but it does NOT break.
     * `isCommonFieldRequired('age')` falls through to the defensive
     * default (step 3 of the method) and returns true for any field
     * not in either list. Existing Services and Fellowship attendance
     * requests still collect `age` directly through their own
     * FormRequests; the accessor falls back to the stored column.
     *
     * @var array<int, string>
     */
    public const COMMON_FIELDS_ALWAYS_REQUIRED = [
        'first_name',
        'last_name',
        'birthday',
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

    /**
     * How many minutes before the event's start_time the attendance
     * window opens. See docs/attendance-redesign.md section 3.
     *
     * 60 = one hour early. Adjusting this constant is the single knob
     * for the whole window policy.
     */
    public const ATTENDANCE_WINDOW_MINUTES_BEFORE = 60;

    /**
     * Event kind discriminator values.
     *
     * 'running' -- uses EventStatus (Draft, RegistrationOpen, etc.)
     * 'church'  -- uses ChurchEventStatus (Upcoming, Ongoing, etc.)
     *
     * Per docs/church-events/06 sub-decisions Q5, Q6 (2026-10-16).
     */
    public const KIND_RUNNING = 'running';

    public const KIND_CHURCH = 'church';

    /*
     * Lifecycle guard policy.
     *
     * Church events and running events are two genuinely separate
     * lifecycles. They share the `events` table but differ in:
     *
     *   - status source: `church_status` (church) vs `status` (running)
     *   - editable states (see canEdit() below)
     *   - downstream flow: church uses module paths (Services,
     *     Fellowship, Discipleship); running uses registration,
     *     attendance, and finalize on the running-event surface
     *
     * Six of the can*() guards below are running-only and return
     * false for church events:
     *
     *   - canEditRegistrationForm
     *   - canOpenRegistration
     *   - canCloseRegistration
     *   - canFinalize
     *   - canRecordAttendance
     *   - canViewAttendance
     *
     * One guard -- canEdit -- applies to both kinds, with a
     * per-kind rule. See its docblock.
     *
     * Each rule reads its own lifecycle's status column. Neither
     * reads the other lifecycle's column. This is deliberate: a
     * church event whose `status` column reads 'draft' (the DB
     * default for that non-nullable column) must not be treated as
     * a running event in Draft.
     *
     * isRunningEvent() and isChurchEvent() are the dispatchers.
     * Neither is a negation of the other -- both return false if
     * event_kind is a value not in {running, church}. Guards that
     * dispatch on kind fail closed on unknown kinds.
     */

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
            'registration_form_version' => 'integer',
            'registration_tshirt' => 'array',
            'status' => EventStatus::class,
            'church_status' => ChurchEventStatus::class,
            'church_registration_open' => 'boolean',

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
            'parental_consent' => 'boolean',
        ];
    }

    /**
     * Default event_kind to 'running' on create.
     *
     * The DB column has ->default('running') (see migration
     * 2026_10_16_110000), but the default fires at INSERT time and
     * never re-propagates to the in-memory model. Any code that
     * calls Event::create([...]) without setting event_kind gets a
     * model whose event_kind is null, even though the DB row reads
     * 'running'.
     *
     * That gap is invisible to route-bound controllers (the model is
     * rebound from DB with the correct value), but visible to any
     * direct Model access -- including test helpers that create an
     * event and then call a can*() guard on the returned instance.
     *
     * This hook closes the gap by setting event_kind in the model
     * before INSERT, so the in-memory instance and the DB row agree.
     * Any caller that sets event_kind explicitly (e.g.
     * EventController::store for church events) is unaffected --
     * the hook only fires when the value is null.
     *
     * Any raw insert that bypasses Eloquent still hits the DB default
     * and gets 'running' correctly.
     */
    protected static function booted(): void
    {
        static::creating(function (Event $event): void {
            if ($event->event_kind === null) {
                $event->event_kind = self::KIND_RUNNING;
            }
        });
    }

    /**
     * Is this a church event?
     *
     * Per the discriminator column added in the Phase 2 migration.
     * Existing rows default to 'running'.
     */
    public function isChurchEvent(): bool
    {
        return $this->event_kind === self::KIND_CHURCH;
    }

    /**
     * Is this a running event (Community Run or Fun Run)?
     */
    public function isRunningEvent(): bool
    {
        return $this->event_kind === self::KIND_RUNNING;
    }

    /**
     * Human-readable status label for this event.
     *
     * Dispatches to EventStatus or ChurchEventStatus depending on
     * the event kind. Frontend uses this for status pills.
     */
    public function statusLabel(): string
    {
        if ($this->isChurchEvent() && $this->church_status !== null) {
            return $this->church_status->label();
        }

        return $this->status?->label() ?? '';
    }

    /**
     * Is a common participant field required on this event's public
     * registration form?
     *
     * Always-required fields (first_name, last_name, birthday) return
     * true regardless of the stored map. Toggleable fields (email,
     * contact_number, address) read the map, falling back to true
     * for a missing key or a NULL column.
     *
     * Unknown field names return true — a defensive default so a typo
     * does not silently make a field optional. This is what makes
     * `isCommonFieldRequired('age')` continue to return true even
     * though `age` is no longer in COMMON_FIELDS_ALWAYS_REQUIRED:
     * `age` is not in either list, so the defensive default applies.
     */
    public function isCommonFieldRequired(string $field): bool
    {
        if ($this->usesEmailOnlyRegistration() && in_array($field, ['email', 'address'], true)) {
            return true;
        }

        if (in_array($field, self::COMMON_FIELDS_ALWAYS_REQUIRED, true)) {
            return true;
        }

        if (! in_array($field, self::COMMON_FIELDS_TOGGLEABLE, true)) {
            return true;
        }

        $requirements = $this->registration_common_field_requirements ?? [];

        return $requirements[$field] ?? true;
    }

    public function usesEmailOnlyRegistration(): bool
    {
        return $this->isChurchEvent() || ($this->isRunningEvent() && $this->registration_form_version >= 2);
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

    /**
     * Can the event be edited via the running-event edit path?
     *
     * This is the one can*() guard that applies to BOTH event kinds,
     * with a per-kind rule. The two lifecycles are separate systems
     * (see the class-level lifecycle guard policy note).
     *
     *   Running: editable in Draft, RegistrationOpen, RegistrationClosed.
     *            Not editable in Ongoing, Completed, or Cancelled -- the
     *            three terminal-or-locked states for the running flow.
     *
     *   Church:  editable in Upcoming, Ongoing.
     *            Not editable in Completed or Cancelled -- the two
     *            terminal states for the church flow.
     *
     * Each rule reads its own lifecycle's status column. A church
     * event whose status column reads 'draft' (the DB default for
     * that non-nullable column) is editable based on its
     * church_status, not on its status.
     *
     * Fails closed on a null church_status for a church event, and
     * on an unknown event_kind for a non-church event.
     */
    public function canEdit(): bool
    {
        if ($this->isChurchEvent()) {
            return in_array($this->church_status, [
                ChurchEventStatus::Upcoming,
                ChurchEventStatus::Ongoing,
            ], true);
        }

        return $this->isRunningEvent() && in_array($this->status, [
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
     *
     * Running event only. Church events do not have a registration
     * form on the running-event side.
     */
    public function canEditRegistrationForm(): bool
    {
        return $this->isRunningEvent() && $this->status === EventStatus::Draft;
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
     * Running event only. A church event that passes the two status
     * checks above (Draft + form saved) would generate a public
     * /r/{slug} URL for a church event that has no meaningful
     * registration flow behind it. See ISSUE-014.
     *
     * See the migration for
     * events.registration_form_saved_at for the backfill trade-off and
     * the known risk to pre-existing Draft events.
     */
    public function canOpenRegistration(): bool
    {
        return $this->isRunningEvent()
            && $this->status === EventStatus::Draft
            && $this->registration_form_saved_at !== null;
    }

    public function acceptsPublicRegistration(): bool
    {
        return $this->isRunningEvent()
            && in_array($this->status, [EventStatus::RegistrationOpen, EventStatus::Ongoing], true)
            && ($this->registration_end === null || now()->lessThan($this->registration_end));
    }

    public function acceptsChurchPublicRegistration(): bool
    {
        return $this->isChurchEvent() && (bool) $this->church_registration_open
            && in_array($this->getRawOriginal('church_status'), [ChurchEventStatus::Upcoming->value, ChurchEventStatus::Ongoing->value], true);
    }

    public function canCloseRegistration(): bool
    {
        return $this->acceptsPublicRegistration();
    }

    public function canReopenRegistration(): bool
    {
        return $this->isRunningEvent() && ($this->status === EventStatus::RegistrationClosed
            || (in_array($this->status, [EventStatus::RegistrationOpen, EventStatus::Ongoing], true)
                && ! $this->acceptsPublicRegistration()));
    }

    /**
     * Can the event be finalized (moved from Ongoing to Completed)?
     *
     * The event must be Ongoing and at least one hour past its
     * scheduled start. Finalization is
     * terminal: the workflow's allowedTransitions() gives Completed an
     * empty list, so a finalized event cannot be reopened. The only
     * ways to change a completed event's data are admin event deletion
     * or a direct database edit.
     *
     * Placed alongside canCloseRegistration() so the terminal state
     * machine transitions read as a unit: Draft → RegistrationOpen →
     * RegistrationClosed → Ongoing → Completed.
     *
     * Running event only.
     */
    public function canFinalize(): bool
    {
        if (
            ! $this->isRunningEvent()
            || $this->status !== EventStatus::Ongoing
            || $this->event_date === null
            || $this->start_time === null
        ) {
            return false;
        }

        $finalizationAvailableAt = $this->event_date
            ->copy()
            ->setTimeFromTimeString($this->start_time->format('H:i'))
            ->addHour();

        return now()->greaterThanOrEqualTo($finalizationAvailableAt);
    }

    /**
     * Can attendance be recorded against this event's registrations?
     *
     * Running event only. Church event attendance is recorded through
     * the Services, Fellowship, and Discipleship modules against their
     * own tables, not against registration rows.
     */
    public function canRecordAttendance(): bool
    {
        return $this->isRunningEvent() && in_array($this->status, [
            EventStatus::RegistrationOpen,
            EventStatus::RegistrationClosed,
            EventStatus::Ongoing,
        ], true);
    }

    public function isAttendanceReadOnly(): bool
    {
        return $this->isRunningEvent()
            ? in_array($this->status, [EventStatus::Completed, EventStatus::Cancelled], true)
            : ($this->isChurchEvent()
                && in_array($this->church_status, [ChurchEventStatus::Completed, ChurchEventStatus::Cancelled], true));
    }

    /**
     * Can attendance be recorded for this event right now?
     *
     * Composes the status gate (canRecordAttendance()) with a
     * time-based window that opens one hour before the event's
     * start_time.
     *
     * Replaces the day-granular canRecordAttendanceToday(), which
     * allowed marking from midnight of event day — six hours early
     * for an event starting at 06:00. See
     * docs/attendance-redesign.md section 3.
     *
     * The window closes when the status leaves the allowed set.
     * Completed events stay viewable (canViewAttendance) but not
     * markable. An admin force-open toggle is handled at the
     * controller layer; this method returns the strict truth.
     *
     * Inherits the running-event kind guard through
     * canRecordAttendance(). No explicit isRunningEvent() check is
     * needed here -- if canRecordAttendance() returns false, the
     * first early-return fires before the time window is evaluated.
     */
    public function canRecordAttendanceNow(): bool
    {
        if (! $this->canRecordAttendance()) {
            return false;
        }

        if ($this->event_date === null) {
            return false;
        }

        return now()->greaterThanOrEqualTo($this->attendanceWindowOpensAt());
    }

    /**
     * The moment marking opens: start_time minus
     * ATTENDANCE_WINDOW_MINUTES_BEFORE.
     *
     * If start_time is null, falls back to midnight of event_date —
     * the same effective behavior as the day-granular gate this
     * replaces. An event without a start_time is almost certainly an
     * oversight; the fallback is defensive, not ideal.
     *
     * If start_time is earlier than the window buffer (e.g. 00:30
     * minus 1 hour), the returned moment falls on the previous day.
     * That is correct — a race starting at 00:30 should be markable
     * from 23:30 the evening before.
     *
     * Return type is CarbonInterface because Laravel's date cast
     * (and the app's global date preference) can produce either
     * Carbon or CarbonImmutable. Both implement CarbonInterface.
     */
    protected function attendanceWindowOpensAt(): CarbonInterface
    {
        $opensAt = $this->event_date->copy()->startOfDay();

        if ($this->start_time !== null) {
            $opensAt = $opensAt
                ->setTime(
                    (int) $this->start_time->format('H'),
                    (int) $this->start_time->format('i'),
                )
                ->subMinutes(self::ATTENDANCE_WINDOW_MINUTES_BEFORE);
        }

        return $opensAt;
    }

    /**
     * Can the attendance page be viewed for this event?
     *
     * Broader than canRecordAttendanceNow(). Adds Completed and
     * Cancelled to the viewable set so the organizer can review
     * attendance history. Marking is separately gated by status.
     *
     * Excluded: draft and configured. Their attendance data
     * is meaningless or does not exist.
     *
     * Date gate: a future event returns false. A completed event has
     * a past event_date by definition, so the gate is trivially
     * satisfied.
     *
     * Running event only. Church event attendance is recorded through
     * the church module surfaces, not the running-event attendance
     * page.
     */
    public function canViewAttendance(): bool
    {
        if (! $this->isRunningEvent()) {
            return false;
        }

        $viewableStatuses = [
            EventStatus::RegistrationOpen,
            EventStatus::RegistrationClosed,
            EventStatus::Ongoing,
            EventStatus::Completed,
            EventStatus::Cancelled,
        ];

        if (! in_array($this->status, $viewableStatuses, true)) {
            return false;
        }

        if ($this->event_date === null) {
            return false;
        }

        return $this->event_date->lte(today()) || now()->greaterThanOrEqualTo($this->attendanceWindowOpensAt());
    }

    public function isCommunityRun(): bool
    {
        return $this->event_type === EventType::CommunityRun;
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
