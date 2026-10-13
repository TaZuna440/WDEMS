<?php

namespace App\Http\Requests;

use App\Concerns\ContactAndAddressQualityRules;
use App\Concerns\HumanNameQualityRules;
use App\Models\Event;
use App\Models\Participant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

class PublicRegistrationRequest extends FormRequest
{
    use ContactAndAddressQualityRules;
    use HumanNameQualityRules;

    private ?Event $pendingEvent = null;

    /**
     * Reuse the public form contract against the current event at finalization.
     * The pending payload is decrypted server-side; no client resubmission can
     * replace the contact values that were verified.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function validatePending(Event $event, #[\SensitiveParameter] array $payload): array
    {
        $request = static::create('/', 'POST', $payload);
        $request->pendingEvent = $event;
        $validator = Validator::make($payload, $request->rules(), $request->messages());
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => $this->personNameRules(),
            'last_name' => $this->personNameRules(),
            'email' => $this->commonFieldRules('email', ['string', 'email:rfc', 'max:255']),
            'contact_number' => $this->commonFieldRules('contact_number', [
                'string',
                'max:50',
                ...$this->contactNumberQualityRules(),
            ]),
            'age' => ['required', 'integer', 'min:1', 'max:120'],
            'address' => $this->commonFieldRules('address', [
                'string',
                'max:255',
                ...$this->addressQualityRules(),
            ]),
            'responses' => ['nullable', 'array', 'max:5'],
            'responses.*' => ['nullable'],
        ];
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
            'contact_number.regex' => 'Please enter a valid phone number.',
            'address.regex' => 'Please enter a valid address.',
            'address.not_regex' => 'Please enter a valid address.',
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
