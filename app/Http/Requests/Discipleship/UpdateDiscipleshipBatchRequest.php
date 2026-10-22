<?php

namespace App\Http\Requests\Discipleship;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate a Discipleship batch update.
 *
 * Rules are identical to StoreDiscipleshipBatchRequest today. Kept
 * as a separate class so the two can diverge later without a
 * rename.
 *
 * The batch's parent program is immutable -- not in the payload.
 *
 * Phase 5B.
 */
class UpdateDiscipleshipBatchRequest extends FormRequest
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
            'start_date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:+2 years'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Batch name is required.',
            'start_date.required' => 'Start date is required.',
            'start_date.after_or_equal' => 'Start date cannot be in the past.',
            'start_date.before_or_equal' => 'Start date cannot be more than 2 years from today.',
        ];
    }
}
