<?php

namespace App\Http\Requests\Fellowship;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate a Fellowship event creation.
 *
 * The fellowship_type is not in the payload -- it comes from the
 * route ({type}). The controller reads it from the route.
 *
 * Scheduling dates are limited to today through two years ahead.
 *
 * Phase 4.
 */
class StoreFellowshipEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'event_date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:+2 years'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'venue' => ['nullable', 'string', 'max:255'],
            'facilitator' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Fellowship name is required.',
            'event_date.required' => 'Fellowship date is required.',
            'event_date.after_or_equal' => 'Fellowship date cannot be in the past.',
            'event_date.before_or_equal' => 'Fellowship date cannot be more than 2 years from today.',
            'end_time.after' => 'End time must be after start time.',
        ];
    }
}
