<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrationFieldRequest;
use App\Models\Event;
use App\Models\RegistrationField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationFormController extends Controller
{
    /**
     * Show the registration form builder for an event.
     *
     * The page renders the six common participant fields (read-only)
     * and the event's custom fields (editable via drag-to-reorder).
     * Editing is allowed only while the event is in Draft.
     */
    public function show(Event $event): Response
    {
        $event->load(['registrationFields' => function ($query): void {
            $query->orderBy('display_order');
        }]);

        return Inertia::render('events/registration-form', [
            'event' => [
                'id' => $event->id,
                'event_name' => $event->event_name,
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
                'can_edit' => $event->canEditRegistrationForm(),
            ],
            'fields' => $event->registrationFields
                ->map(fn (RegistrationField $field) => [
                    'id' => $field->id,
                    'label' => $field->label,
                    'field_type' => $field->field_type,
                    'options' => $field->options ?? [],
                    'is_required' => $field->is_required,
                    'validation_rules' => $field->validation_rules,
                    'display_order' => $field->display_order,
                ])
                ->values(),
        ]);
    }

    /**
     * Replace the event's registration form in one batch.
     *
     * The payload is the entire form definition, sent as a `fields`
     * array. This method deletes all existing rows and re-inserts from
     * the payload, assigning `display_order` from array position.
     *
     * Delete-and-reinsert is used instead of a diff-based upsert
     * because:
     *   - The form is editable only while Draft. No responses exist
     *     yet, so row IDs are not referenced anywhere.
     *   - Drag-reorder changes positions freely; a diff would have to
     *     detect moves, and the algorithm is not worth the complexity
     *     for a table that will be rewritten anyway.
     *   - Once the event moves past Draft (form locked), no further
     *     saves happen. IDs are stable from that moment.
     */
    public function update(RegistrationFieldRequest $request, Event $event): RedirectResponse
    {
        if (! $event->canEditRegistrationForm()) {
            abort(403, 'This registration form can no longer be edited.');
        }

        $fields = $request->validated()['fields'] ?? [];

        DB::transaction(function () use ($event, $fields): void {
            $event->registrationFields()->delete();

            foreach ($fields as $index => $field) {
                $event->registrationFields()->create([
                    'label' => $field['label'],
                    'field_type' => $field['field_type'],
                    'options' => $field['options'] ?? null,
                    'validation_rules' => $field['validation_rules'] ?? null,
                    'is_required' => $field['is_required'] ?? false,
                    'display_order' => $index,
                ]);
            }
        });

        return redirect()->route('events.registration-form.show', $event);
    }
}
