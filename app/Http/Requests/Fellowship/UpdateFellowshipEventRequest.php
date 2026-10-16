<?php

namespace App\Http\Requests\Fellowship;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate a Fellowship event update.
 *
 * Rules are identical to StoreFellowshipEventRequest today. Kept as
 * a separate class so the two can diverge later without a rename.
 * Same rationale as the split between StoreServiceOccurrenceRequest
 * and any future update counterpart.
 *
 * The fellowship_type is immutable after creation -- it is not in
 * the payload and not editable.
 *
 * Phase 4.
 */
class UpdateFellowshipEventRequest extends FormRequest
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
            'event_date' => ['required', 'date', 'before_or_equal:+2 years'],
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
            'event_date.before_or_equal' => 'Fellowship date cannot be more than 2 years from today.',
            'end_time.after' => 'End time must be after start time.',
        ];
    }
}
