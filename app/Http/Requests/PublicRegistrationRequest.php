<?php

namespace App\Http\Requests;

use App\Concerns\ContactAndAddressQualityRules;
use App\Concerns\HumanNameQualityRules;
use App\Models\Event;
use App\Models\Participant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PublicRegistrationRequest extends FormRequest
{
    use ContactAndAddressQualityRules;
    use HumanNameQualityRules;

    private ?Event $pendingEvent = null;

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['contact_number', 'emergency_contact_number'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $digits = preg_replace('/[\s()\-]/', '', $value);
                if (preg_match('/\A09[0-9]{9}\z/', $digits)) {
                    $normalized[$field] = '+63'.substr($digits, 1);
                } elseif (preg_match('/\A\+639[0-9]{9}\z/', $digits)) {
                    $normalized[$field] = $digits;
                }
            }
        }
        $this->merge($normalized);
    }

    /**
     * Reuse the public form contract against the current event at finalization.
     * The pending payload is decrypted server-side; no client resubmission can
     * replace the contact values that were verified.
     *
     * Transition note (2026-10-19): accepts both `age` and `birthday`.
     * The public form submits `birthday` after Block 6 of the birthdate
     * swap; legacy challenges created before that block carry `age`.
     * Both being accepted means challenges survive the swap across the
     * 24-hour TTL window without a coordinated cutover. It also means
     * any direct caller of this request (tests, tooling) can keep using
     * `age` indefinitely. Removing `age` acceptance is a future cleanup
     * (Block 10 of the swap plan), not a Phase 2 requirement.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function validatePending(Event $event, #[\SensitiveParameter] array $payload): array
    {
        $request = static::create('/', 'POST', $payload);
        $request->pendingEvent = $event;
        $request->prepareForValidation();
        $validator = Validator::make($request->all(), $request->rules(), $request->messages());
        $request->withValidator($validator);

        return $validator->validate();
    }

    /**
     * No auth on the public submission route. The route middleware
     * (EnsureRegistrationIsOpen) is the gate.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Six common participant fields plus event-owned custom responses.
     * Requiredness comes only from Event::isCommonFieldRequired().
     *
     * The demographic block accepts EITHER `age` (free-typed integer,
     * legacy path) OR `birthday` (date, bounded to age 3-120). At
     * least one must be present; the check lives in withValidator() so
     * the error lands under the `birthday` key as a single field error
     * rather than a double error from `required_without` rules.
     *
     * `birthday` bounds:
     *   before_or_equal: today - 3 years  → must be at least 3 today
     *   after_or_equal:  today - 120 years → must be at most 120 today
     *
     * Both bounds are inclusive so exactly-3-today and exactly-120-today
     * both pass. Per D2 of the birthdate swap plan.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $event = $this->pendingEvent ?? $this->route('event');
        $v2 = $event instanceof Event && $event->usesEmailOnlyRegistration();
        $rules = [
            'similarity_acknowledged' => ['sometimes', 'boolean'],
            'first_name' => $this->personNameRules(),
            'last_name' => $this->personNameRules(),
            'middle_name' => ['nullable', 'string', 'max:255', ...$this->humanNameQualityRules()],
            'suffix' => ['nullable', 'string', 'max:16', 'regex:/\A[\p{L}.]+\z/u'],
            'email' => $this->commonFieldRules('email', ['string', 'email:rfc', 'max:255']),
            'contact_number' => $this->commonFieldRules('contact_number', [
                'string',
                'max:50',
                ...$this->contactNumberQualityRules(),
            ]),
            'age' => ['nullable', 'integer', 'min:1', 'max:120'],
            'birthday' => [
                'nullable',
                'date',
                'before_or_equal:'.now()->subYears(3)->toDateString(),
                'after_or_equal:'.now()->subYears(120)->toDateString(),
            ],
            'address' => $this->commonFieldRules('address', [
                'string',
                'max:255',
                ...$this->addressQualityRules(),
            ]),
            'emergency_contact_name' => ['required', 'string', 'max:255', ...$this->humanNameQualityRules()],
            'emergency_contact_relationship' => ['required', 'string', 'max:64'],
            'emergency_contact_number' => ['required', 'string', 'max:50', ...$this->contactNumberQualityRules()],
            'responses' => ['nullable', 'array', 'max:5'],
            'responses.*' => ['nullable'],
        ];

        if ($v2) {
            $name = ['required', 'string', 'min:2', 'max:50', "regex:/\\A[\\p{L}\\p{M} '\\-]+\\z/u"];
            $rules['first_name'] = $name;
            $rules['last_name'] = $name;
            $rules['middle_name'] = array_merge(['nullable'], array_slice($name, 1));
            $rules['suffix'] = ['nullable', 'string', 'max:10', 'regex:/\A[\p{L}.]+\z/u'];
            $rules['birthday'][0] = 'required';
            $rules['contact_number'][] = 'regex:/\A(?:09|\+639)[0-9]{9}\z/';
            $rules['emergency_contact_number'][] = 'regex:/\A(?:09|\+639)[0-9]{9}\z/';
            $rules['address'] = ['required', 'string', 'min:3', 'max:255'];
            $rules['emergency_contact_name'] = ['nullable', 'string', 'max:101'];
            $rules['emergency_contact_first_name'] = $name;
            $rules['emergency_contact_last_name'] = $name;
            $rules['emergency_contact_relationship'] = ['required', Rule::in(['Parent', 'Sibling', 'Spouse', 'Friend', 'Guardian', 'Other'])];
            $rules['emergency_contact_relationship_other'] = ['exclude_unless:emergency_contact_relationship,Other', 'required', 'string', 'min:2', 'max:15', "regex:/\\A(?=.*[\\p{L}])[\\p{L}\\p{M} '\\-]+\\z/u"];
        }

        $tshirt = $event instanceof Event ? $event->registration_tshirt : null;
        $rules['tshirt_choice'] = $tshirt
            ? [$tshirt['required'] ? 'required' : 'nullable', 'string', Rule::in($tshirt['choices'])]
            : ['prohibited'];

        return $rules;
    }

    /** @return array<int, string> */
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
     * @param  array<int, string>  $formatRules
     * @return array<int, string>
     */
    private function commonFieldRules(string $field, array $formatRules): array
    {
        $event = $this->pendingEvent ?? $this->route('event');
        $isRequired = ! ($event instanceof Event) || $event->isCommonFieldRequired($field);

        return array_merge([$isRequired ? 'required' : 'nullable'], $formatRules);
    }

    /**
     * Validate custom response ownership, type and requiredness, then ensure
     * at least one demographic field (age or birthday) is present, then ensure
     * at least one required (therefore verifiable) identity key is available.
     * Optional contacts remain valid input, but cannot satisfy identity.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $event = $this->pendingEvent ?? $this->route('event');

            if (! $event instanceof Event) {
                return;
            }

            $fields = $event->registrationFields()->get();
            $responses = $this->input('responses', []);

            if (! is_array($responses)) {
                $responses = [];
            }

            foreach ($responses as $fieldId => $value) {
                $field = $fields->firstWhere('id', (int) $fieldId);

                if ($field === null || (string) (int) $fieldId !== (string) $fieldId) {
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

            // -------- Demographic: at least one of age or birthday --------

            // Accepts both, prefers neither at the validation layer.
            // `birthday` is the preferred representation going forward;
            // the error lands there so the UI highlights the field the
            // organizer should fill in. `age` remains accepted for
            // legacy callers (pre-swap challenges, existing test
            // fixtures, direct tooling).
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

            // -------- Identity: at least one required contact channel --------

            $email = $this->input('email');
            $phone = $this->input('contact_number');
            $hasRequiredEmail = $event->isCommonFieldRequired('email')
                && is_string($email) && trim($email) !== '';
            $hasRequiredPhone = $event->isCommonFieldRequired('contact_number')
                && Participant::normalizeContactNumber(is_string($phone) ? $phone : null) !== null;

            if (! $hasRequiredEmail && ! $hasRequiredPhone) {
                $v->errors()->add(
                    'identity',
                    'A required email address or Philippine mobile number is needed to verify your registration. Contact the organizer if neither is required.',
                );
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'first_name.regex' => 'Please enter a valid first name.',
            'first_name.not_regex' => 'Please enter a valid first name.',
            'last_name.regex' => 'Please enter a valid last name.',
            'last_name.not_regex' => 'Please enter a valid last name.',
            'middle_name.regex' => 'Please enter a valid middle name.',
            'middle_name.not_regex' => 'Please enter a valid middle name.',
            'suffix.regex' => 'Please enter a valid suffix (e.g. Jr., Sr., II).',
            'contact_number.regex' => 'Please enter a valid phone number.',
            'address.regex' => 'Please enter a valid address.',
            'address.not_regex' => 'Please enter a valid address.',
            'emergency_contact_name.regex' => 'Please enter a valid emergency contact name.',
            'emergency_contact_name.not_regex' => 'Please enter a valid emergency contact name.',
            'emergency_contact_number.regex' => 'Please enter a valid emergency contact number.',
            'birthday.date' => 'Please enter a valid date of birth.',
            'birthday.before_or_equal' => 'You must be at least 3 years old to register.',
            'birthday.after_or_equal' => 'Please enter a valid date of birth.',
        ];
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /** @param array<int, string> $options */
    private function validateType($validator, int $fieldId, string $type, mixed $value, array $options): void
    {
        $key = "responses.{$fieldId}";

        if (is_string($value) && mb_strlen($value) > 5000) {
            $validator->errors()->add($key, 'Must not exceed 5000 characters.');

            return;
        }

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
