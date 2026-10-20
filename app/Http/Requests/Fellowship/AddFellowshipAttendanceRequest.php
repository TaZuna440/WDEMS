<?php

namespace App\Http\Requests\Fellowship;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Add a participant to a Fellowship event's attendance.
 *
 * Two entry paths:
 *   1. participant_id is provided -- an existing participant is
 *      linked. Name fields are ignored.
 *   2. participant_id is null -- a new participant is created from
 *      the name/age/birthday/address/contact fields.
 *
 * Unlike Services, Fellowship has one attendance track. The
 * optional fellowship_group_id assigns the participant to a group
 * within this event. The controller enforces that the group
 * belongs to the same event.
 *
 * No email field -- the mockup's add dialog does not collect one.
 * Same as AddServiceAttendanceRequest.
 *
 * Phase 4.
 */
class AddFellowshipAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'participant_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('participants', 'id')->whereNull('provisional_event_id')],

            'first_name' => ['nullable', 'required_if:participant_id,null', 'string', 'max:255'],
            'last_name' => ['nullable', 'required_if:participant_id,null', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:50'],
            'suffix' => ['nullable', 'string', 'max:10'],
            'age' => ['nullable', 'integer', 'min:1', 'max:120'],
            'birthday' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_number' => ['nullable', 'string', 'max:50'],

            'fellowship_group_id' => ['nullable', 'integer', 'exists:fellowship_groups,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'first_name.required_if' => 'First name is required when creating a new participant.',
            'last_name.required_if' => 'Last name is required when creating a new participant.',
            'fellowship_group_id.exists' => 'The selected group does not exist.',
        ];
    }
}
