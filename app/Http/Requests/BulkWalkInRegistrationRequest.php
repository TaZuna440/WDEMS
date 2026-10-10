<?php

namespace App\Http\Requests;

use App\Concerns\ContactAndAddressQualityRules;
use App\Concerns\HumanNameQualityRules;
use App\Concerns\WalkInFieldValidationRules;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bulk walk-in registration payload.
 *
 * Accepts a `rows` array. Each row is shaped like a single walk-in
 * submission, plus a `client_uuid` for idempotency. No `mark_present`
 * per row — bulk rows always mark present (Phase C scope decision).
 *
 * Validation split:
 *
 *   - This request validates SHAPE. Array bounds, per-row field
 *     rules (name quality, age range, per-event common field
 *     required-ness), custom field ownership and format, and
 *     client_uuid uniqueness within the payload.
 *
 *   - The controller validates BUSINESS RULES per row. Identity
 *     resolution, duplicate-against-existing, duplicate-within-batch.
 *     Those are per-row accept/reject (D6) and cannot be expressed
 *     as all-or-nothing Laravel rules without blocking the whole
 *     batch on one bad row.
 *
 * Error keys use the `rows.{i}.*` prefix so the frontend can render
 * per-row feedback from a single error bag.
 */
class BulkWalkInRegistrationRequest extends FormRequest
{
    use ContactAndAddressQualityRules;
    use HumanNameQualityRules;
    use WalkInFieldValidationRules;

    public const MAX_ROWS = 100;

    /**
     * Route middleware is the gate (auth + verified.or.admin +
     * device.trusted). The controller checks the event-specific
     * window gate and the per-row business rules.
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
            'rows' => ['required', 'array', 'min:1', 'max:'.self::MAX_ROWS],

            'rows.*.client_uuid' => ['required', 'string', 'uuid', 'distinct'],

            'rows.*.first_name' => $this->personNameRules(),
            'rows.*.last_name' => $this->personNameRules(),
            'rows.*.age' => ['required', 'integer', 'min:1', 'max:120'],

            'rows.*.email' => $this->commonFieldRules('email', [
                'string',
                'email:rfc',
                'max:255',
            ]),
            'rows.*.contact_number' => $this->commonFieldRules('contact_number', [
                'string',
                'max:50',
                ...$this->contactNumberQualityRules(),
            ]),
            'rows.*.address' => $this->commonFieldRules('address', [
                'string',
                'max:500',
                ...$this->addressQualityRules(),
            ]),

            'rows.*.responses' => ['nullable', 'array'],
            'rows.*.responses.*' => ['nullable'],

            'force_open' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Post-rules validation. Runs two passes over the rows:
     *
     *   1. Custom field responses — key ownership and format per row.
     *      Error keys: `rows.{i}.responses.{field_id}`.
     *   2. Required custom fields — iterating each row's field list,
     *      not the submitted keys.
     *
     * Pass 1 and 2 mirror WalkInRegistrationRequest::withValidator()
     * for shape. Business rules (identity, duplicate) live in the
     * controller.
     *
     * Deliberately does NOT check identity presence (email or phone).
     * That is a per-row business rule and belongs in the controller
     * so a single row with no identity does not block the whole
     * batch. The controller catches the failure per row.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $event = $this->route('event');

            if (! $event instanceof Event) {
                return;
            }

            $fields = $event->registrationFields()->get();
            $rows = $this->input('rows', []);

            if (! is_array($rows)) {
                return;
            }

            foreach ($rows as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $responses = $row['responses'] ?? [];

                if (! is_array($responses)) {
                    $responses = [];
                }

                // -------- Pass 1: format per response --------

                foreach ($responses as $fieldId => $value) {
                    $field = $fields->firstWhere('id', (int) $fieldId);

                    if ($field === null) {
                        $v->errors()->add(
                            "rows.{$i}.responses.{$fieldId}",
                            'This field does not belong to this event.',
                        );
                        continue;
                    }

                    if ($this->isEmpty($value)) {
                        continue;
                    }

                    $this->validateResponseType(
                        $v,
                        "rows.{$i}.responses.{$fieldId}",
                        $field->field_type,
                        $value,
                        $field->options ?? [],
                    );
                }

                // -------- Pass 2: required custom fields --------

                foreach ($fields as $field) {
                    if (! $field->is_required) {
                        continue;
                    }

                    $value = $responses[$field->id] ?? null;

                    if ($this->isEmpty($value)) {
                        $v->errors()->add(
                            "rows.{$i}.responses.{$field->id}",
                            'This field is required.',
                        );
                    }
                }
            }
        });
    }

    /**
     * Rules for a row's name field. Same shape as the single walk-in
     * request, applied per row.
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
     * depends on the event. Same pattern as the single walk-in and
     * public requests.
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
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.required' => 'At least one row is required.',
            'rows.min' => 'At least one row is required.',
            'rows.max' => 'You can add up to '.self::MAX_ROWS.' rows at a time.',

            'rows.*.client_uuid.required' => 'Each row needs a client identifier.',
            'rows.*.client_uuid.uuid' => 'Each row needs a valid client identifier.',
            'rows.*.client_uuid.distinct' => 'Two rows share the same client identifier.',

            'rows.*.first_name.regex' => 'Please enter a valid first name.',
            'rows.*.first_name.not_regex' => 'Please enter a valid first name.',
            'rows.*.last_name.regex' => 'Please enter a valid last name.',
            'rows.*.last_name.not_regex' => 'Please enter a valid last name.',
            'rows.*.contact_number.regex' => 'Please enter a valid phone number.',
            'rows.*.address.regex' => 'Please enter a valid address.',
            'rows.*.address.not_regex' => 'Please enter a valid address.',
        ];
    }
}
