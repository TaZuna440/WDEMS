<?php

namespace App\Http\Requests\Services;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a recurring-schedule batch.
 *
 * Generates N occurrences from one definition:
 *   pattern: 'weekly' or 'fortnightly'
 *   day_of_week: 0 (Sunday) through 6 (Saturday)
 *   first_date: the first occurrence's date; must fall on day_of_week
 *   weeks: how many occurrences to generate (1-52)
 *
 * The controller enforces the day-of-week match; the request only
 * validates shape.
 */
class StoreRecurringServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'pattern' => ['required', Rule::in(['weekly', 'fortnightly'])],
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'first_date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:+2 years'],
            'weeks' => ['required', 'integer', 'min:1', 'max:52'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'service_leader' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'pattern.required' => 'Choose weekly or fortnightly.',
            'day_of_week.required' => 'Day of the week is required.',
            'first_date.required' => 'First service date is required.',
            'first_date.after_or_equal' => 'First date cannot be in the past.',
            'weeks.required' => 'Number of occurrences is required.',
            'weeks.max' => 'You can generate up to 52 occurrences at a time.',
        ];
    }
}
