<?php

namespace App\Http\Requests\Fellowship;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update status, group, or notes on an existing Fellowship
 * attendance row.
 *
 * attendance_status is required. fellowship_group_id is optional --
 * a participant may be marked without a group. When provided, the
 * controller checks it belongs to the same event.
 *
 * The status is a short string rather than an enum so the allowed
 * set can grow without a schema change; the Rule::in list here is
 * the source of truth for the validation boundary.
 *
 * Phase 4.
 */
class MarkFellowshipAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'attendance_status' => ['required', Rule::in(['present', 'absent'])],
            'fellowship_group_id' => ['nullable', 'integer', 'exists:fellowship_groups,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'attendance_status.required' => 'Attendance status is required.',
            'attendance_status.in' => 'Attendance status must be present or absent.',
            'fellowship_group_id.exists' => 'The selected group does not exist.',
        ];
    }
}
