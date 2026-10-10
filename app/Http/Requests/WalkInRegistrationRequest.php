<?php

namespace App\Http\Requests;

use App\Concerns\ContactAndAddressQualityRules;
use App\Concerns\HumanNameQualityRules;
use App\Models\Event;
use App\Models\Participant;
use Illuminate\Foundation\Http\FormRequest;

class WalkInRegistrationRequest extends FormRequest
{
    use ContactAndAddressQualityRules;
    use HumanNameQualityRules;

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
     * Six common participant fields, plus a `mark_present` flag.
     *
     * Required-ness of three of the six fields (email, contact_number,
     * address) is per-event, read via Event::isCommonFieldRequired().
     * First name, last name, and age are always required.
     *
     * Contact number and address go through
     * ContactAndAddressQualityRules — same trait the public form uses.
     * A walk-in is still a real submission and must not accept
     * keyboard mashing ("fgfdgfdgdfg", "dfdfdsfdsfdsfd"). The quality
     * rules only apply when the field is present; the per-event
     * nullable/required prefix handles the empty case.
     *
     * No custom fields. Per Phase 4 scope, walk-in is a fast path —
     * name, age, and any identity fields the event requires.
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
        ];
    }

    /**
     * Two post-rules checks:
     *
     *   1. At-least-one identity — email or normalizable phone.
     *      Same rule as PublicRegistrationRequest, same key
     *      (`identity`).
     *
     *   2. Not already registered for this event. If the identity
     *      resolves to a participant who already has a Registration
     *      row for this event, the walk-in would create a duplicate.
     *      The error key is `identity` for consistency — the failure
     *      is about the person, not a single field.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $event = $this->route('event');

            if (! $event instanceof Event) {
                return;
            }

            $email = $this->input('email');
            $phone = $this->input('contact_number');

            $emailClean = is_string($email) ? trim($email) : '';
            $normalized = Participant::normalizeContactNumber(
                is_string($phone) ? $phone : null,
            );

            // -------- Pass 1: at-least-one identity --------

            if ($emailClean === '' && $normalized === null) {
                $v->errors()->add(
                    'identity',
                    'Please provide at least an email address or a contact number.',
                );

                return;
            }

            // -------- Pass 2: not already registered --------

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
     * Custom messages for the name quality regexes, the new
     * contact/address quality regexes, and the identity errors.
     * Mirrors the public request for name fields and the new
     * contact/address messages; keeps the walk-in-specific identity
     * message.
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
