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
     * Five is the ceiling for a Community Run registration form. Each
     * event already has six common fields (first name, last name, email,
     * contact number, age, address). Five customs brings the total to
     * eleven questions — the practical ceiling before participants
     * abandon the form.
     */
    public const MAX_FIELDS = 5;

    /**
     * Maximum number of choices for a select / radio / checkbox field.
     * Beyond 10, the field should be split into multiple questions or
     * a different field type.
     */
    public const MAX_OPTIONS_PER_FIELD = 10;

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
     * The six structural participant fields every registration form
     * includes automatically. Custom labels may not collide with these
     * after normalization — see validateFieldLabelCollisions().
     *
     * Mirrored in resources/js/lib/registration-field-validation.ts as
     * COMMON_FIELD_LABELS_NORMALIZED. Keep both in sync.
     *
     * @return array<int, string>
     */
    protected function commonFieldLabelsNormalized(): array
    {
        return [
            'firstname',
            'lastname',
            'email',
            'contactnumber',
            'age',
            'address',
        ];
    }

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
     * Normalize a label for collision comparison.
     *
     * Lowercase, NFKC (if the intl extension is available), then strip
     * everything except [a-z0-9]. "First Name", "first_name",
     * "FIRST-NAME", and Cyrillic-lookalike "Fіrst Nаme" all normalize to
     * "firstname".
     */
    protected function normalizeLabelForCollision(string $label): string
    {
        $label = trim($label);

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($label, \Normalizer::FORM_KC);
            if (is_string($normalized)) {
                $label = $normalized;
            }
        }

        return (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower($label));
    }

    /**
     * Get the validation rules used to validate the registration form.
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
     * Registered by RegistrationFieldRequest::withValidator().
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
     * (trim + lowercase). Non-string labels are skipped without casting.
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
     * Reject labels that collide with the six structural common fields.
     *
     * A custom field labeled "Email" would render alongside the
     * structural Email field on the public form, confusing participants
     * about which one to fill in. The collision check normalizes both
     * sides (lowercase, NFKC, strip non-alphanumeric) so "First Name",
     * "first_name", "FIRST-NAME", and Cyrillic-lookalike "First Nаme"
     * all match.
     *
     * Registered by RegistrationFieldRequest::withValidator().
     */
    protected function validateFieldLabelCollisions(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $data = $v->getData();
            $fields = $data['fields'] ?? null;

            if (! is_array($fields)) {
                return;
            }

            $common = $this->commonFieldLabelsNormalized();

            foreach ($fields as $index => $field) {
                if (! is_array($field)) {
                    continue;
                }

                $rawLabel = $field['label'] ?? '';

                if (! is_string($rawLabel)) {
                    continue;
                }

                $normalized = $this->normalizeLabelForCollision($rawLabel);

                if ($normalized === '') {
                    continue;
                }

                if (in_array($normalized, $common, true)) {
                    $v->errors()->add(
                        "fields.{$index}.label",
                        'This label matches a common field that is already on every form.',
                    );
                }
            }
        });
    }

    /**
     * Reject keyboard-mashing labels.
     *
     * Applies the vowel and consonant checks only to labels of
     * MIN_STRICT_LABEL_LENGTH characters or longer.
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
