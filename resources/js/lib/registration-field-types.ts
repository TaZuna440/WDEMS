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
    select: 'Selection',
    checkbox: 'Checkboxes',
    radio: 'Radio buttons',
    date: 'Date',
};

/**
 * Optional hint shown beneath each type in the picker.
 */
export const FIELD_TYPE_HINTS: Record<FieldType, string> = {
    text: 'e.g. Nickname, Emergency contact name',
    textarea: 'e.g. Medical conditions, Dietary restrictions',
    number: 'e.g. Number of companions',
    email: 'e.g. Alternate email address',
    select: 'e.g. Distance — 3km, 5km, 10km',
    checkbox: 'e.g. Add-ons — Timing chip, Meal, Medal engraving',
    radio: 'e.g. Preferred wave — Morning, Afternoon',
    date: 'e.g. Preferred race date',
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
