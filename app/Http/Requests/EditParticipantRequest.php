<?php

namespace App\Http\Requests;

use App\Concerns\ContactAndAddressQualityRules;
use App\Concerns\HumanNameQualityRules;
use App\Models\Participant;
use App\Models\Registration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Edit a participant's identity and descriptive fields from the
 * monitor's actions menu.
 *
 * Scope per docs/registration-monitoring.md §10: name, email, phone,
 * address. Age is not editable. First-write-wins is deliberately
 * overridden here — the organizer is correcting a mistake, not
 * resolving a new registration.
 *
 * Shared contact details are allowed. Reject collisions only when the
 * normalized full name and supplied contact identify another participant.
 * Existing middle name and suffix are preserved during this correction.
 */
class EditParticipantRequest extends FormRequest
{
    use ContactAndAddressQualityRules;
    use HumanNameQualityRules;

    /**
     * Route middleware is the gate (auth + verified.or.admin +
     * device.trusted). The controller checks that the registration
     * belongs to an event that is open or closed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => $this->personNameRules(),
            'last_name' => $this->personNameRules(),

            'email' => [
                'nullable',
                'string',
                'email:rfc',
                'max:255',
            ],

            'contact_number' => [
                'nullable',
                'string',
                'max:50',
                ...$this->contactNumberQualityRules(),
            ],

            'address' => [
                'nullable',
                'string',
                'max:500',
                ...$this->addressQualityRules(),
            ],
        ];
    }

    /**
     * Reject full-name plus contact collisions after field validation.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $registration = $this->route('registration');

            if (! $registration instanceof Registration) {
                return;
            }

            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $person = $registration->participant;
            $matches = Participant::query()->matchingName('running',
                $this->input('first_name'), $this->input('last_name'), $person?->middle_name, $person?->suffix,
            )->where('id', '!=', $registration->participant_id);
            $email = mb_strtolower(trim((string) $this->input('email', '')));
            if ($email !== '' && (clone $matches)->where('email', $email)->exists()) {
                $v->errors()->add('email', 'Another participant already has this full name and email. Review the records before linking them.');
            }
            $raw = $this->input('contact_number');
            $normalized = Participant::normalizeContactNumber(is_string($raw) ? $raw : null);
            if ($normalized !== null && (clone $matches)->where('contact_number_normalized', $normalized)->exists()) {
                $v->errors()->add('contact_number', 'Another participant already has this full name and phone. Review the records before linking them.');
            }
        });
    }

    /**
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
        ];
    }
}
