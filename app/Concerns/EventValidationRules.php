<?php

namespace App\Concerns;

use App\Enums\EventType;
use Illuminate\Contracts\Validation\ValidationRule;
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
     * Pattern for accepting user-pasted URLs.
     *
     * Requires:
     *   - http:// or https:// scheme
     *   - a host with at least one dot (rejects "https://fdhgdgfdgf")
     *   - a path, query, or fragment (rejects bare "https://maps.google.com")
     *   - at least one more character after that separator
     *
     * Mirrored in resources/js/lib/event-validation.ts as
     * HTTP_URL_PATTERN. Keep both in sync.
     */
    protected function httpUrlPattern(): string
    {
        return '/^https?:\/\/[a-z0-9-]+(\.[a-z0-9-]+)+[\/?#]\S+$/i';
    }

    /**
     * Get the validation rules used to validate events.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function eventRules(): array
    {
        return [
            // Step 1 — Basics
            'event_type' => ['required', Rule::enum(EventType::class)],
            'event_name' => $this->eventNameRules(),
            'description' => ['nullable', 'string', 'max:2000'],

            // Step 2 — Schedule
            'event_date' => [
                'required',
                'date',
                'after_or_equal:today',
                'before:+'.self::MAX_EVENT_YEARS_AHEAD.' years',
            ],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'distance_value' => ['required', 'numeric', 'min:0.01', 'max:9999.99'],
            'distance_unit' => ['required', Rule::in(['km', 'mi'])],
            'course_url' => [
                'nullable',
                'string',
                'max:500',
                'regex:'.$this->httpUrlPattern(),
            ],

            // Step 3 — Venue
            'venue' => ['required', 'string', 'min:3', 'max:255'],
            'venue_address' => ['required', 'string', 'min:5', 'max:500'],
            'venue_map_url' => [
                'nullable',
                'string',
                'max:500',
                'regex:'.$this->httpUrlPattern(),
            ],
            'venue_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'venue_longitude' => ['nullable', 'numeric', 'between:-180,180'],

            // Step 4 — Extras
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
            'quiet_space_available' => ['boolean'],
        ];
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
            'course_url.regex' => 'Course link must point to a specific location, like https://maps.app.goo.gl/abc123.',
            'venue_map_url.regex' => 'Map link must point to a specific location, like https://maps.app.goo.gl/abc123.',
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
