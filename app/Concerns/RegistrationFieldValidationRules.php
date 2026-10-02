<?php

namespace App\Concerns;

use App\Models\Event;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait RegistrationFieldValidationRules
{
    /**
     * Maximum number of custom fields per event.
     */
    public const MAX_FIELDS = 5;

    /**
     * Maximum number of choices for a select / radio / checkbox field.
     */
    public const MAX_OPTIONS_PER_FIELD = 10;

    /**
     * Maximum length of a field label or option value.
     */
    public const MAX_LABEL_LENGTH = 255;

    /**
     * Minimum number of choices required for a select / radio / checkbox
     * field.
     */
    public const MIN_OPTIONS_PER_CHOICE_FIELD = 2;

    /**
     * Labels of this length or longer must pass the full human-name
     * quality checks.
     */
    public const MIN_STRICT_LABEL_LENGTH = 5;

    /**
     * The six structural participant fields every registration form
     * includes automatically.
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
        $toggleableKeys = implode(',', Event::COMMON_FIELDS_TOGGLEABLE);

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

            'common_field_requirements' => ['nullable', 'array:'.$toggleableKeys],
            'common_field_requirements.*' => ['boolean'],
        ];
    }

    /**
     * Cross-field check: choice types require options; non-choice types
     * must not carry any.
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
     * Reject any form-builder save where both identity fields are
     * toggled off.
     *
     * The registration form must collect at least one identity key
     * (email or contact_number). The at-least-one rule is enforced at
     * the request layer on public submissions
     * (PublicRegistrationRequest::withValidator). This method is the
     * upstream guard: an organizer cannot publish a form where both
     * keys are optional, because no submission through such a form
     * could be deduplicated.
     *
     * Both toggles off is the only failing shape. A missing map means
     * "all required" (the column's NULL-default), which passes.
     *
     * See docs/participant-identity.md §7.
     */
    protected function validateCommonFieldRequirementsIdentity(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $data = $v->getData();
            $requirements = $data['common_field_requirements'] ?? null;

            if (! is_array($requirements)) {
                return;
            }

            $emailRequired = (bool) ($requirements['email'] ?? true);
            $phoneRequired = (bool) ($requirements['contact_number'] ?? true);

            if (! $emailRequired && ! $phoneRequired) {
                $v->errors()->add(
                    'common_field_requirements',
                    'At least one of email or contact number must be required.',
                );
            }
        });
    }

    /**
     * Custom messages for registration field rules.
     *
     * @return array<string, string>
     */
    protected function registrationFieldMessages(): array
    {
        return [
            'fields.max' => 'You can add up to '.self::MAX_FIELDS.' custom fields.',
            'fields.*.options.max' => 'Each field can have up to '.self::MAX_OPTIONS_PER_FIELD.' choices.',
            'fields.*.label.min' => 'Field labels cannot be empty.',
            'common_field_requirements.array' => 'The common field requirements contain an unknown key.',
        ];
    }
}
