<?php

namespace App\Concerns;

use App\Enums\EventType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait EventValidationRules
{
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
            'event_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'distance_value' => ['required', 'numeric', 'min:0.01', 'max:9999.99'],
            'distance_unit' => ['required', Rule::in(['km', 'mi'])],
            'course_url' => ['nullable', 'url', 'max:500'],

            // Step 3 — Venue
            'venue' => ['required', 'string', 'max:255'],
            'venue_address' => ['required', 'string', 'max:500'],
            'venue_latitude' => ['required', 'numeric', 'between:-90,90'],
            'venue_longitude' => ['required', 'numeric', 'between:-180,180'],

            // Step 4 — Extras
            'rsvp_required' => ['boolean'],
            'partners' => ['nullable', 'array'],
            'partners.*.name' => ['required_with:partners', 'string', 'max:255'],
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
     * Validation rules for the event name.
     *
     * Catches the most common human errors:
     *  - too short (min 3)
     *  - doesn't start with a letter or number
     *  - has no vowels (keyboard mashing like "dfdsfdsfd")
     *  - has no consonants (only vowels)
     *  - 3+ identical characters in a row ("aaaa")
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
            'regex:/^[A-Za-z0-9]/',
            'regex:/[aeiouyAEIOUY]/',
            'regex:/[bcdfghjklmnpqrstvwxzBCDFGHJKLMNPQRSTVWXZ]/',
            'not_regex:/(.)\1\1/',
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
