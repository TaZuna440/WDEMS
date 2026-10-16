<?php

namespace App\Http\Requests\Fellowship;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a Fellowship group update.
 *
 * Rules are identical to StoreFellowshipGroupRequest except the
 * unique check ignores the group currently being edited -- a
 * rename that keeps the same name does not collide with itself.
 *
 * Phase 4.
 */
class UpdateFellowshipGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $event = $this->route('event');
        $eventId = is_object($event) ? $event->id : $event;

        $group = $this->route('group');
        $groupId = is_object($group) ? $group->id : $group;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('fellowship_groups', 'name')
                    ->where('fellowship_event_id', $eventId)
                    ->ignore($groupId),
            ],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Group name is required.',
            'name.unique' => 'This event already has a group with that name.',
        ];
    }
}
