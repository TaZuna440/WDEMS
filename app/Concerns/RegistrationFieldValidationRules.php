<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait RegistrationFieldValidationRules
{
    /**
     * Maximum number of custom fields per event.
     *
     * Thirty is the realistic ceiling for a Community Run registration
     * form. Beyond that the organizer is building a survey, not a signup.
     */
    public const MAX_FIELDS = 30;

    /**
     * Maximum number of choices for a select / radio / checkbox field.
     * Beyond 50, the field should be split.
     */
    public const MAX_OPTIONS_PER_FIELD = 50;

    /**
     * Maximum length of a field label or option value.
     */
    public const MAX_LABEL_LENGTH = 255;

    /**
     * Minimum number of choices required for a select / radio / checkbox
     * field. A choice field with one option is a hidden constant.
     */
    public const MIN_OPTIONS_PER_CHOICE_FIELD = 2;

    /**
     * Labels of this length or longer must pass the full human-name
     * quality checks (at least one vowel AND at least one consonant).
     *
     * Shorter labels — "KM", "10K", "M/F", "BP", "3M" — are legitimate
     * field names for a running event and have no vowel. Enforcing the
     * vowel check on them would reject real labels while catching
     * nothing.
     *
     * Long labels ("gfdgfdfgfd", "dfdsfdsfd") are keyboard mashing and
     * should be rejected. Five is the smallest threshold that keeps every
     * legitimate short label while catching the mashing case.
     *
     * Mirrored in resources/js/lib/registration-field-validation.ts as
     * MIN_STRICT_LABEL_LENGTH. Keep both in sync.
     */
    public const MIN_STRICT_LABEL_LENGTH = 5;

    /**
     * All eight supported field types.
     *
     * @return array<int, string>
     */
    protected function fieldTypes(): array
    {
        return [
            'text',
            'textarea',
            'number',
            'email',
            'select',
            'checkbox',
            'radio',
            'date',
        ];
    }

    /**
     * Field types that carry an options array.
     *
     * @return array<int, string>
     */
    protected function choiceFieldTypes(): array
    {
        return ['select', 'checkbox', 'radio'];
    }

    /**
     * Get the validation rules used to validate the registration form.
     *
     * The payload is a batch: a `fields` array containing the entire
     * form definition. Each row is validated independently; cross-row
     * checks (choice-field options, duplicate labels, label quality)
     * run in withValidator() closures.
     *
     * Labels are always required, bounded, and checked for the two
     * relaxed quality rules (start with letter/digit, no 3+ repeats).
     * The full human-name quality rules (vowel + consonant) apply only
     * to labels of MIN_STRICT_LABEL_LENGTH or more — see
     * validateFieldLabelQuality.
     *
     * display_order is not a rule. The batch endpoint assigns it from
     * array position, so client and server cannot diverge.
     *
     * validation_rules is stored as a freeform JSON blob in Phase 2.
     * Its internal structure is validated in Phase 3 when the public
     * form consumes it.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function registrationFieldRules(): array
    {
        return [
            'fields' => ['nullable', 'array', 'max:'.self::MAX_FIELDS],
            'fields.*.label' => [
                'required',
                'string',
                'min:1',
                'max:'.self::MAX_LABEL_LENGTH,
                'regex:/^[A-Za-z0-9]/',
                'not_regex:/(.)\1\1/',
            ],
            'fields.*.field_type' => ['required', 'string', Rule::in($this->fieldTypes())],
            'fields.*.options' => ['nullable', 'array', 'max:'.self::MAX_OPTIONS_PER_FIELD],
            'fields.*.options.*' => ['string', 'min:1', 'max:'.self::MAX_LABEL_LENGTH],
            'fields.*.is_required' => ['boolean'],
            'fields.*.validation_rules' => ['nullable', 'array'],
        ];
    }

    /**
     * Cross-field check: choice types require options; non-choice types
     * must not carry any.
     *
     * Runs inside a Validator::after closure so it can read the whole
     * payload. Registered by RegistrationFieldRequest::withValidator().
     */
    protected function validateChoiceFieldOptions(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $data = $v->getData();
            $fields = $data['fields'] ?? null;

            if (! is_array($fields)) {
                return;
            }

            $choiceTypes = $this->choiceFieldTypes();

            foreach ($fields as $index => $field) {
                if (! is_array($field)) {
                    continue;
                }

                $type = $field['field_type'] ?? null;
                $options = $field['options'] ?? [];

                if (! is_string($type)) {
                    continue;
                }

                if (in_array($type, $choiceTypes, true)) {
                    if (! is_array($options) || count($options) < self::MIN_OPTIONS_PER_CHOICE_FIELD) {
                        $v->errors()->add(
                            "fields.{$index}.options",
                            'At least '.self::MIN_OPTIONS_PER_CHOICE_FIELD.' choices are required for this field type.',
                        );
                    }
                } else {
                    if (is_array($options) && count($options) > 0) {
                        $v->errors()->add(
                            "fields.{$index}.options",
                            'Options are only valid for select, radio, and checkbox fields.',
                        );
                    }
                }
            }
        });
    }

    /**
     * Reject duplicate field labels.
     *
     * Two fields are duplicates iff the normalized label matches
     * (trim + lowercase). Same pattern as the partner duplicate check.
     *
     * Non-string labels are skipped without casting — a bad label type
     * already produces a shape error from the rules array, and casting
     * an array to string emits a warning.
     *
     * Registered by RegistrationFieldRequest::withValidator().
     */
    protected function validateFieldLabelDuplicates(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $data = $v->getData();
            $fields = $data['fields'] ?? null;

            if (! is_array($fields)) {
                return;
            }

            $seen = [];
            $duplicateCount = 0;

            foreach ($fields as $index => $field) {
                if (! is_array($field)) {
                    continue;
                }

                $rawLabel = $field['label'] ?? '';

                if (! is_string($rawLabel)) {
                    continue;
                }

                $label = strtolower(trim($rawLabel));

                if ($label === '') {
                    continue;
                }

                if (isset($seen[$label])) {
                    $v->errors()->add(
                        "fields.{$index}.label",
                        'Duplicate of row '.($seen[$label] + 1).' — same label.',
                    );
                    $duplicateCount++;
                } else {
                    $seen[$label] = $index;
                }
            }

            if ($duplicateCount > 0) {
                $v->errors()->add(
                    'fields',
                    $duplicateCount === 1
                        ? 'One field has the same label as another row.'
                        : "{$duplicateCount} fields have the same label as other rows.",
                );
            }
        });
    }

    /**
     * Reject keyboard-mashing labels.
     *
     * Two rules are always applied by the rules array: the label must
     * start with a letter or digit, and it must not contain 3+ identical
     * characters in a row.
     *
     * This closure adds the vowel and consonant checks, but only for
     * labels of MIN_STRICT_LABEL_LENGTH characters or longer. Shorter
     * labels like "KM", "10K", "M/F", "BP", and "3M" are legitimate
     * field names for a running event and would fail a vowel check.
     * Longer labels that contain no vowels ("gfdgfdfgfd") are mashing.
     *
     * Registered by RegistrationFieldRequest::withValidator().
     */
    protected function validateFieldLabelQuality(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $data = $v->getData();
            $fields = $data['fields'] ?? null;

            if (! is_array($fields)) {
                return;
            }

            foreach ($fields as $index => $field) {
                if (! is_array($field)) {
                    continue;
                }

                $rawLabel = $field['label'] ?? '';

                if (! is_string($rawLabel)) {
                    continue;
                }

                $label = trim($rawLabel);

                if (mb_strlen($label) < self::MIN_STRICT_LABEL_LENGTH) {
                    continue;
                }

                $hasVowel = preg_match('/[aeiouyAEIOUY]/', $label) === 1;
                $hasConsonant = preg_match('/[bcdfghjklmnpqrstvwxzBCDFGHJKLMNPQRSTVWXZ]/', $label) === 1;

                if (! $hasVowel || ! $hasConsonant) {
                    $v->errors()->add(
                        "fields.{$index}.label",
                        'Please enter a valid field label.',
                    );
                }
            }
        });
    }

    /**
     * Custom messages for registration field rules whose Laravel
     * defaults do not read well.
     *
     * @return array<string, string>
     */
    protected function registrationFieldMessages(): array
    {
        return [
            'fields.max' => 'You can add up to '.self::MAX_FIELDS.' custom fields.',
            'fields.*.options.max' => 'Each field can have up to '.self::MAX_OPTIONS_PER_FIELD.' choices.',
            'fields.*.label.min' => 'Field labels cannot be empty.',
        ];
    }
}
