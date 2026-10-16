<?php

namespace App\Http\Requests\Fellowship;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a Fellowship group creation.
 *
 * A group belongs to exactly one event. Its name must be unique
 * within that event -- the UNIQUE(fellowship_event_id, name) index
 * enforces this at the DB level, and Rule::unique here produces a
 * friendly 422 rather than a QueryException.
 *
 * The fellowship_event_id comes from the route ({event} bound to
 * FellowshipEvent by the controller type-hint). It is not in the
 * payload. $this->route('event') returns the bound model.
 *
 * No name-quality rules. Fellowship group names are short
 * free-form labels -- "Parents", "Youth", "Children". The
 * humanNameQualityRules from EventValidationRules are tuned for
 * event names, not group labels. Deferred until the client asks.
 *
 * Phase 4.
 */
class StoreFellowshipGroupRequest extends FormRequest
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

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('fellowship_groups', 'name')
                    ->where('fellowship_event_id', $eventId),
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
