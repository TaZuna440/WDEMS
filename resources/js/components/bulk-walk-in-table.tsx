import { Plus, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    makeBulkWalkInRow,
    type BulkWalkInRow,
} from '@/lib/attendance-bulk-validation';

type CommonFieldRequirements = {
    email: boolean;
    contact_number: boolean;
    address: boolean;
};

type ServerField = {
    id: number;
    label: string;
    field_type: string;
    options: string[];
    is_required: boolean;
};

type Props = {
    rows: BulkWalkInRow[];
    fields: ServerField[];
    requirements: CommonFieldRequirements;
    errors: Record<string, string>;
    onChange: (rows: BulkWalkInRow[]) => void;
    disabled?: boolean;
    maxRows: number;
};

/**
 * Bulk walk-in entry table.
 *
 * One row per participant. Each row carries its own client_uuid for
 * idempotency and its own responses map for the event's custom
 * fields. Common fields (name, age, email, phone, address) render
 * on every row.
 *
 * Per-row errors come in through the `errors` prop keyed as
 * `rows.{i}.{field}` — same shape the server returns. InputError
 * reads the string and renders it under the field. Rows with errors
 * are marked with a left border so the operator can spot them in a
 * long list.
 *
 * No per-row dialogs, no per-row submits. The whole table is
 * submitted together by the parent.
 */
export default function BulkWalkInTable({
    rows,
    fields,
    requirements,
    errors,
    onChange,
    disabled = false,
    maxRows,
}: Props) {
    const updateRow = (index: number, patch: Partial<BulkWalkInRow>) => {
        const next = rows.slice();
        next[index] = { ...next[index], ...patch };
        onChange(next);
    };

    const updateResponse = (
        rowIndex: number,
        fieldId: number,
        value: string | string[],
    ) => {
        const next = rows.slice();
        next[rowIndex] = {
            ...next[rowIndex],
            responses: {
                ...next[rowIndex].responses,
                [fieldId]: value,
            },
        };
        onChange(next);
    };

    const toggleCheckbox = (
        rowIndex: number,
        fieldId: number,
        option: string,
    ) => {
        const current = rows[rowIndex].responses[fieldId];
        const list = Array.isArray(current) ? current : [];
        const next = list.includes(option)
            ? list.filter((v) => v !== option)
            : [...list, option];
        updateResponse(rowIndex, fieldId, next);
    };

    const addRow = () => {
        if (rows.length >= maxRows) return;
        onChange([...rows, makeBulkWalkInRow()]);
    };

    const removeRow = (index: number) => {
        if (rows.length <= 1) return;
        const next = rows.slice();
        next.splice(index, 1);
        onChange(next);
    };

    const rowHasError = (index: number): boolean =>
        Object.keys(errors).some((key) => key.startsWith(`rows.${index}.`));

    const requiredMark = <span className="text-destructive"> *</span>;

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-col gap-4">
                {rows.map((row, i) => (
                    <div
                        key={row.client_uuid}
                        className={`rounded-lg border bg-white/[0.02] p-4 ${
                            rowHasError(i)
                                ? 'border-destructive/40'
                                : 'border-white/10'
                        }`}
                    >
                        <div className="mb-3 flex items-center justify-between">
                            <span className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                Row {i + 1}
                            </span>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => removeRow(i)}
                                disabled={disabled || rows.length <= 1}
                                className="h-7 w-7 p-0 text-muted-foreground hover:text-destructive"
                                aria-label={`Remove row ${i + 1}`}
                            >
                                <Trash2 className="h-3.5 w-3.5" />
                            </Button>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor={`rows.${i}.first_name`}>
                                    First name{requiredMark}
                                </Label>
                                <Input
                                    id={`rows.${i}.first_name`}
                                    value={row.first_name}
                                    onChange={(e) =>
                                        updateRow(i, {
                                            first_name: e.target.value,
                                        })
                                    }
                                    autoComplete="off"
                                    disabled={disabled}
                                />
                                <InputError
                                    message={errors[`rows.${i}.first_name`]}
                                />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor={`rows.${i}.last_name`}>
                                    Last name{requiredMark}
                                </Label>
                                <Input
                                    id={`rows.${i}.last_name`}
                                    value={row.last_name}
                                    onChange={(e) =>
                                        updateRow(i, {
                                            last_name: e.target.value,
                                        })
                                    }
                                    autoComplete="off"
                                    disabled={disabled}
                                />
                                <InputError
                                    message={errors[`rows.${i}.last_name`]}
                                />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor={`rows.${i}.age`}>
                                    Age{requiredMark}
                                </Label>
                                <Input
                                    id={`rows.${i}.age`}
                                    type="number"
                                    inputMode="numeric"
                                    min={1}
                                    max={120}
                                    value={row.age}
                                    onChange={(e) =>
                                        updateRow(i, { age: e.target.value })
                                    }
                                    autoComplete="off"
                                    disabled={disabled}
                                />
                                <InputError
                                    message={errors[`rows.${i}.age`]}
                                />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor={`rows.${i}.email`}>
                                    Email
                                    {requirements.email && requiredMark}
                                </Label>
                                <Input
                                    id={`rows.${i}.email`}
                                    type="email"
                                    value={row.email}
                                    onChange={(e) =>
                                        updateRow(i, { email: e.target.value })
                                    }
                                    autoComplete="off"
                                    disabled={disabled}
                                />
                                <InputError
                                    message={errors[`rows.${i}.email`]}
                                />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor={`rows.${i}.contact_number`}>
                                    Contact number
                                    {requirements.contact_number && requiredMark}
                                </Label>
                                <Input
                                    id={`rows.${i}.contact_number`}
                                    type="tel"
                                    value={row.contact_number}
                                    onChange={(e) =>
                                        updateRow(i, {
                                            contact_number: e.target.value,
                                        })
                                    }
                                    autoComplete="off"
                                    disabled={disabled}
                                />
                                <InputError
                                    message={
                                        errors[`rows.${i}.contact_number`]
                                    }
                                />
                            </div>

                            <div className="grid gap-1.5 sm:col-span-2">
                                <Label htmlFor={`rows.${i}.address`}>
                                    Address
                                    {requirements.address && requiredMark}
                                </Label>
                                <Input
                                    id={`rows.${i}.address`}
                                    value={row.address}
                                    onChange={(e) =>
                                        updateRow(i, {
                                            address: e.target.value,
                                        })
                                    }
                                    autoComplete="off"
                                    disabled={disabled}
                                />
                                <InputError
                                    message={errors[`rows.${i}.address`]}
                                />
                            </div>
                        </div>

                        {fields.length > 0 && (
                            <div className="mt-4 flex flex-col gap-3 border-t border-white/5 pt-4">
                                <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    Additional information
                                </p>

                                {fields.map((field) => {
                                    const errorKey = `rows.${i}.responses.${field.id}`;
                                    const error = errors[errorKey];
                                    const value = row.responses[field.id];

                                    return (
                                        <div
                                            key={field.id}
                                            className="grid gap-1.5"
                                        >
                                            <Label
                                                htmlFor={`rows.${i}.field-${field.id}`}
                                            >
                                                {field.label}
                                                {field.is_required &&
                                                    requiredMark}
                                            </Label>

                                            {field.field_type ===
                                                'textarea' && (
                                                <textarea
                                                    id={`rows.${i}.field-${field.id}`}
                                                    value={
                                                        typeof value ===
                                                        'string'
                                                            ? value
                                                            : ''
                                                    }
                                                    onChange={(e) =>
                                                        updateResponse(
                                                            i,
                                                            field.id,
                                                            e.target.value,
                                                        )
                                                    }
                                                    rows={3}
                                                    disabled={disabled}
                                                    className="flex min-h-[60px] w-full rounded-md border border-white/10 bg-white/[0.02] px-3 py-2 text-sm text-foreground focus:border-lime-brand/50 focus:outline-none focus:ring-1 focus:ring-lime-brand/50"
                                                />
                                            )}

                                            {field.field_type === 'select' && (
                                                <select
                                                    id={`rows.${i}.field-${field.id}`}
                                                    value={
                                                        typeof value ===
                                                        'string'
                                                            ? value
                                                            : ''
                                                    }
                                                    onChange={(e) =>
                                                        updateResponse(
                                                            i,
                                                            field.id,
                                                            e.target.value,
                                                        )
                                                    }
                                                    disabled={disabled}
                                                    className="h-9 w-full rounded-md border border-white/10 bg-white/[0.02] px-3 text-sm text-foreground focus:border-lime-brand/50 focus:outline-none disabled:opacity-50"
                                                >
                                                    <option value="">
                                                        Choose…
                                                    </option>
                                                    {field.options.map(
                                                        (option) => (
                                                            <option
                                                                key={option}
                                                                value={option}
                                                            >
                                                                {option}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            )}

                                            {field.field_type === 'radio' && (
                                                <div className="flex flex-col gap-1.5 pt-1">
                                                    {field.options.map(
                                                        (option) => (
                                                            <label
                                                                key={option}
                                                                className="flex cursor-pointer items-center gap-2 text-sm text-foreground"
                                                            >
                                                                <input
                                                                    type="radio"
                                                                    name={`rows.${i}.field-${field.id}`}
                                                                    value={
                                                                        option
                                                                    }
                                                                    checked={
                                                                        value ===
                                                                        option
                                                                    }
                                                                    onChange={() =>
                                                                        updateResponse(
                                                                            i,
                                                                            field.id,
                                                                            option,
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        disabled
                                                                    }
                                                                    className="h-4 w-4"
                                                                />
                                                                {option}
                                                            </label>
                                                        ),
                                                    )}
                                                </div>
                                            )}

                                            {field.field_type ===
                                                'checkbox' && (
                                                <div className="flex flex-col gap-1.5 pt-1">
                                                    {field.options.map(
                                                        (option) => {
                                                            const list =
                                                                Array.isArray(
                                                                    value,
                                                                )
                                                                    ? value
                                                                    : [];
                                                            return (
                                                                <label
                                                                    key={
                                                                        option
                                                                    }
                                                                    className="flex cursor-pointer items-center gap-2 text-sm text-foreground"
                                                                >
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={list.includes(
                                                                            option,
                                                                        )}
                                                                        onChange={() =>
                                                                            toggleCheckbox(
                                                                                i,
                                                                                field.id,
                                                                                option,
                                                                            )
                                                                        }
                                                                        disabled={
                                                                            disabled
                                                                        }
                                                                        className="h-4 w-4"
                                                                    />
                                                                    {option}
                                                                </label>
                                                            );
                                                        },
                                                    )}
                                                </div>
                                            )}

                                            {(field.field_type === 'text' ||
                                                field.field_type ===
                                                    'number' ||
                                                field.field_type ===
                                                    'email' ||
                                                field.field_type ===
                                                    'date') && (
                                                <Input
                                                    id={`rows.${i}.field-${field.id}`}
                                                    type={
                                                        field.field_type ===
                                                        'date'
                                                            ? 'date'
                                                            : field.field_type ===
                                                                'email'
                                                              ? 'email'
                                                              : field.field_type ===
                                                                  'number'
                                                                ? 'number'
                                                                : 'text'
                                                    }
                                                    value={
                                                        typeof value ===
                                                        'string'
                                                            ? value
                                                            : ''
                                                    }
                                                    onChange={(e) =>
                                                        updateResponse(
                                                            i,
                                                            field.id,
                                                            e.target.value,
                                                        )
                                                    }
                                                    autoComplete="off"
                                                    disabled={disabled}
                                                />
                                            )}

                                            <InputError message={error} />
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                ))}
            </div>

            <div className="flex items-center justify-between">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={addRow}
                    disabled={disabled || rows.length >= maxRows}
                >
                    <Plus className="mr-2 h-3.5 w-3.5" />
                    Add row
                </Button>

                <p className="text-xs text-muted-foreground">
                    {rows.length} of {maxRows} rows
                </p>
            </div>
        </div>
    );
}
