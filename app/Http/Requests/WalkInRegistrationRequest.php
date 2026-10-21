<?php

namespace App\Http\Requests;

use App\Concerns\ContactAndAddressQualityRules;
use App\Concerns\HumanNameQualityRules;
use App\Concerns\WalkInFieldValidationRules;
use App\Models\Event;
use App\Models\Participant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WalkInRegistrationRequest extends FormRequest
{
    use ContactAndAddressQualityRules;
    use HumanNameQualityRules;
    use WalkInFieldValidationRules;

    /**
     * Encoding modes accepted by the walk-in endpoint.
     *
     * Live — staff is registering the participant at the table, right
     * now. The server stamps attendance_time with now(), matching the
     * current behavior. This is the default when the field is absent.
     *
     * Deferred — staff is transcribing a paper form after the fact.
     * The server uses the submitted arrival_time instead of now(), so
     * the record preserves when the participant actually arrived, not
     * when the encoding happened.
     */
    public const ENCODING_MODES = ['live', 'deferred'];

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
     * `responses` map. Plus the encoding mode and, when deferred, the
     * arrival time.
     *
     * Required-ness of three of the six common fields (email,
     * contact_number, address) is per-event, read via
     * Event::isCommonFieldRequired(). First name, last name, and
     * birthday are always required (D4 of the birthdate swap — same
     * as the public form).
     *
     * Transition note (2026-10-19): the demographic field accepts
     * both `age` and `birthday`, mirroring PublicRegistrationRequest
     * (birthdate swap Phase 3). The dialog submits `birthday`;
     * legacy callers and existing test fixtures still POST `age`.
     * The demographic pass in withValidator() requires at least one
     * of the two; the error lands on the `birthday` key.
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
            'middle_name' => ['nullable', 'string', 'max:50'],
            'suffix' => ['nullable', 'string', 'max:10'],
            'age' => ['nullable', 'integer', 'min:1', 'max:120'],
            'birthday' => [
                'nullable',
                'date',
                'before_or_equal:'.now()->subYears(3)->toDateString(),
                'after_or_equal:'.now()->subYears(120)->toDateString(),
            ],
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

            // Encoding mode. Absent or null means "live" — the current
            // behavior, kept as the default for backwards compatibility.
            'encoding_mode' => ['nullable', 'string', Rule::in(self::ENCODING_MODES)],

            // Arrival time. Only meaningful when encoding_mode is
            // 'deferred'. Required in that case (checked in
            // withValidator so the error lands under the right key).
            'arrival_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * Post-rules validation. Runs in six passes:
     *
     *   1. Custom field responses — key ownership and format.
     *   2. Required custom fields — iterating the field list, not
     *      the submitted keys (a required field the staff member
     *      skipped produces no key in `responses`).
     *   3. Encoding mode — arrival_time is required when the mode is
     *      deferred. In live mode, any submitted arrival_time is
     *      ignored by the controller.
     *   4. Demographic — at least one of age (legacy) or birthday.
     *      Error lands on the `birthday` key, matching the public
     *      form. Per D4, birthday is required on walk-in; `age`
     *      remains accepted for legacy callers and test fixtures.
     *   5. At-least-one identity — email or normalizable phone.
     *      Early-returns when this fails so the duplicate check
     *      below is not run against a blank identity.
     *   6. Not already registered for this event.
     *
     * Passes 1 + 2 mirror PublicRegistrationRequest::withValidator()
     * exactly. The isEmpty() and validateResponseType() helpers come
     * from the WalkInFieldValidationRules trait — shared with the
     * bulk walk-in request.
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

            // -------- Pass 3: encoding mode --------

            $mode = $this->input('encoding_mode') ?? 'live';
            $arrivalTime = $this->input('arrival_time');

            if ($mode === 'deferred' && ($arrivalTime === null || trim((string) $arrivalTime) === '')) {
                $v->errors()->add(
                    'arrival_time',
                    'Arrival time is required when transcribing from paper.',
                );
            }

            // -------- Pass 4: demographic — at least one of age or birthday --------

            // Accepts both, prefers neither at the validation layer.
            // `birthday` is the preferred representation going forward
            // (D4: required on walk-in, same as the public form); the
            // error lands there so the UI highlights the DatePicker.
            // `age` remains accepted for legacy callers and existing
            // test fixtures. Mirrors
            // PublicRegistrationRequest::withValidator().
            $age = $this->input('age');
            $birthday = $this->input('birthday');

            $hasAge = $age !== null && $age !== '';
            $hasBirthday = is_string($birthday) && trim($birthday) !== '';

            if (! $hasAge && ! $hasBirthday) {
                $v->errors()->add(
                    'birthday',
                    'Please provide your date of birth.',
                );
            }

            // -------- Pass 5: at-least-one identity --------

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

            // -------- Pass 6: not already registered --------

            $existing = Participant::findByComposite('running',
                $emailClean,
                $normalized,
                is_string($this->input('first_name')) ? $this->input('first_name') : null,
                is_string($this->input('last_name')) ? $this->input('last_name') : null,
                is_string($this->input('middle_name')) ? $this->input('middle_name') : null,
                is_string($this->input('suffix')) ? $this->input('suffix') : null,
            );

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
     * address quality regexes, the birthday bounds, the identity
     * error, the arrival-time error, and the custom field errors.
     * Mirrors PublicRegistrationRequest for the shared keys.
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
            'birthday.date' => 'Please enter a valid date of birth.',
            'birthday.before_or_equal' => 'You must be at least 3 years old to register.',
            'birthday.after_or_equal' => 'Please enter a valid date of birth.',
            'identity.required' => 'Please provide at least an email address or a contact number.',
            'arrival_time.date_format' => 'Enter the arrival time as HH:MM (24-hour).',
        ];
    }
}
