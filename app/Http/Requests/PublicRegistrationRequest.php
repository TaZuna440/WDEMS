<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

class PublicRegistrationRequest extends FormRequest
{
    /**
     * No auth on the public submission route. The route middleware
     * (EnsureRegistrationIsOpen) is the gate.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Six common participant fields, plus a `responses` map keyed by
     * registration_field_id. The shape of each response value depends
     * on the field's type — validated in withValidator(), not here,
     * because the rules are dynamic per event.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'contact_number' => ['required', 'string', 'max:50'],
            'age' => ['required', 'integer', 'min:1', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'responses' => ['nullable', 'array'],
            'responses.*' => ['nullable'],
        ];
    }

    /**
     * Per-event validation. Runs in two passes:
     *
     *   1. Every submitted response key must belong to the bound
     *      event's registration fields, and each non-empty value must
     *      match the field's type (and, for choice types, its options).
     *
     *   2. Every required field on the event must have a non-empty
     *      submitted value. This pass iterates the *fields*, not the
     *      responses — a required field the participant skipped
     *      produces no key in `responses`, so pass 1 would never see
     *      it. See the regression test
     *      "a required custom field with no value is rejected".
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $event = $this->route('event');

            if (! $event instanceof Event) {
                return;
            }

            $fields = $event->registrationFields()->get();
            $responses = $this->input('responses', []);

            if (! is_array($responses)) {
                $responses = [];
            }

            // Pass 1 — validate what was submitted.
            foreach ($responses as $fieldId => $value) {
                $field = $fields->firstWhere('id', (int) $fieldId);

                if ($field === null) {
                    $v->errors()->add(
                        "responses.{$fieldId}",
                        'This field does not belong to this event.',
                    );
                    continue;
                }

                if ($this->isEmpty($value)) {
                    continue;
                }

                $this->validateType(
                    $v,
                    (int) $fieldId,
                    $field->field_type,
                    $value,
                    $field->options ?? [],
                );
            }

            // Pass 2 — enforce required-ness against the field list,
            // not the submitted keys.
            foreach ($fields as $field) {
                if (! $field->is_required) {
                    continue;
                }

                $value = $responses[$field->id] ?? null;

                if ($this->isEmpty($value)) {
                    $v->errors()->add(
                        "responses.{$field->id}",
                        'This field is required.',
                    );
                }
            }
        });
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * @param array<int, string> $options
     */
    private function validateType($validator, int $fieldId, string $type, mixed $value, array $options): void
    {
        $key = "responses.{$fieldId}";

        switch ($type) {
            case 'text':
            case 'textarea':
            case 'email':
                if (! is_string($value)) {
                    $validator->errors()->add($key, 'Must be a string.');
                } elseif ($type === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $validator->errors()->add($key, 'Must be a valid email address.');
                }
                break;

            case 'date':
                if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                    $validator->errors()->add($key, 'Must be a date in YYYY-MM-DD format.');
                }
                break;

            case 'number':
                if (! is_numeric($value)) {
                    $validator->errors()->add($key, 'Must be a number.');
                }
                break;

            case 'select':
            case 'radio':
                if (! is_string($value) || ! in_array($value, $options, true)) {
                    $validator->errors()->add($key, 'Invalid choice.');
                }
                break;

            case 'checkbox':
                if (! is_array($value)) {
                    $validator->errors()->add($key, 'Must be an array of choices.');
                    break;
                }
                foreach ($value as $option) {
                    if (! is_string($option) || ! in_array($option, $options, true)) {
                        $validator->errors()->add($key, 'Invalid choice.');
                        break;
                    }
                }
                break;
        }
    }
}
