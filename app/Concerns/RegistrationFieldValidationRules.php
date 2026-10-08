<?php

namespace App\Concerns;

use App\Models\Event;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait RegistrationFieldValidationRules
{
    public const MAX_FIELDS = 5;
    public const MAX_OPTIONS_PER_FIELD = 10;
    public const MAX_LABEL_LENGTH = 255;
    public const MIN_OPTIONS_PER_CHOICE_FIELD = 2;
    public const MIN_STRICT_LABEL_LENGTH = 2;

    /**
     * Short labels exempt from both the quality and collision rules.
     *
     * These are common form abbreviations that legitimate organizers
     * may want to use. An exempt label is never rejected by the
     * quality rule or the collision rule — it is trusted as-is.
     *
     * All lowercase. The check normalizes the label before comparing.
     *
     * @return array<int, string>
     */
    protected function exemptShortLabels(): array
    {
        return [
            'km',
            'kg',
            'hr',
            'ml',
            'cm',
            'id',
            'no',
            'ok',
        ];
    }

    /**
     * Synonym map for the six structural participant fields.
     *
     * @return array<string, array{display: string, aliases: array<int, string>}>
     */
    protected function commonFieldSynonyms(): array
    {
        return [
            'firstname' => [
                'display' => 'First name',
                'aliases' => ['firstname', 'fname', 'givenname', 'forename', 'first'],
            ],
            'lastname' => [
                'display' => 'Last name',
                'aliases' => ['lastname', 'lname', 'surname', 'familyname', 'last'],
            ],
            'email' => [
                'display' => 'Email',
                'aliases' => ['email', 'emailaddress', 'mail', 'gmail', 'emial'],
            ],
            'contactnumber' => [
                'display' => 'Contact number',
                'aliases' => [
                    'contactnumber', 'contact', 'contactno', 'contactnum',
                    'phone', 'phonenumber', 'mobile', 'mobilenumber',
                    'cell', 'cellphone', 'cp', 'tel', 'telephone',
                ],
            ],
            'age' => [
                'display' => 'Age',
                'aliases' => ['age', 'dob', 'birthdate', 'birthday', 'bday'],
            ],
            'address' => [
                'display' => 'Address',
                'aliases' => ['address', 'addr', 'adress', 'home', 'location', 'street', 'streetaddress'],
            ],
        ];
    }

    protected function commonFieldLabelsNormalized(): array
    {
        $labels = [];
        foreach ($this->commonFieldSynonyms() as $group) {
            foreach ($group['aliases'] as $alias) {
                $labels[] = $alias;
            }
        }
        return $labels;
    }

    protected function fieldTypes(): array
    {
        return ['text', 'textarea', 'number', 'email', 'select', 'checkbox', 'radio', 'date'];
    }

    protected function choiceFieldTypes(): array
    {
        return ['select', 'checkbox', 'radio'];
    }

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

    protected function editDistanceThreshold(int $length): int
    {
        return $length <= 9 ? 1 : 2;
    }

    protected function findCommonFieldCollision(string $normalized): ?array
    {
        if ($normalized === '') {
            return null;
        }

        $groups = $this->commonFieldSynonyms();

        foreach ($groups as $group) {
            if (in_array($normalized, $group['aliases'], true)) {
                return [
                    'display' => $group['display'],
                    'matched_alias' => $normalized,
                    'reason' => 'exact',
                ];
            }
        }

        $length = strlen($normalized);
        $threshold = $this->editDistanceThreshold($length);

        foreach ($groups as $group) {
            foreach ($group['aliases'] as $alias) {
                if (abs(strlen($alias) - $length) > $threshold) {
                    continue;
                }

                if (levenshtein($normalized, $alias) <= $threshold) {
                    return [
                        'display' => $group['display'],
                        'matched_alias' => $alias,
                        'reason' => 'misspelling',
                    ];
                }
            }
        }

        return null;
    }

    protected function registrationFieldRules(): array
    {
        $toggleableKeys = implode(',', Event::COMMON_FIELDS_TOGGLEABLE);

        return [
            'fields' => ['nullable', 'array', 'max:'.self::MAX_FIELDS],
            'fields.*.label' => [
                'required',
                'string',
                'min:2',
                'max:'.self::MAX_LABEL_LENGTH,
                'regex:/^[A-Za-z0-9]/',
                'regex:/[A-Za-z]/',
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

    protected function validateChoiceFieldOptions(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $data = $v->getData();
            $fields = $data['fields'] ?? null;

            if (! is_array($fields)) return;

            $choiceTypes = $this->choiceFieldTypes();

            foreach ($fields as $index => $field) {
                if (! is_array($field)) continue;

                $type = $field['field_type'] ?? null;
                $options = $field['options'] ?? [];

                if (! is_string($type)) continue;

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

    protected function validateFieldLabelDuplicates(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $data = $v->getData();
            $fields = $data['fields'] ?? null;

            if (! is_array($fields)) return;

            $seen = [];
            $duplicateCount = 0;

            foreach ($fields as $index => $field) {
                if (! is_array($field)) continue;

                $rawLabel = $field['label'] ?? '';
                if (! is_string($rawLabel)) continue;

                $label = strtolower(trim($rawLabel));
                if ($label === '') continue;

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

    protected function validateFieldLabelCollisions(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $data = $v->getData();
            $fields = $data['fields'] ?? null;

            if (! is_array($fields)) return;

            $exempt = $this->exemptShortLabels();

            foreach ($fields as $index => $field) {
                if (! is_array($field)) continue;

                $rawLabel = $field['label'] ?? '';
                if (! is_string($rawLabel)) continue;

                $normalized = $this->normalizeLabelForCollision($rawLabel);
                if ($normalized === '') continue;

                // Exempt labels skip the collision rule entirely.
                // This is the fix: previously the exemption list only
                // guarded the quality rule, so 'cm' (centimeters)
                // collided with the 'cp' alias of contactnumber.
                if (in_array($normalized, $exempt, true)) {
                    continue;
                }

                $collision = $this->findCommonFieldCollision($normalized);

                if ($collision !== null) {
                    $v->errors()->add(
                        "fields.{$index}.label",
                        "This label duplicates the built-in field '{$collision['display']}'.",
                    );
                }
            }
        });
    }

    protected function validateFieldLabelQuality(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $data = $v->getData();
            $fields = $data['fields'] ?? null;

            if (! is_array($fields)) return;

            $exempt = $this->exemptShortLabels();

            foreach ($fields as $index => $field) {
                if (! is_array($field)) continue;

                $rawLabel = $field['label'] ?? '';
                if (! is_string($rawLabel)) continue;

                $label = trim($rawLabel);
                $length = mb_strlen($label);

                if ($length < self::MIN_STRICT_LABEL_LENGTH) continue;

                $normalized = mb_strtolower($label);

                if (in_array($normalized, $exempt, true)) continue;

                $hasVowel = preg_match('/[aeiouyAEIOUY]/', $label) === 1;
                $hasConsonant = preg_match('/[bcdfghjklmnpqrstvwxzBCDFGHJKLMNPQRSTVWXZ]/', $label) === 1;

                if (! $hasVowel) {
                    $v->errors()->add(
                        "fields.{$index}.label",
                        'Please enter a valid field label.',
                    );
                    continue;
                }

                if ($length >= 3 && ! $hasConsonant) {
                    $v->errors()->add(
                        "fields.{$index}.label",
                        'Please enter a valid field label.',
                    );
                }
            }
        });
    }

    protected function validateCommonFieldRequirementsIdentity(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $data = $v->getData();
            $requirements = $data['common_field_requirements'] ?? null;

            if (! is_array($requirements)) return;

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

    protected function registrationFieldMessages(): array
    {
        return [
            'fields.max' => 'You can add up to '.self::MAX_FIELDS.' custom fields.',
            'fields.*.options.max' => 'Each field can have up to '.self::MAX_OPTIONS_PER_FIELD.' choices.',
            'fields.*.label.min' => 'Field labels must be at least 2 characters.',
            'fields.*.label.regex' => 'Please enter a valid field label.',
            'common_field_requirements.array' => 'The common field requirements contain an unknown key.',
        ];
    }
}
