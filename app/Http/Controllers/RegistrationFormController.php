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
     * The page renders the six common participant fields (read-only
     * except for the toggleable required/optional state) and the
     * event's custom fields (editable via drag-to-reorder). Editing
     * is allowed only while the event is in Draft.
     *
     * `common_field_requirements` is the resolved state — always
     * three keys, values reflecting the current event. The frontend
     * does not need to know about the NULL-as-default convention.
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
            'common_field_requirements' => $this->resolveRequirements($event),
        ]);
    }

    /**
     * Replace the event's registration form in one batch.
     *
     * The payload is the entire form definition — the custom `fields`
     * array plus a `common_field_requirements` map. This method
     * deletes all existing custom-field rows and re-inserts from the
     * payload, assigning `display_order` from array position.
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
     *
     * On every successful save, `events.registration_form_saved_at` is
     * stamped. This is the sole signal that a form exists for the event
     * — the Open Registration action is gated on a non-null value.
     * Saving an empty form (just the common fields) still counts:
     * the organizer has explicitly decided what the form contains.
     *
     * The requirements map is compressed to NULL when every toggleable
     * field is required — the default. That preserves the column's
     * "NULL means all required" invariant and keeps payloads small.
     */
    public function update(RegistrationFieldRequest $request, Event $event): RedirectResponse
    {
        if (! $event->canEditRegistrationForm()) {
            abort(403, 'This registration form can no longer be edited.');
        }

        $fields = $request->validated()['fields'] ?? [];
        $requirements = $this->compressRequirements(
            $request->validated()['common_field_requirements'] ?? null,
        );

        DB::transaction(function () use ($event, $fields, $requirements): void {
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

            $event->registration_common_field_requirements = $requirements;
            $event->registration_form_saved_at = now();
            $event->save();
        });

        return redirect()->route('events.show', $event);
    }

    /**
     * Resolve the current requirements into a three-key map for the
     * page. Uses Event::isCommonFieldRequired() so always-required
     * fields are included for completeness even though the frontend
     * will not render a toggle for them.
     *
     * @return array<string, bool>
     */
    private function resolveRequirements(Event $event): array
    {
        $resolved = [];

        foreach (Event::COMMON_FIELDS_TOGGLEABLE as $field) {
            $resolved[$field] = $event->isCommonFieldRequired($field);
        }

        return $resolved;
    }

    /**
     * Compress the requirements map to NULL when every toggleable
     * field is required. Also filters out any keys not in
     * Event::COMMON_FIELDS_TOGGLEABLE — the request rules should
     * already reject them, but the controller is the last line of
     * defense for what reaches the database.
     *
     * @param  array<string, bool>|null  $requirements
     * @return array<string, bool>|null
     */
    private function compressRequirements(?array $requirements): ?array
    {
        if ($requirements === null) {
            return null;
        }

        $filtered = [];

        foreach (Event::COMMON_FIELDS_TOGGLEABLE as $field) {
            $filtered[$field] = (bool) ($requirements[$field] ?? true);
        }

        if (! in_array(false, $filtered, true)) {
            return null;
        }

        return $filtered;
    }
}
