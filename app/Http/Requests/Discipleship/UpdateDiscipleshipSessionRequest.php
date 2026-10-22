<?php

namespace App\Http\Requests\Discipleship;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate a session update.
 *
 * Rules are identical to StoreDiscipleshipSessionRequest today.
 * Kept as a separate class so the two can diverge later without
 * a rename. The session's parent batch is immutable -- not in
 * the payload.
 *
 * Phase 5D.
 */
class UpdateDiscipleshipSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'session_date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:+2 years'],
            'topic' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'session_date.required' => 'Session date is required.',
            'session_date.after_or_equal' => 'Session date cannot be in the past.',
            'session_date.before_or_equal' => 'Session date cannot be more than 2 years from today.',
            'topic.required' => 'Session topic is required.',
        ];
    }
}
