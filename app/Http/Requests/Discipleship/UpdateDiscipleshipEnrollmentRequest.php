<?php

namespace App\Http\Requests\Discipleship;

use App\Enums\DiscipleshipEnrollmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate an enrollment update.
 *
 * The participant and program are immutable after creation --
 * neither is in the payload. What changes: status, batch pointer,
 * notes. Moving to a different batch of the same program is an
 * update, not a new enrollment (the unique on
 * (participant_id, program_id) would reject a second row anyway).
 *
 * batch_id is optional; when provided, the controller asserts it
 * belongs to the same program.
 *
 * Phase 5C.
 */
class UpdateDiscipleshipEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(DiscipleshipEnrollmentStatus::values())],
            'batch_id' => ['nullable', 'integer', 'exists:discipleship_batches,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status.required' => 'Enrollment status is required.',
            'status.in' => 'Enrollment status must be one of: enrolled, in_progress, completed, withdrawn, on_hold.',
            'batch_id.exists' => 'The selected batch does not exist.',
        ];
    }
}
