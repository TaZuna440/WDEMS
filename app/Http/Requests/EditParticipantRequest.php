<?php

namespace App\Http\Requests;

use App\Concerns\ContactAndAddressQualityRules;
use App\Concerns\HumanNameQualityRules;
use App\Models\Participant;
use App\Models\Registration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
 * The email UNIQUE constraint and contact_number_normalized UNIQUE
 * constraint still apply. Editing a value that collides with another
 * participant is rejected. Editing a value back to its current
 * setting is allowed — the ignore() call excludes the current row
 * from the unique check.
 *
 * Uniqueness for phone runs in withValidator() because the value
 * that carries the constraint is derived (normalized from the raw
 * input by Participant::setContactNumberAttribute).
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
        $registration = $this->route('registration');
        $participantId = $registration instanceof Registration
            ? $registration->participant_id
            : null;

        return [
            'first_name' => $this->personNameRules(),
            'last_name' => $this->personNameRules(),

            'email' => [
                'nullable',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('participants', 'email')->ignore($participantId),
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
     * Post-rules validation. One check:
     *
     *   - Phone uniqueness. If the submitted raw phone normalizes to
     *     a value, that value must not already belong to another
     *     participant. A phone that does not normalize (landline,
     *     unparseable) has no identity key and cannot collide under
     *     the UNIQUE index.
     *
     * The error lands under `contact_number` so the modal's field
     * anchor fires.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $registration = $this->route('registration');

            if (! $registration instanceof Registration) {
                return;
            }

            $raw = $this->input('contact_number');
            $normalized = Participant::normalizeContactNumber(
                is_string($raw) ? $raw : null,
            );

            if ($normalized === null) {
                return;
            }

            $collision = Participant::where(
                'contact_number_normalized',
                $normalized,
            )
                ->where('id', '!=', $registration->participant_id)
                ->exists();

            if ($collision) {
                $v->errors()->add(
                    'contact_number',
                    'This phone number belongs to another participant.',
                );
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
            'email.unique' => 'This email belongs to another participant.',
        ];
    }
}
