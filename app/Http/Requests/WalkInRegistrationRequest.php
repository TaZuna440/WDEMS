<?php

namespace App\Http\Requests;

use App\Concerns\ContactAndAddressQualityRules;
use App\Concerns\HumanNameQualityRules;
use App\Concerns\WalkInFieldValidationRules;
use App\Models\Event;
use App\Models\Participant;
use Illuminate\Foundation\Http\FormRequest;

class WalkInRegistrationRequest extends FormRequest
{
    use ContactAndAddressQualityRules;
    use HumanNameQualityRules;
    use WalkInFieldValidationRules;

    /**
     * Staff are creating this on behalf of a participant. The route
     * middleware (auth + verified.or.admin + device.trusted) is the
     * gate.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Six common participant fields, plus a `mark_present` flag, plus
     * the event's custom fields keyed by registration_field_id in a
     * `responses` map.
     *
     * Required-ness of three of the six common fields (email,
     * contact_number, address) is per-event, read via
     * Event::isCommonFieldRequired(). First name, last name, and age
     * are always required.
     *
     * Contact number and address go through
     * ContactAndAddressQualityRules — same trait the public form uses.
     *
     * Custom field validation lives in withValidator() — the shape
     * depends on the event's registration_fields rows, which are
     * fetched once per request. Mirrors
     * PublicRegistrationRequest::withValidator() Pass 1 + 2.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => $this->personNameRules(),
            'last_name' => $this->personNameRules(),
            'age' => ['required', 'integer', 'min:1', 'max:120'],
            'email' => $this->commonFieldRules('email', ['string', 'email:rfc', 'max:255']),
            'contact_number' => $this->commonFieldRules('contact_number', [
                'string',
                'max:50',
                ...$this->contactNumberQualityRules(),
            ]),
            'address' => $this->commonFieldRules('address', [
                'string',
                'max:500',
                ...$this->addressQualityRules(),
            ]),
            'mark_present' => ['boolean'],
            'responses' => ['nullable', 'array'],
            'responses.*' => ['nullable'],
        ];
    }

    /**
     * Post-rules validation. Runs in four passes:
     *
     *   1. Custom field responses — key ownership and format.
     *   2. Required custom fields — iterating the field list, not
     *      the submitted keys (a required field the staff member
     *      skipped produces no key in `responses`).
     *   3. At-least-one identity — email or normalizable phone.
     *      Early-returns when this fails so the duplicate check
     *      below is not run against a blank identity.
     *   4. Not already registered for this event.
     *
     * Passes 1 + 2 mirror PublicRegistrationRequest::withValidator()
     * exactly. The isEmpty() and validateResponseType() helpers come
     * from the WalkInFieldValidationRules trait — shared with the
     * bulk walk-in request introduced in Phase C.
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

                $this->validateResponseType(
                    $v,
                    "responses.{$fieldId}",
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

            $emailClean = is_string($email) ? trim($email) : '';
            $normalized = Participant::normalizeContactNumber(
                is_string($phone) ? $phone : null,
            );

            if ($emailClean === '' && $normalized === null) {
                $v->errors()->add(
                    'identity',
                    'Please provide at least an email address or a contact number.',
                );

                return;
            }

            // -------- Pass 4: not already registered --------

            $existing = null;

            if ($emailClean !== '') {
                $existing = Participant::where('email', $emailClean)->first();
            }

            if ($existing === null && $normalized !== null) {
                $existing = Participant::where(
                    'contact_number_normalized',
                    $normalized,
                )->first();
            }

            if ($existing === null) {
                return;
            }

            $alreadyRegistered = $existing->registrations()
                ->where('event_id', $event->id)
                ->exists();

            if ($alreadyRegistered) {
                $v->errors()->add(
                    'identity',
                    'This person is already registered for this event. Search the list and mark their attendance instead.',
                );
            }
        });
    }

    /**
     * Rules for a participant's name field. Same shape as
     * PublicRegistrationRequest::personNameRules().
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
     * depends on the event.
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
     * Custom messages for the name quality regexes, the contact and
     * address quality regexes, the identity error, and the custom
     * field errors. Mirrors PublicRegistrationRequest for the custom
     * field keys.
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
            'contact_number.regex' => 'Please enter a valid phone number.',
            'address.regex' => 'Please enter a valid address.',
            'address.not_regex' => 'Please enter a valid address.',
            'identity.required' => 'Please provide at least an email address or a contact number.',
        ];
    }
}
