<?php

namespace App\Http\Requests\Services;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update the attendance_status on an existing attendance row.
 *
 * Used by both the Members and First-Timers tracks. The status is a
 * short string rather than an enum so the allowed set can grow
 * without a schema change; the Rule::in list here is the source of
 * truth for the validation boundary.
 */
class MarkServiceAttendanceRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'attendance_status.required' => 'Attendance status is required.',
            'attendance_status.in' => 'Attendance status must be present or absent.',
        ];
    }
}
