/**
 * Client-side validation for the public registration form.
 *
 * Mirrors app/Http/Requests/PublicRegistrationRequest.php. The server
 * remains the source of truth — these checks exist so the participant
 * sees format errors before submitting, not so we can skip server
 * validation.
 *
 * Error keys match the server's naming so `<InputError>` slots fire
 * on both server and client errors without any translation layer:
 *   - common fields use their own name: "email", "age", ...
 *   - custom fields use "responses.{field_id}"
 *   - at-least-one identity rule uses "identity" (form-level)
 *
 * Required-ness of email, contact_number, and address comes from the
 * event's common_field_requirements map (passed as the third
 * argument). first_name, last_name, and age are always required.
 *
 * Name quality checks reuse humanNameQualityError() from
 * @/lib/event-validation. That function is the client mirror of the
 * server's HumanNameQualityRules trait — one source of truth for what
 * counts as keyboard mashing, shared across the wizard, the form
 * builder, and this file.
 *
 * Contact-number and address quality checks are new: they mirror the
 * ContactAndAddressQualityRules trait on the server and catch
 * keyboard mashing ("fgfdgfdgdfg", "dfdfdsfdsfdsfd") while admitting
 * real phone numbers and addresses. The rules are intentionally kept
 * identical to the PHP versions — if you change one side, change the
 * other in the same commit.
 */

import { humanNameQualityError } from '@/lib/event-validation';

export type ServerField = {
    id: number;
    label: string;
    field_type: string;
    options: string[];
    is_required: boolean;
};

export type RegistrationFormData = {
    first_name: string;
    last_name: string;
    email: string;
    contact_number: string;
    age: string;
    address: string;
    responses: Record<number, string | string[]>;
};

const MAX_NAME = 255;
const MAX_CONTACT = 50;
const MAX_ADDRESS = 500;

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/;

/**
 * Client mirror of ContactAndAddressQualityRules::contactNumberQualityRules().
 *
 * Allowed characters: digits, spaces, +, -, (, ), .
 * Digit count: 7 to 15 (enough for any national or international
 * number, too few for keyboard mashing).
 */
const CONTACT_ALLOWED_CHARS = /^[\d\s+\-().]+$/;
const CONTACT_DIGIT_COUNT = /^(?=(?:\D*\d){7,15}\D*$)/;

/**
 * Client mirror of ContactAndAddressQualityRules::addressQualityRules().
 *
 * Five checks: length >= 5, has a letter, has a vowel-or-digit, has
 * a consonant-or-digit, no 3+ identical letters in a row.
 */
const ADDRESS_HAS_LETTER = /[A-Za-z]/;
const ADDRESS_HAS_VOWEL_OR_DIGIT = /[aeiouyAEIOUY0-9]/;
const ADDRESS_HAS_CONSONANT_OR_DIGIT = /[bcdfghjklmnpqrstvwxzBCDFGHJKLMNPQRSTVWXZ0-9]/;
const ADDRESS_REPEATED_LETTERS = /([A-Za-z])\1\1/;

function isBlank(value: unknown): boolean {
    if (value === null || value === undefined) return true;
    if (typeof value === 'string') return value.trim() === '';
    if (Array.isArray(value)) return value.length === 0;
    return false;
}

/**
 * Client-side mirror of Participant::normalizeContactNumber().
 *
 * Accepts the four PH mobile formats (09-prefix, +63-prefix,
 * 63-prefix, bare 9-prefix). Returns the canonical form or null.
 *
 * Used only by the at-least-one identity check. The server is the
 * source of truth for the actual stored value.
 */
function normalizePhone(raw: string): string | null {
    const digits = raw.replace(/\D+/g, '');

    if (digits === '') return null;

    if (digits.startsWith('63') && digits.length === 12) {
        return '0' + digits.slice(2);
    }

    if (digits.startsWith('9') && digits.length === 10) {
        return '0' + digits;
    }

    if (digits.startsWith('09') && digits.length === 11) {
        return digits;
    }

    return null;
}

/**
 * Name-field validation: blank, length, quality.
 *
 * The quality check reuses the shared humanNameQualityError() helper
 * so that "sdfasf" is rejected identically here and in the wizard.
 * Returns the error message or null when the value passes.
 */
function validateNameValue(
    value: string,
    label: string,
): string | null {
    if (isBlank(value)) {
        return `${label} is required.`;
    }
    if (value.length > MAX_NAME) {
        return `${label} must not exceed ${MAX_NAME} characters.`;
    }
    return humanNameQualityError(value, label);
}

/**
 * Contact-number validation: length, allowed characters, digit count.
 *
 * Mirrors ContactAndAddressQualityRules::contactNumberQualityRules()
 * and the message overrides on PublicRegistrationRequest. Returns the
 * error message or null. Does not enforce identity — a value that
 * fails here can still be blank-safe if email is present.
 */
function validateContactNumber(value: string): string | null {
    if (value.length > MAX_CONTACT) {
        return `Contact number must not exceed ${MAX_CONTACT} characters.`;
    }
    if (!CONTACT_ALLOWED_CHARS.test(value)) {
        return 'Please enter a valid phone number.';
    }
    if (!CONTACT_DIGIT_COUNT.test(value)) {
        return 'Please enter a valid phone number.';
    }
    return null;
}

/**
 * Address validation: length, letters, vowels/digits, consonants/
 * digits, no repeated letters.
 *
 * Mirrors ContactAndAddressQualityRules::addressQualityRules() and
 * the message overrides on PublicRegistrationRequest. Returns the
 * error message or null.
 */
function validateAddress(value: string): string | null {
    if (value.length > MAX_ADDRESS) {
        return `Address must not exceed ${MAX_ADDRESS} characters.`;
    }
    if (value.length < 5) {
        return 'Please enter a valid address.';
    }
    if (!ADDRESS_HAS_LETTER.test(value)) {
        return 'Please enter a valid address.';
    }
    if (!ADDRESS_HAS_VOWEL_OR_DIGIT.test(value)) {
        return 'Please enter a valid address.';
    }
    if (!ADDRESS_HAS_CONSONANT_OR_DIGIT.test(value)) {
        return 'Please enter a valid address.';
    }
    if (ADDRESS_REPEATED_LETTERS.test(value)) {
        return 'Please enter a valid address.';
    }
    return null;
}

export function validateRegistrationForm(
    data: RegistrationFormData,
    fields: ServerField[],
    commonFieldRequirements: Record<string, boolean> = {},
): Record<string, string> {
    const errors: Record<string, string> = {};

    const isRequired = (field: string): boolean =>
        commonFieldRequirements[field] ?? true;

    // -------- Common fields --------

    const firstNameError = validateNameValue(data.first_name, 'First name');
    if (firstNameError) {
        errors.first_name = firstNameError;
    }

    const lastNameError = validateNameValue(data.last_name, 'Last name');
    if (lastNameError) {
        errors.last_name = lastNameError;
    }

    if (isBlank(data.age)) {
        errors.age = 'Age is required.';
    } else {
        const age = Number(data.age);
        if (!Number.isInteger(age) || age < 1 || age > 120) {
            errors.age = 'Age must be a whole number between 1 and 120.';
        }
    }

    if (isBlank(data.email)) {
        if (isRequired('email')) {
            errors.email = 'Email is required.';
        }
    } else if (!EMAIL_PATTERN.test(data.email)) {
        errors.email = 'Please enter a valid email address.';
    }

    if (isBlank(data.contact_number)) {
        if (isRequired('contact_number')) {
            errors.contact_number = 'Contact number is required.';
        }
    } else {
        const contactError = validateContactNumber(data.contact_number);
        if (contactError) {
            errors.contact_number = contactError;
        }
    }

    if (isBlank(data.address)) {
        if (isRequired('address')) {
            errors.address = 'Address is required.';
        }
    } else {
        const addressError = validateAddress(data.address);
        if (addressError) {
            errors.address = addressError;
        }
    }

    // -------- At-least-one identity --------

    // Email counts when non-blank. Phone counts when it normalizes to
    // a PH mobile. The rule is form-level, not anchored to either
    // field — the error key is `identity`.
    //
    // Under normal configuration at least one of email/contact_number
    // is required, so this check is defense in depth against a legacy
    // or manually-edited event where both are optional.
    const emailBlank = isBlank(data.email);
    const phoneBlank = isBlank(data.contact_number)
        || normalizePhone(data.contact_number) === null;

    if (emailBlank && phoneBlank) {
        errors.identity = 'Please provide at least an email address or a contact number.';
    }

    // -------- Custom fields --------

    for (const field of fields) {
        const key = `responses.${field.id}`;
        const value = data.responses[field.id];

        if (isBlank(value)) {
            if (field.is_required) {
                errors[key] = 'This field is required.';
            }
            continue;
        }

        switch (field.field_type) {
            case 'email':
                if (typeof value !== 'string' || !EMAIL_PATTERN.test(value)) {
                    errors[key] = 'Please enter a valid email address.';
                }
                break;

            case 'date':
                if (typeof value !== 'string' || !DATE_PATTERN.test(value)) {
                    errors[key] = 'Please enter a valid date.';
                }
                break;

            case 'number':
                if (typeof value !== 'string' || !/^-?\d+(\.\d+)?$/.test(value)) {
                    errors[key] = 'Please enter a number.';
                }
                break;

            case 'select':
            case 'radio':
                if (typeof value !== 'string' || !field.options.includes(value)) {
                    errors[key] = 'Please choose one of the available options.';
                }
                break;

            case 'checkbox':
                if (!Array.isArray(value) || value.some((v) => !field.options.includes(v))) {
                    errors[key] = 'Please choose from the available options.';
                }
                break;
        }
    }

    return errors;
}
