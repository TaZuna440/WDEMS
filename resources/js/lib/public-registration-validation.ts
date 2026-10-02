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
 */

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

function isBlank(value: unknown): boolean {
    if (value === null || value === undefined) return true;
    if (typeof value === 'string') return value.trim() === '';
    if (Array.isArray(value)) return value.length === 0;
    return false;
}

export function validateRegistrationForm(
    data: RegistrationFormData,
    fields: ServerField[],
): Record<string, string> {
    const errors: Record<string, string> = {};

    // -------- Common fields --------

    if (isBlank(data.first_name)) {
        errors.first_name = 'First name is required.';
    } else if (data.first_name.length > MAX_NAME) {
        errors.first_name = `First name must not exceed ${MAX_NAME} characters.`;
    }

    if (isBlank(data.last_name)) {
        errors.last_name = 'Last name is required.';
    } else if (data.last_name.length > MAX_NAME) {
        errors.last_name = `Last name must not exceed ${MAX_NAME} characters.`;
    }

    if (isBlank(data.email)) {
        errors.email = 'Email is required.';
    } else if (!EMAIL_PATTERN.test(data.email)) {
        errors.email = 'Please enter a valid email address.';
    }

    if (isBlank(data.contact_number)) {
        errors.contact_number = 'Contact number is required.';
    } else if (data.contact_number.length > MAX_CONTACT) {
        errors.contact_number = `Contact number must not exceed ${MAX_CONTACT} characters.`;
    }

    if (isBlank(data.age)) {
        errors.age = 'Age is required.';
    } else {
        const age = Number(data.age);
        if (!Number.isInteger(age) || age < 1 || age > 120) {
            errors.age = 'Age must be a whole number between 1 and 120.';
        }
    }

    if (data.address.length > MAX_ADDRESS) {
        errors.address = `Address must not exceed ${MAX_ADDRESS} characters.`;
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

            // text, textarea — no format check beyond required
        }
    }

    return errors;
}
