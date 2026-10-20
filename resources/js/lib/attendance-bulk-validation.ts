/**
 * Client-side validation for the bulk walk-in table.
 *
 * Wraps validateRegistrationForm() from public-registration-validation
 * — the same per-row rules the public form and the single walk-in
 * dialog use. Namespaces the returned error keys under `rows.{i}.*`
 * so the table can render per-row feedback from a single error bag.
 *
 * Error key shape mirrors the server's BulkWalkInRegistrationRequest
 * and AttendanceController::bulkWalkIn(). Same keys, same messages,
 * server or client — the InputError slot does not need a translation
 * layer.
 *
 * The client never validates `client_uuid`. The table component
 * generates one per row from crypto.randomUUID() and keeps it stable
 * across edits. If somehow a row reaches the server without one, the
 * request's `uuid` rule catches it.
 *
 * The client DOES validate `arrival_time` when the batch is in
 * deferred encoding mode — a per-row HH:MM is required in that mode,
 * matching the server's Pass 3 in BulkWalkInRegistrationRequest.
 */

import {
    validateRegistrationForm,
    type RegistrationFormData,
    type ServerField,
} from '@/lib/public-registration-validation';

export type BulkWalkInRow = RegistrationFormData & {
    client_uuid: string;
    /**
     * HH:MM string. Only meaningful when the batch encoding mode is
     * 'deferred'. In live mode the server ignores this field and
     * stamps attendance_time with now().
     */
    arrival_time: string;
};

export type BulkWalkInValidationResult = {
    errors: Record<string, string>;
    invalidRowIndexes: number[];
};

export function validateBulkWalkInRows(
    rows: BulkWalkInRow[],
    fields: ServerField[],
    commonFieldRequirements: Record<string, boolean> = {},
    deferred = false,
): BulkWalkInValidationResult {
    const errors: Record<string, string> = {};
    const invalidRowIndexes: number[] = [];

    rows.forEach((row, i) => {
        // Strip the client-only fields before passing to the shared
        // validator. They have no counterpart on RegistrationFormData,
        // and the validator would ignore them anyway — but keeping the
        // shape exact avoids surprises if the shared validator grows.
        const rowData: RegistrationFormData = {
            first_name: row.first_name,
            last_name: row.last_name,
            middle_name: row.middle_name,
            suffix: row.suffix,
            email: row.email,
            contact_number: row.contact_number,
            age: row.age,
            birthday: row.birthday,
            address: row.address,
            responses: row.responses,
        };

        const rowErrors: Record<string, string> = {
            ...validateRegistrationForm(
                rowData,
                fields,
                commonFieldRequirements,
            ),
        };

        // Deferred-mode arrival time is required per row. The server
        // enforces the same rule in Pass 3 of
        // BulkWalkInRegistrationRequest.
        if (deferred && row.arrival_time.trim() === '') {
            rowErrors.arrival_time =
                'Arrival time is required for each row when transcribing from paper.';
        }

        const keys = Object.keys(rowErrors);

        if (keys.length === 0) {
            return;
        }

        invalidRowIndexes.push(i);

        for (const key of keys) {
            errors[`rows.${i}.${key}`] = rowErrors[key];
        }
    });

    return { errors, invalidRowIndexes };
}

/**
 * Generate a fresh row for the bulk walk-in table.
 *
 * The row is blank except for the client_uuid. Age is typed as
 * `string | number` on RegistrationFormData but starts empty so the
 * input renders as blank. arrival_time starts empty; the table only
 * renders its input when the batch is in deferred mode.
 */
export function makeBulkWalkInRow(): BulkWalkInRow {
    return {
        client_uuid: crypto.randomUUID(),
        first_name: '',
        last_name: '',
        middle_name: '',
        suffix: '',
        email: '',
        contact_number: '',
        age: '',
        birthday: '',
        address: '',
        responses: {},
        arrival_time: '',
    };
}
