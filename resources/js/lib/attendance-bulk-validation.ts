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
 */

import {
    validateRegistrationForm,
    type RegistrationFormData,
    type ServerField,
} from '@/lib/public-registration-validation';

export type BulkWalkInRow = RegistrationFormData & {
    client_uuid: string;
};

export type BulkWalkInValidationResult = {
    errors: Record<string, string>;
    invalidRowIndexes: number[];
};

export function validateBulkWalkInRows(
    rows: BulkWalkInRow[],
    fields: ServerField[],
    commonFieldRequirements: Record<string, boolean> = {},
): BulkWalkInValidationResult {
    const errors: Record<string, string> = {};
    const invalidRowIndexes: number[] = [];

    rows.forEach((row, i) => {
        // Strip the client_uuid before passing to the shared validator.
        // It has no counterpart on RegistrationFormData, and the
        // validator would ignore it anyway — but keeping the shape
        // exact avoids surprises if the shared validator grows.
        const rowData: RegistrationFormData = {
            first_name: row.first_name,
            last_name: row.last_name,
            email: row.email,
            contact_number: row.contact_number,
            age: row.age,
            address: row.address,
            responses: row.responses,
        };

        const rowErrors = validateRegistrationForm(
            rowData,
            fields,
            commonFieldRequirements,
        );

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
 * input renders as blank.
 */
export function makeBulkWalkInRow(): BulkWalkInRow {
    return {
        client_uuid: crypto.randomUUID(),
        first_name: '',
        last_name: '',
        email: '',
        contact_number: '',
        age: '',
        address: '',
        responses: {},
    };
}
