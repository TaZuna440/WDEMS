<?php

namespace App\Http\Requests;

use App\Concerns\HumanNameQualityRules;
use App\Models\Event;
use App\Models\Participant;
use Illuminate\Foundation\Http\FormRequest;

class PublicRegistrationRequest extends FormRequest
{
    use HumanNameQualityRules;

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
     * registration_field_id.
     *
     * Required-ness of three of the six fields (email, contact_number,
     * address) is per-event — set in the form builder and read via
     * Event::isCommonFieldRequired(). First name, last name, and age
     * are always required.
     *
     * Name fields (first_name, last_name) go through personNameRules(),
     * which composes length bounds with the shared
     * HumanNameQualityRules. That trait is the same one already used
     * by event_name, partners.*.name, and the form builder's label
     * field — reused here rather than duplicated.
     *
     * The at-least-one identity rule (email OR contact_number) lives
     * in withValidator() — it depends on the normalized form of the
     * phone, which is not known during the standard rules pass.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => $this->personNameRules(),
            'last_name' => $this->personNameRules(),
            'email' => $this->commonFieldRules('email', ['string', 'email:rfc', 'max:255']),
            'contact_number' => $this->commonFieldRules('contact_number', ['string', 'max:50']),
            'age' => ['required', 'integer', 'min:1', 'max:120'],
            'address' => $this->commonFieldRules('address', ['string', 'max:500']),
            'responses' => ['nullable', 'array'],
            'responses.*' => ['nullable'],
        ];
    }

    /**
     * Rules for a participant's name field.
     *
     * Composes length bounds with the shared humanNameQualityRules().
     * No minimum length is enforced beyond `required` — two-letter
     * names ("Bo", "Uy") and short surnames are legitimate. The
     * quality rules carry the "does this look like a real name"
     * weight; the length rule only guards the upper bound.
     *
     * @return array<int, string>
     */
    private function personNameRules(): array
    {
        return [
            'required',
            'string',
            'max:255',
            ...$this->humanNameQualityRules(),
        ];
    }

    /**
     * Build the rule list for a common field whose required-ness
     * depends on the event. Always-required fields do not go through
     * this method — they use a fixed `required` rule.
     *
     * Falls back to `required` when no Event is bound, which matches
     * the column's NULL-means-required default and keeps the failure
     * mode safe.
     *
     * @param  array<int, string>  $formatRules
     * @return array<int, string>
     */
    private function commonFieldRules(string $field, array $formatRules): array
    {
        $event = $this->route('event');

        $isRequired = ! ($event instanceof Event) || $event->isCommonFieldRequired($field);

        return array_merge([$isRequired ? 'required' : 'nullable'], $formatRules);
    }

    /**
     * Post-rules validation. Runs in three passes:
     *
     *   1. Custom field responses — key ownership and format.
     *   2. Required custom fields — iterating the field list, not
     *      the submitted keys (a required field the participant
     *      skipped produces no key in `responses`).
     *   3. At-least-one identity — email or normalizable phone. A
     *      submitted phone that does not normalize counts as no
     *      phone. See docs/participant-identity.md §7.
     *
     * The identity check is form-level: the error key is `identity`,
     * not `email` or `contact_number`. The rule is about the pair,
     * not either field in isolation.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $event = $this->route('event');

            if (! $event instanceof Event) {
                return;
            }

            // -------- Pass 1 + 2: custom fields --------

            $fields = $event->registrationFields()->get();
            $responses = $this->input('responses', []);

            if (! is_array($responses)) {
                $responses = [];
            }

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

            // -------- Pass 3: at-least-one identity --------

            $email = $this->input('email');
            $phone = $this->input('contact_number');

            $emailBlank = ! is_string($email) || trim($email) === '';
            $phoneBlank = Participant::normalizeContactNumber(
                is_string($phone) ? $phone : null,
            ) === null;

            if ($emailBlank && $phoneBlank) {
                $v->errors()->add(
                    'identity',
                    'Please provide at least an email address or a contact number.',
                );
            }
        });
    }

    /**
     * Custom messages for the four name-quality regex rules.
     *
     * The rules themselves live in HumanNameQualityRules. Laravel
     * reports any of the three `regex:` failures under the key
     * `{field}.regex` and the not_regex failure under
     * `{field}.not_regex`, so two overrides per field cover all four.
     *
     * Without these overrides Laravel defaults to the raw format
     * message ("The first name format is invalid") which is
     * technically correct but unhelpful. Same wording the wizard
     * already uses for event_name.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.regex' => 'Please enter a valid first name.',
            'first_name.not_regex' => 'Please enter a valid first name.',
            'last_name.regex' => 'Please enter a valid last name.',
            'last_name.not_regex' => 'Please enter a valid last name.',
        ];
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * @param  array<int, string>  $options
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
