/**
 * Client-side validation for the registration form builder.
 *
 * Mirrors app/Concerns/RegistrationFieldValidationRules.php. The server
 * remains the source of truth — these checks exist so the user sees
 * format errors before submitting, not so we can skip server validation.
 *
 * Error keys use the same shape as server errors: `fields.{index}.{col}`
 * for per-row errors, `fields` for aggregate messages. This lets the
 * same rendering code handle both sources.
 */

import {
    FIELD_TYPES,
    isChoiceFieldType,
    type FieldType,
} from '@/lib/registration-field-types';

/**
 * Maximum number of custom fields per event.
 * Mirrors RegistrationFieldValidationRules::MAX_FIELDS.
 */
export const MAX_FIELDS = 30;

/**
 * Maximum number of choices for a select / radio / checkbox field.
 * Mirrors RegistrationFieldValidationRules::MAX_OPTIONS_PER_FIELD.
 */
export const MAX_OPTIONS_PER_FIELD = 50;

/**
 * Maximum length of a field label or option value.
 * Mirrors RegistrationFieldValidationRules::MAX_LABEL_LENGTH.
 */
export const MAX_LABEL_LENGTH = 255;

/**
 * Minimum number of choices required for a select / radio / checkbox
 * field. Mirrors RegistrationFieldValidationRules::MIN_OPTIONS_PER_CHOICE_FIELD.
 */
export const MIN_OPTIONS_PER_CHOICE_FIELD = 2;

/**
 * Labels of this length or longer must contain at least one vowel AND
 * at least one consonant. Shorter labels like "KM", "10K", and "M/F"
 * are legitimate field names and skip the vowel check.
 *
 * Mirrors RegistrationFieldValidationRules::MIN_STRICT_LABEL_LENGTH.
 */
export const MIN_STRICT_LABEL_LENGTH = 5;

export type FieldFormData = {
    label: string;
    field_type: string;
    options: string[];
    is_required: boolean;
    validation_rules: Record<string, string | number | boolean | null> | null;
};

function asString(value: unknown): string {
    return typeof value === 'string' ? value : '';
}

/**
 * Relaxed check — always applied. Starts with a letter or digit, and
 * no 3+ identical characters in a row.
 */
function passesRelaxedLabelChecks(label: string): boolean {
    if (!/^[A-Za-z0-9]/.test(label)) return false;
    if (/(.)\1\1/.test(label)) return false;
    return true;
}

/**
 * Full name-quality check — applied only to labels of
 * MIN_STRICT_LABEL_LENGTH or more characters. Requires a vowel and a
 * consonant.
 */
function passesStrictLabelChecks(label: string): boolean {
    if (!/[aeiouyAEIOUY]/.test(label)) return false;
    if (!/[bcdfghjklmnpqrstvwxzBCDFGHJKLMNPQRSTVWXZ]/.test(label)) return false;
    return true;
}

function isLabelValid(label: string): boolean {
    if (!passesRelaxedLabelChecks(label)) return false;
    if (label.length >= MIN_STRICT_LABEL_LENGTH && !passesStrictLabelChecks(label)) {
        return false;
    }
    return true;
}

/**
 * Validate an entire form definition. Returns a map of error messages
 * keyed the same way the server does — `fields.{index}.label`,
 * `fields.{index}.options`, `fields.{index}.options.{j}`, and `fields`
 * for the aggregate.
 *
 * An empty object means the form is valid.
 */
export function validateRegistrationForm(
    fields: FieldFormData[],
): Record<string, string> {
    const errors: Record<string, string> = {};

    if (fields.length > MAX_FIELDS) {
        errors.fields = `You can add up to ${MAX_FIELDS} custom fields.`;
        return errors;
    }

    fields.forEach((field, index) => {
        const label = asString(field.label).trim();
        const type = asString(field.field_type).trim();
        const options = Array.isArray(field.options) ? field.options : [];

        // Label — required, length, then quality.
        if (!label) {
            errors[`fields.${index}.label`] = 'Field label is required.';
        } else if (label.length > MAX_LABEL_LENGTH) {
            errors[`fields.${index}.label`] =
                `Field label must not exceed ${MAX_LABEL_LENGTH} characters.`;
        } else if (!isLabelValid(label)) {
            errors[`fields.${index}.label`] =
                'Please enter a valid field label.';
        }

        // Type — must be one of the eight.
        if (!type) {
            errors[`fields.${index}.field_type`] = 'Field type is required.';
        } else if (!(FIELD_TYPES as readonly string[]).includes(type)) {
            errors[`fields.${index}.field_type`] = 'Invalid field type.';
        }

        // Options — choice types require at least two; non-choice types
        // must have none. Individual choice values must be non-empty.
        if (isChoiceFieldType(type)) {
            if (options.length < MIN_OPTIONS_PER_CHOICE_FIELD) {
                errors[`fields.${index}.options`] =
                    `At least ${MIN_OPTIONS_PER_CHOICE_FIELD} choices are required for this field type.`;
            } else if (options.length > MAX_OPTIONS_PER_FIELD) {
                errors[`fields.${index}.options`] =
                    `Each field can have up to ${MAX_OPTIONS_PER_FIELD} choices.`;
            } else {
                options.forEach((option, optionIndex) => {
                    const value =
                        typeof option === 'string' ? option.trim() : '';
                    if (value === '') {
                        errors[`fields.${index}.options.${optionIndex}`] =
                            'Choice values cannot be empty.';
                    }
                });
            }
        } else if (options.length > 0) {
            errors[`fields.${index}.options`] =
                'Options are only valid for select, radio, and checkbox fields.';
        }
    });

    // Duplicate labels — case-insensitive, trimmed.
    const seen = new Map<string, number>();
    let duplicateCount = 0;

    fields.forEach((field, index) => {
        const raw = field.label;
        if (typeof raw !== 'string') return;
        const label = raw.trim().toLowerCase();
        if (!label) return;

        const firstIndex = seen.get(label);
        if (firstIndex !== undefined) {
            errors[`fields.${index}.label`] =
                `Duplicate of row ${firstIndex + 1} — same label.`;
            duplicateCount += 1;
        } else {
            seen.set(label, index);
        }
    });

    if (duplicateCount > 0 && !errors.fields) {
        errors.fields =
            duplicateCount === 1
                ? 'One field has the same label as another row.'
                : `${duplicateCount} fields have the same label as other rows.`;
    }

    return errors;
}

/**
 * Convenience — returns true when the entire form passes client-side
 * validation. Used by the save button.
 */
export function isRegistrationFormValid(fields: FieldFormData[]): boolean {
    return Object.keys(validateRegistrationForm(fields)).length === 0;
}

/**
 * Re-export the FieldType union so callers can import it from one place.
 */
export type { FieldType };
