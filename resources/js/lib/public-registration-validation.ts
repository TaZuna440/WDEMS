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
 *   - common fields use their own name: "email", "birthday", ...
 *   - custom fields use "responses.{field_id}"
 *   - at-least-one identity rule uses "identity" (form-level)
 *
 * Demographics (age OR birthday). The 2026-10-19 birthdate swap
 * replaced the free-typed `age` field with a bounded `birthday` date
 * picker (age 3 to 120). Both fields remain accepted by the server so
 * pre-swap challenges and legacy callers keep working. This file
 * mirrors that: `age` and `birthday` are both optional on
 * RegistrationFormData, and at least one must be present when the
 * caller supplies a birthday field.
 *
 * Required-ness of email, contact_number, and address comes from the
 * event's common_field_requirements map (passed as the third
 * argument). first_name, last_name, and (age-or-birthday) are always
 * required.
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
    similarity_reviewed_identity?: string;
    birthday_input?: string;
    similarity_acknowledged?: boolean;
    first_name: string;
    last_name: string;
    /**
     * Optional middle name. Same quality rules as first/last when
     * present. Part of the composite identity key (W7).
     */
    middle_name?: string;
    /**
     * Optional suffix (Jr., Sr., II…). Letters and periods only,
     * max 16 chars. Part of the composite identity key (W7).
     */
    suffix?: string;
    /**
     * Emergency contact (Step 3). Required at intake when the caller
     * supplies the fields; stored on the registration, not the
     * participant.
     */
    emergency_contact_name?: string;
    tshirt_choice?: string;
    emergency_contact_first_name?: string;
    emergency_contact_last_name?: string;
    emergency_contact_relationship_other?: string;
    emergency_contact_relationship?: string;
    emergency_contact_number?: string;
    /**
     * Step 1 consent gate (W6). Client-side only; the server records
     * consent_accepted_at at finalize() regardless. Not sent as a
     * separate field — the checkbox state lives in the wizard.
     */
    consent_accepted?: boolean;
    email: string;
    contact_number: string;
    /**
     * Legacy demographic field. Accepted by the server since
     * pre-swap challenges and direct callers still submit it.
     * Optional on this type so bulk walk-in rows (which still
     * collect age only) continue to compile. When the public form
     * collects a birthday, this stays empty.
     */
    age?: string;
    /**
     * Preferred demographic field. ISO date `YYYY-MM-DD`. Bounded
     * to the age 3-120 envelope by both client and server. When
     * present, the server writes it to Participant::birthday and the
     * Participant accessor derives age from it on read.
     */
    birthday?: string;
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
const ADDRESS_HAS_CONSONANT_OR_DIGIT =
    /[bcdfghjklmnpqrstvwxzBCDFGHJKLMNPQRSTVWXZ0-9]/;
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
 * ISO date string for "today minus N years" (local midnight).
 *
 * Used to build both the string-form bounds (for comparison) and
 * the Date-form bounds (for the DatePicker's minDate/maxDate props
 * — see birthdayPickerBounds()). The two forms are always in sync
 * because both derive from the same Date math.
 *
 * Edge case note: on leap-day anniversaries, JS Date#setFullYear
 * rolls Feb 29 forward to Mar 1 in non-leap years. Laravel's
 * Carbon::subYears clamps backward to Feb 28. Off-by-one-day
 * difference in a 1-in-1460-days scenario; server is authoritative.
 */
function isoYearsAgo(years: number): string {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    d.setFullYear(d.getFullYear() - years);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

/**
 * Earliest and latest acceptable birthday dates, in Date form.
 *
 * Exported so the DatePicker call site (public.tsx, Block 6) can
 * pass the same bounds the client-side validator enforces.
 *
 * Returns:
 *   minDate = today - 120 years  (oldest person allowed)
 *   maxDate = today - 3 years    (youngest person allowed)
 *
 * Both bounds are inclusive — a person aged exactly 3 today and a
 * person aged exactly 120 today are both accepted. Matches the
 * server-side before_or_equal / after_or_equal rules on
 * PublicRegistrationRequest.
 */
export function birthdayPickerBounds(): { minDate: Date; maxDate: Date } {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const min = new Date(today);
    min.setFullYear(min.getFullYear() - 120);

    const max = new Date(today);
    max.setFullYear(max.getFullYear() - 3);

    return { minDate: min, maxDate: max };
}

/**
 * Name-field validation: blank, length, quality.
 *
 * The quality check reuses the shared humanNameQualityError() helper
 * so that "sdfasf" is rejected identically here and in the wizard.
 * Returns the error message or null when the value passes.
 */
function validateNameValue(value: string, label: string): string | null {
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

/**
 * Birthday validation: ISO format, age 3-120 bounds.
 *
 * Mirrors PublicRegistrationRequest's `birthday` rules:
 *   nullable|date|before_or_equal:(today-3y)|after_or_equal:(today-120y)
 *
 * ISO dates compare correctly as strings, so the bounds check is a
 * plain `<=` / `>=` against the string forms. No Date construction
 * needed at comparison time.
 *
 * Returns the error message or null.
 */
function validateBirthday(value: string): string | null {
    if (!DATE_PATTERN.test(value)) {
        return 'Please enter a valid date of birth.';
    }

    const maxDate = isoYearsAgo(3); // youngest allowed (must be >= this)
    const minDate = isoYearsAgo(120); // oldest allowed (must be <= this)

    if (value > maxDate) {
        return 'You must be at least 3 years old to register.';
    }
    if (value < minDate) {
        return 'Please enter a valid date of birth.';
    }

    return null;
}

/**
 * Non-blocking warning when the emergency contact number matches the
 * participant's own number. Decision 2026-10-19: warn, don't block.
 * The wizard surfaces this next to the field; validation still passes.
 */
export function emergencyContactWarning(
    data: Pick<
        RegistrationFormData,
        'contact_number' | 'emergency_contact_number'
    >,
): string | null {
    const own =
        typeof data.contact_number === 'string'
            ? normalizePhone(data.contact_number)
            : null;
    const ec =
        typeof data.emergency_contact_number === 'string'
            ? normalizePhone(data.emergency_contact_number)
            : null;

    if (own !== null && ec !== null && own === ec) {
        return 'This is the same as your contact number. Make sure someone else can be reached in an emergency.';
    }

    return null;
}

export function validateRegistrationForm(
    data: RegistrationFormData,
    fields: ServerField[],
    commonFieldRequirements: Record<string, boolean> = {},
    formVersion = 1,
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

    // -------- Optional name parts (composite identity key in W7) --------

    // Both are optional — blank is valid. When present, middle name
    // gets the same quality checks as first/last; suffix is limited
    // to letters and periods (Jr., Sr., II, III…), mirroring
    // PublicRegistrationRequest.
    if (!isBlank(data.middle_name) && typeof data.middle_name === 'string') {
        const middleNameError = validateNameValue(
            data.middle_name,
            'Middle name',
        );
        if (middleNameError) {
            errors.middle_name = middleNameError;
        }
    }

    if (!isBlank(data.suffix) && typeof data.suffix === 'string') {
        if (data.suffix.length > 16 || !/^[\p{L}.]+$/u.test(data.suffix)) {
            errors.suffix = 'Please enter a valid suffix (e.g. Jr., Sr., II).';
        }
    }

    // -------- Demographics: age or birthday --------

    // Both fields are optional on the type so not-yet-migrated
    // callers keep compiling. `tracksBirthday` distinguishes callers
    // that supply a birthday field at all — the public form, the
    // single walk-in dialog, and bulk walk-in rows (Phase 4) do.
    const tracksBirthday = data.birthday !== undefined;
    const ageBlank = isBlank(data.age);
    const birthdayBlank = isBlank(data.birthday);

    // Individual age range check (legacy path — only when provided).
    if (!ageBlank) {
        const age = Number(data.age);
        if (!Number.isInteger(age) || age < 1 || age > 120) {
            errors.age = 'Age must be a whole number between 1 and 120.';
        }
    }

    // -------- Emergency contact (Step 3) --------

    // Required at intake; stored per-registration. Validated only when
    // the caller supplies the fields (the wizard does; the legacy
    // single-page form does not yet) — same tracksX gate pattern as
    // birthday.
    const tracksEmergencyContact =
        data.emergency_contact_name !== undefined || formVersion >= 2;

    if (tracksEmergencyContact) {
        const ecNameError = validateNameValue(
            typeof data.emergency_contact_name === 'string'
                ? data.emergency_contact_name
                : '',
            'Emergency contact name',
        );
        if (ecNameError && formVersion < 2) {
            errors.emergency_contact_name = ecNameError;
        }

        if (isBlank(data.emergency_contact_relationship)) {
            errors.emergency_contact_relationship = 'Relationship is required.';
        } else if (
            typeof data.emergency_contact_relationship === 'string' &&
            data.emergency_contact_relationship.length > 64
        ) {
            errors.emergency_contact_relationship =
                'Relationship must not exceed 64 characters.';
        }

        if (isBlank(data.emergency_contact_number)) {
            errors.emergency_contact_number =
                'Emergency contact number is required.';
        } else if (typeof data.emergency_contact_number === 'string') {
            const ecContactError = validateContactNumber(
                data.emergency_contact_number,
            );
            if (ecContactError) {
                errors.emergency_contact_number = ecContactError;
            }
            if (!/^(?:09|\+639)[0-9]{9}$/.test(data.emergency_contact_number)) {
                errors.emergency_contact_number =
                    'Enter a valid Philippine mobile number: 09xxxxxxxxx or +639xxxxxxxxx.';
            }
        }
    }

    if (formVersion >= 2) {
        const nameKeys = [
            'first_name',
            'last_name',
            'emergency_contact_first_name',
            'emergency_contact_last_name',
        ] as const;
        for (const key of [...nameKeys, 'middle_name'] as const) {
            const value = (data[key] ?? '').trim();
            if (key === 'middle_name' && value === '') continue;
            if (
                value.length < 2 ||
                value.length > 50 ||
                !/^[\p{L}\p{M} '-]+$/u.test(value)
            ) {
                errors[key] =
                    'Use 2–50 characters: letters, spaces, hyphens, or apostrophes.';
            } else if (key.startsWith('emergency_contact_')) {
                const label = key.endsWith('first_name')
                    ? 'Emergency contact first name'
                    : 'Emergency contact last name';
                const qualityError = humanNameQualityError(value, label);
                if (qualityError) {
                    errors[key] = qualityError;
                } else {
                    delete errors[key];
                }
            } else {
                delete errors[key];
            }
        }
        if ((data.suffix ?? '').length > 10)
            errors.suffix = 'Suffix must not exceed 10 characters.';
        for (const key of [
            'contact_number',
            'emergency_contact_number',
        ] as const) {
            const value = data[key] ?? '';
            if (value && !/^(?:09|\+639)[0-9]{9}$/.test(value))
                errors[key] =
                    'Enter a valid Philippine mobile number: 09xxxxxxxxx or +639xxxxxxxxx.';
        }
        if (
            ![
                'Parent',
                'Sibling',
                'Spouse',
                'Friend',
                'Guardian',
                'Other',
            ].includes(data.emergency_contact_relationship ?? '')
        ) {
            errors.emergency_contact_relationship = 'Choose a relationship.';
        }
        if (data.emergency_contact_relationship === 'Other') {
            const other = (
                data.emergency_contact_relationship_other ?? ''
            ).trim();
            if (
                !/^[\p{L}\p{M} '-]{2,15}$/u.test(other) ||
                !/\p{L}/u.test(other)
            )
                errors.emergency_contact_relationship_other =
                    'Specify the relationship using 2–15 letters, spaces, apostrophes or hyphens.';
        }
        if (data.address.trim().length < 3 || data.address.length > 255)
            errors.address = 'Enter barangay and city using 3–255 characters.';
    }

    // Individual birthday format/bounds check (new path — only when
    // provided). Invalid birthday always errors even if a valid age
    // is also supplied, because the DOB is what will be persisted
    // (server writes birthday in preference to age).
    if (!birthdayBlank && typeof data.birthday === 'string') {
        const birthdayError = validateBirthday(data.birthday);
        if (birthdayError) {
            errors.birthday = birthdayError;
        }
    }

    // At-least-one requirement. `birthday` is preferred as the error
    // key because that is what the current UI collects; the server
    // mirrors this preference in withValidator().
    if (ageBlank && birthdayBlank) {
        if (tracksBirthday) {
            errors.birthday = 'Please provide your date of birth.';
        } else {
            errors.age = 'Age is required.';
        }
    }

    // -------- Contact fields --------

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
    const hasRequiredEmail = isRequired('email') && !isBlank(data.email);
    const hasRequiredPhone =
        isRequired('contact_number') &&
        !isBlank(data.contact_number) &&
        normalizePhone(data.contact_number) !== null;

    if (!hasRequiredEmail && !hasRequiredPhone) {
        errors.identity =
            'Please provide at least an email address or a contact number.';
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
                if (
                    typeof value !== 'string' ||
                    !/^-?\d+(\.\d+)?$/.test(value)
                ) {
                    errors[key] = 'Please enter a number.';
                }
                break;

            case 'select':
            case 'radio':
                if (
                    typeof value !== 'string' ||
                    !field.options.includes(value)
                ) {
                    errors[key] = 'Please choose one of the available options.';
                }
                break;

            case 'checkbox':
                if (
                    !Array.isArray(value) ||
                    value.some((v) => !field.options.includes(v))
                ) {
                    errors[key] = 'Please choose from the available options.';
                }
                break;
        }
    }

    return errors;
}
