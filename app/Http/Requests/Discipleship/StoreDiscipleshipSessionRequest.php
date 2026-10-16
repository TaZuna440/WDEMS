<?php

namespace App\Http\Requests\Discipleship;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate a session creation on a batch.
 *
 * batch_id is not in the payload -- the batch comes from the
 * route ({batch} bound to DiscipleshipBatch). The controller
 * reads it from the route.
 *
 * session_date has an upper bound but no lower bound. Past dates
 * are allowed so retroactive session logging works -- same policy
 * as batches and events elsewhere.
 *
 * Phase 5D.
 */
class StoreDiscipleshipSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'session_date' => ['required', 'date', 'before_or_equal:+2 years'],
            'topic' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'session_date.required' => 'Session date is required.',
            'session_date.before_or_equal' => 'Session date cannot be more than 2 years from today.',
            'topic.required' => 'Session topic is required.',
        ];
    }
}
