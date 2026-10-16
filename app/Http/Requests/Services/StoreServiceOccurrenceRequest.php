<?php

namespace App\Http\Requests\Services;

use App\Enums\ServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a single manual occurrence creation.
 *
 * The service_type is not in the payload -- it comes from the route
 * ({type}). The request re-reads it from the route to run Rule::in
 * against the enum values, so a bad URL segment is caught here
 * rather than producing an enum cast error at the controller.
 */
class StoreServiceOccurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'occurrence_date' => ['required', 'date', 'before_or_equal:+2 years'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'service_leader' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'occurrence_date.required' => 'Service date is required.',
            'occurrence_date.before_or_equal' => 'Service date cannot be more than 2 years from today.',
            'end_time.after' => 'End time must be after start time.',
        ];
    }
}
