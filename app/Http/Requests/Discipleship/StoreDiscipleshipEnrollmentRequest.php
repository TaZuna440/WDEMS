<?php

namespace App\Http\Requests\Discipleship;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate an enrollment creation.
 *
 * program_id is not in the payload -- the program comes from the
 * route ({program:slug} bound to DiscipleshipProgram). The
 * controller reads it from the route and stamps program_id on
 * the row.
 *
 * batch_id is optional and, when provided, must belong to the
 * same program. The controller asserts this cross-table
 * constraint (a Rule::exists would not enforce "belongs to this
 * program" without a closure).
 *
 * enrolled_at defaults to now() when omitted. Allowed up to today
 * (retroactive logging), not into the future.
 *
 * Phase 5C.
 */
class StoreDiscipleshipEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'participant_id' => ['required', 'integer', 'exists:participants,id'],
            'batch_id' => ['nullable', 'integer', 'exists:discipleship_batches,id'],
            'enrolled_at' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'participant_id.required' => 'Choose a participant to enroll.',
            'participant_id.exists' => 'The selected participant does not exist.',
            'batch_id.exists' => 'The selected batch does not exist.',
            'enrolled_at.before_or_equal' => 'Enrollment date cannot be in the future.',
        ];
    }
}
