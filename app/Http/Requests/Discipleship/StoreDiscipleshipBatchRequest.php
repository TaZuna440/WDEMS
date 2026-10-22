<?php

namespace App\Http\Requests\Discipleship;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate a Discipleship batch creation.
 *
 * program_id is not in the payload -- the program comes from the
 * route ({program:slug} bound to DiscipleshipProgram). The
 * controller reads it from the route and stamps program_id on the
 * created batch.
 *
 * Scheduling dates are limited to today through two years ahead.
 *
 * No unique constraint on (program_id, name) at the DB level; the
 * same reasoning applies here. Two batches can share a name.
 *
 * Phase 5B.
 */
class StoreDiscipleshipBatchRequest extends FormRequest
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
