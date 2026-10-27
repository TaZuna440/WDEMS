<?php

namespace App\Concerns;

use App\Enums\ChurchCategory;
use App\Enums\ChurchEventStatus;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\Event;
use App\Support\ChurchSpecialistCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait EventValidationRules
{
    use HumanNameQualityRules;

    /**
     * Minimum duration of an event, in minutes, when end_time is set.
     * Mirrored in resources/js/lib/event-validation.ts as
     * MIN_EVENT_DURATION_MINUTES. Keep both in sync.
     */
    public const MIN_EVENT_DURATION_MINUTES = 60;

    /**
     * Maximum number of years into the future an event may be scheduled.
     * Mirrored in resources/js/lib/event-validation.ts as
     * MAX_EVENT_YEARS_AHEAD. Keep both in sync.
     */
    public const MAX_EVENT_YEARS_AHEAD = 2;

    /**
     * Pattern for accepting Google Maps URLs only.
     *
     * Accepted forms:
     *   - https://www.google.com/maps/place/...
     *   - https://google.com/maps/...
     *   - https://www.google.com.ph/maps/...  (regional domains)
     *   - https://maps.google.com/maps?q=...   (legacy query format)
     *   - https://maps.app.goo.gl/...          (mobile share short link)
     *   - https://goo.gl/maps/...              (legacy short link)
     *
     * Non-Google hosts, fake google-prefixed hosts, and Google URLs
     * without a /maps path are all rejected.
     *
     * Mirrored in resources/js/lib/event-validation.ts as
     * GOOGLE_MAPS_URL_PATTERN. Keep both in sync.
     */
    protected function googleMapsUrlPattern(): string
    {
        return '/^https?:\/\/(?:(?:www\.|maps\.)?google\.[a-z]{2,}(?:\.[a-z]{2})?\/maps(?:\/|\?|#)|maps\.app\.goo\.gl\/|goo\.gl\/maps\/)\S+$/i';
    }

    /**
     * Resolve the event kind for the current request.
     *
     * Defaults to 'running' when the input is absent, preserving
     * running-event behavior for callers that predate the
     * discriminator column.
     */
    protected function currentEventKind(): string
    {
        $kind = $this->input('event_kind');

        return is_string($kind) && $kind !== ''
            ? $kind
            : Event::KIND_RUNNING;
    }

    /**
     * Get the validation rules used to validate events.
     *
     * Rules are conditional on the event_kind field:
     *
     *   running  -- event_type must be CommunityRun;
     *               distance_value and distance_unit are required;
     *               church_category and church_status are nullable.
     *
     *   church   -- event_type must be Church; distance fields are
     *               nullable; church_category and church_status are
     *               required.
     *
     * event_kind itself is nullable so existing callers that omit
     * it continue to behave as running events.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function eventRules(): array
    {
        $isChurch = $this->currentEventKind() === Event::KIND_CHURCH;

        $kindRules = $isChurch
            ? [
                'event_type' => ['required', Rule::in([EventType::Church->value])],
                'church_category' => ['required', Rule::enum(ChurchCategory::class)],
                'church_status' => ['required', Rule::enum(ChurchEventStatus::class)],
            ]
            : [
                'event_type' => ['required', Rule::in(array_map(
                    fn (EventType $type): string => $type->value,
                    EventType::runningTypes(),
                ))],
                'church_category' => ['nullable', Rule::enum(ChurchCategory::class)],
                'church_status' => ['nullable', Rule::enum(ChurchEventStatus::class)],
            ];

        $distanceRules = $isChurch
            ? [
                'distance_value' => ['nullable', 'numeric', 'min:0.01', 'max:9999.99'],
                'distance_unit' => ['nullable', Rule::in(['km', 'mi'])],
            ]
            : [
                'distance_value' => ['required', 'numeric', 'min:0.01', 'max:9999.99'],
                'distance_unit' => ['required', Rule::in(['km', 'mi'])],
            ];

        $specializedChurchRules = $isChurch
            ? [
                'emergency_contact_name' => ['nullable', 'string', 'max:255'],
                'emergency_contact_number' => ['nullable', 'string', 'max:32'],
                'medical_notes' => ['nullable', 'string', 'max:5000'],
                'parental_consent' => ['nullable', 'boolean'],
                'child_name' => ['nullable', 'string', 'max:255'],
                'mother_name' => ['nullable', 'string', 'max:255'],
                'father_name' => ['nullable', 'string', 'max:255'],
                'officiating_pastor' => ['nullable', 'string', 'max:255'],
                'assigned_teacher' => ['nullable', 'string', 'max:255'],
                'program_day_session' => ['nullable', 'string', 'max:255'],
            ]
            : [
                'emergency_contact_name' => ['exclude'],
                'emergency_contact_number' => ['exclude'],
                'medical_notes' => ['exclude'],
                'parental_consent' => ['exclude'],
                'child_name' => ['exclude'],
                'mother_name' => ['exclude'],
                'father_name' => ['exclude'],
                'officiating_pastor' => ['exclude'],
                'assigned_teacher' => ['exclude'],
                'program_day_session' => ['exclude'],
            ];

        return [
            'event_kind' => [
                'nullable',
                Rule::in([Event::KIND_RUNNING, Event::KIND_CHURCH]),
            ],
            'specialist_workflow' => ['sometimes', 'boolean'],
            'church_activity_category' => [
                'nullable',
                'required_if:specialist_workflow,1',
                'string',
                Rule::in(ChurchSpecialistCatalog::activityLabelsForChurchCategory($this->input('church_category'))),
            ],

            // Step 1 -- Basics (kind-dependent)
            ...$kindRules,
            'event_name' => $this->eventNameRules(),
            'description' => ['nullable', 'string', 'max:2000'],
            'coordinator' => $isChurch ? ['nullable', 'string', 'max:255'] : ['exclude'],

            // Step 2 -- Schedule
            'event_date' => [
                'required',
                'date',
                'after_or_equal:today',
                'before:+'.self::MAX_EVENT_YEARS_AHEAD.' years',
            ],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            ...$distanceRules,

            // Step 3 -- Venue
            'venue' => ['required', 'string', 'min:3', 'max:255'],
            'venue_address' => ['required', 'string', 'min:5', 'max:500'],
            'venue_map_url' => [
                'nullable',
                'string',
                'max:500',
                'regex:'.$this->googleMapsUrlPattern(),
            ],
            'venue_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'venue_longitude' => ['nullable', 'numeric', 'between:-180,180'],

            // Step 4 -- Extras
            'partners' => ['nullable', 'array', 'max:20'],
            'partners.*.name' => [
                'required_with:partners',
                'string',
                'min:3',
                'max:255',
                ...$this->humanNameQualityRules(),
            ],
            'partners.*.type' => ['required_with:partners', Rule::in($this->partnerTypes())],

            // Accessibility
            'walkers_welcome' => ['boolean'],
            'all_paces_welcome' => ['boolean'],
            'all_ages_welcome' => ['boolean'],
            'stroller_friendly' => ['boolean'],
            'wheelchair_accessible' => ['boolean'],
            'sweeper_present' => ['boolean'],
            'service_animals_allowed' => ['boolean'],
            'leashed_pets_allowed' => ['boolean'],
            ...$specializedChurchRules,
        ];
    }

    /**
     * Reject an event whose combined date and start time have already
     * passed.
     *
     * The `after_or_equal:today` rule on event_date permits same-day
     * events — a legitimate case for spontaneous community runs. But
     * "today at 09:00" when it is already 14:00 is not a real event.
     * This method catches the combined datetime.
     *
     * Runs only when the date and time are individually valid — if
     * either already has an error, this method returns early so the
     * user sees one specific message per problem, not two.
     *
     * Client-side mirror in
     * resources/js/lib/event-validation.ts::validateSchedule. The
     * client uses the browser's timezone; this check uses
     * config('app.timezone'). Where the two differ, whichever rejects
     * first wins.
     *
     * Registered by EventRequest::withValidator().
     */
    protected function validateEventStartIsInFuture(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            // If the date already failed its own rules (missing, invalid
            // format, before today, or beyond the max), do not pile on.
            if ($v->errors()->has('event_date')) {
                return;
            }

            // If start_time already failed format rules, do not pile on.
            if ($v->errors()->has('start_time')) {
                return;
            }

            $data = $v->getData();
            $date = $data['event_date'] ?? null;
            $time = $data['start_time'] ?? null;

            if (! is_string($date) || ! is_string($time)) {
                return;
            }

            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return;
            }

            if (! preg_match('/^\d{2}:\d{2}$/', $time)) {
                return;
            }

            $start = Date::parse("{$date} {$time}");

            if ($start->isPast()) {
                $v->errors()->add(
                    'start_time',
                    'Start time cannot be in the past.',
                );
            }
        });
    }

    /**
     * Additional event validation that runs after the standard rules.
     *
     * Enforces the minimum event duration when end_time is present.
     * The ordering check (end after start) is handled by the
     * `after:start_time` rule on end_time; this method only handles
     * the "too short" case, and only when ordering is already correct.
     *
     * Registered by EventRequest::withValidator().
     */
    protected function validateEventDuration(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $data = $v->getData();
            $start = $data['start_time'] ?? null;
            $end = $data['end_time'] ?? null;

            if (! is_string($start) || ! is_string($end)) {
                return;
            }

            if ($start === '' || $end === '') {
                return;
            }

            if (! preg_match('/^\d{2}:\d{2}$/', $start) || ! preg_match('/^\d{2}:\d{2}$/', $end)) {
                return;
            }

            // If ordering is already wrong, skip the min-duration check.
            // The `after:start_time` rule will already report it.
            if ($end <= $start) {
                return;
            }

            [$sh, $sm] = array_map('intval', explode(':', $start));
            [$eh, $em] = array_map('intval', explode(':', $end));
            $durationMinutes = ($eh * 60 + $em) - ($sh * 60 + $sm);

            if ($durationMinutes < self::MIN_EVENT_DURATION_MINUTES) {
                $v->errors()->add(
                    'end_time',
                    'End time must be at least 1 hour after start time.',
                );
            }
        });
    }

    /**
     * Reject duplicate partners.
     *
     * Two partners are duplicates iff BOTH the name and the type match,
     * after normalization (trim + lowercase). Same name with different
     * type is allowed — a partner can play two roles at one event.
     *
     * Mirrors the duplicate pass in
     * resources/js/lib/event-validation.ts::validateExtras. Keep both
     * in sync.
     *
     * Registered by EventRequest::withValidator().
     */
    protected function validatePartnerDuplicates(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $data = $v->getData();
            $partners = $data['partners'] ?? null;

            if (! is_array($partners)) {
                return;
            }

            $seen = [];
            $duplicateCount = 0;

            foreach ($partners as $index => $partner) {
                if (! is_array($partner)) {
                    continue;
                }

                $name = strtolower(trim((string) ($partner['name'] ?? '')));
                $type = strtolower(trim((string) ($partner['type'] ?? '')));

                // Rows missing name or type have their own errors from
                // the rules array. Skip so we do not double-report.
                if ($name === '' || $type === '') {
                    continue;
                }

                $key = $name.'|'.$type;

                if (isset($seen[$key])) {
                    $v->errors()->add(
                        "partners.{$index}.name",
                        'Duplicate of row '.($seen[$key] + 1).' — same name and type.',
                    );
                    $duplicateCount++;
                } else {
                    $seen[$key] = $index;
                }
            }

            if ($duplicateCount > 0) {
                $v->errors()->add(
                    'partners',
                    $duplicateCount === 1
                        ? 'One partner has the same name and type as another row.'
                        : "{$duplicateCount} partners have the same name and type as other rows.",
                );
            }
        });
    }

    /**
     * Reject duplicate event names for the same creator.
     *
     * Two events of the same kind created by the same user cannot
     * share a name while either is still active.
     *
     * Scoped by event_kind: a running event and a church event can
     * share a name (they are different namespaces). Within a single
     * kind, the active name must be unique per creator.
     *
     * Running events use EventStatus for the "still active" check;
     * church events use ChurchEventStatus. The two enums have
     * different terminal states, so the check branches.
     *
     * Excluded on update when the current event is the one being
     * edited — editing an existing event must not reject against its
     * own name.
     *
     * Registered by EventRequest::withValidator().
     */
    protected function validateEventNameUnique(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $data = $v->getData();
            $name = $data['event_name'] ?? null;

            if (! is_string($name)) {
                return;
            }

            $trimmed = trim($name);

            if ($trimmed === '') {
                return;
            }

            $userId = auth()->id();

            if ($userId === null) {
                return;
            }

            $normalized = strtolower($trimmed);
            $kind = $this->currentEventKind();

            $query = Event::query()
                ->where('created_by', $userId)
                ->where('event_kind', $kind)
                ->whereRaw('LOWER(TRIM(event_name)) = ?', [$normalized]);

            if ($kind === Event::KIND_CHURCH) {
                $query->whereNotIn('church_status', [
                    ChurchEventStatus::Completed->value,
                    ChurchEventStatus::Cancelled->value,
                ]);
            } else {
                $query->whereNotIn('status', [
                    EventStatus::Completed->value,
                    EventStatus::Cancelled->value,
                ]);
            }

            // On update, exclude the event being edited.
            $routeEvent = request()->route('event');

            if ($routeEvent instanceof Event) {
                $query->where('id', '!=', $routeEvent->id);
            }

            if ($query->exists()) {
                $v->errors()->add(
                    'event_name',
                    'You already have an active event with this name. '
                    .'Finish or cancel it, or use a different name.',
                );
            }
        });
    }

    /**
     * Custom messages for event fields that use Laravel defaults that
     * do not read well.
     *
     * @return array<string, string>
     */
    protected function eventMessages(): array
    {
        return [
            'end_time.after' => 'End time must be after start time.',
            'event_date.after_or_equal' => 'Event date cannot be in the past.',
            'event_date.before' => 'Event date cannot be more than 2 years from today.',
            'venue_map_url.regex' => 'Map link must be a Google Maps URL, like https://www.google.com/maps/place/... or https://maps.app.goo.gl/...',
            'event_kind.in' => 'Event kind must be running or church.',
            'event_type.in' => 'The selected event type does not match the event kind.',
            'church_category.required' => 'Church category is required.',
            'church_category.enum' => 'The selected church category is not valid.',
            'church_status.required' => 'Church status is required.',
        ];
    }

    /**
     * Validation rules for the event name.
     *
     * Composes length rules with the shared humanNameQualityRules().
     *
     * @return array<int, string>
     */
    protected function eventNameRules(): array
    {
        return [
            'required',
            'string',
            'min:3',
            'max:100',
            ...$this->humanNameQualityRules(),
        ];
    }

    /**
     * Allowed partner type values.
     *
     * @return array<int, string>
     */
    protected function partnerTypes(): array
    {
        return [
            'host',
            'sponsor',
            'food',
            'beverage',
            'medical',
            'organization',
            'media',
            'logistics',
            'other',
        ];
    }
}
