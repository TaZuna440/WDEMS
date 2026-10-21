<?php

namespace App\Http\Requests\Services;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add a participant to a service occurrence's attendance.
 *
 * Two entry paths:
 *   1. participant_id is provided -- an existing participant is
 *      linked. Name fields are ignored.
 *   2. participant_id is null -- a new participant is created from
 *      the name/age/birthday/address/contact fields.
 *
 * The `track` field selects which attendance table receives the row
 * (service_attendance_members or service_attendance_first_timers).
 * The controller enforces that the participant is not already on the
 * other track for the same occurrence.
 */
class AddServiceAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'track' => ['required', Rule::in(['member', 'first_timer'])],
            'participant_id' => ['nullable', 'integer', Rule::exists('participants', 'id')->where('pool', 'church')->whereNull('provisional_event_id')],

            // Only required when participant_id is null (new participant).
            'first_name' => ['nullable', 'required_if:participant_id,null', 'string', 'max:255'],
            'last_name' => ['nullable', 'required_if:participant_id,null', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:50'],
            'suffix' => ['nullable', 'string', 'max:10'],
            'age' => ['nullable', 'integer', 'min:1', 'max:120'],
            'birthday' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_number' => ['nullable', 'string', 'max:50'],

            // Members-track only.
            'cell_leader' => ['nullable', 'string', 'max:255'],

            // First-timers track only.
            'invited_by' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'track.required' => 'Attendance track is required.',
            'first_name.required_if' => 'First name is required when creating a new participant.',
            'last_name.required_if' => 'Last name is required when creating a new participant.',
        ];
    }
}
