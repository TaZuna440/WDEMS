/**
 * The eight supported registration-field types.
 *
 * Mirrors RegistrationFieldValidationRules::fieldTypes() on the server.
 * If this list changes, the server trait, the FormRequest, and this
 * file must all be updated in the same commit.
 */

export const FIELD_TYPES = [
    'text',
    'textarea',
    'number',
    'email',
    'select',
    'checkbox',
    'radio',
    'date',
] as const;

export type FieldType = (typeof FIELD_TYPES)[number];

/**
 * Human-readable labels for the field-type picker.
 */
export const FIELD_TYPE_LABELS: Record<FieldType, string> = {
    text: 'Short text',
    textarea: 'Long text',
    number: 'Number',
    email: 'Email',
    select: 'Dropdown',
    checkbox: 'Checkboxes',
    radio: 'Radio buttons',
    date: 'Date',
};

/**
 * Optional hint shown beneath each type in the picker.
 */
export const FIELD_TYPE_HINTS: Record<FieldType, string> = {
    text: 'Single line of text',
    textarea: 'Multiple lines, good for notes',
    number: 'Digits only',
    email: 'Validated email address',
    select: 'Pick one from a list',
    checkbox: 'Pick any number from a list',
    radio: 'Pick exactly one from a list',
    date: 'Calendar date',
};

/**
 * Field types that carry an options array.
 *
 * Mirrors RegistrationFieldValidationRules::choiceFieldTypes().
 */
export const CHOICE_FIELD_TYPES: readonly FieldType[] = [
    'select',
    'checkbox',
    'radio',
];

export function isChoiceFieldType(type: string): type is FieldType {
    return (CHOICE_FIELD_TYPES as readonly string[]).includes(type);
}

/**
 * Placeholder text for a new field's label input.
 */
export const FIELD_LABEL_PLACEHOLDER = 'e.g. Shirt Size, Emergency Contact';

/**
 * Placeholder text for a new option in a choice field.
 */
export const OPTION_VALUE_PLACEHOLDER = 'e.g. Small';
