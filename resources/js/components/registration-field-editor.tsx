import { Plus, Trash2, X } from 'lucide-react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    FIELD_LABEL_PLACEHOLDER,
    FIELD_TYPES,
    FIELD_TYPE_HINTS,
    FIELD_TYPE_LABELS,
    isChoiceFieldType,
    OPTION_VALUE_PLACEHOLDER,
    type FieldType,
} from '@/lib/registration-field-types';

/**
 * Local draft shape for a single registration field.
 *
 * The `id` is a client-only identifier used by @dnd-kit for reordering.
 * It is never sent to the server — the server assigns display_order
 * from array position, and the batch update replaces all rows.
 */
export type FieldDraft = {
    id: string;
    label: string;
    field_type: string;
    options: string[];
    is_required: boolean;
    validation_rules: Record<string, string | number | boolean | null> | null;
};

type Props = {
    index: number;
    field: FieldDraft;
    onChange: (patch: Partial<FieldDraft>) => void;
    onRemove: () => void;
    handle: ReactNode;
    errors: Record<string, string>;
    disabled?: boolean;
};

export default function RegistrationFieldEditor({
    index,
    field,
    onChange,
    onRemove,
    handle,
    errors,
    disabled = false,
}: Props) {
    const labelError = errors[`fields.${index}.label`];
    const typeError = errors[`fields.${index}.field_type`];
    const optionsError = errors[`fields.${index}.options`];
    const isChoice = isChoiceFieldType(field.field_type);

    const addOption = () => {
        onChange({ options: [...field.options, ''] });
    };

    const updateOption = (optionIndex: number, value: string) => {
        const next = [...field.options];
        next[optionIndex] = value;
        onChange({ options: next });
    };

    const removeOption = (optionIndex: number) => {
        onChange({
            options: field.options.filter((_, i) => i !== optionIndex),
        });
    };

    const handleTypeChange = (value: string) => {
        // Switching to a non-choice type clears options. The validator
        // rejects options on text/number/email/date/textarea, so a stale
        // options array would fail on the next save. Choice-to-choice
        // changes (select → radio) preserve the options.
        onChange({
            field_type: value,
            options: isChoiceFieldType(value) ? field.options : [],
        });
    };

    return (
        <div className="flex items-start gap-2 rounded-lg border border-white/10 bg-white/[0.02] p-3">
            {handle}

            <div className="flex flex-1 flex-col gap-3">
                {/* Label + Type row */}
                <div className="grid gap-3 sm:grid-cols-[1fr_200px]">
                    <div className="grid gap-1.5">
                        <Label htmlFor={`field-label-${field.id}`}>
                            Field label
                        </Label>
                        <Input
                            id={`field-label-${field.id}`}
                            value={field.label}
                            onChange={(e) =>
                                onChange({ label: e.target.value })
                            }
                            placeholder={FIELD_LABEL_PLACEHOLDER}
                            disabled={disabled}
                            aria-invalid={!!labelError}
                        />
                        <InputError message={labelError} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor={`field-type-${field.id}`}>
                            Field type
                        </Label>
                        <Select
                            value={field.field_type}
                            onValueChange={handleTypeChange}
                            disabled={disabled}
                        >
                            <SelectTrigger
                                id={`field-type-${field.id}`}
                                aria-invalid={!!typeError}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {FIELD_TYPES.map((type) => (
                                    <SelectItem key={type} value={type}>
                                        {FIELD_TYPE_LABELS[type]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={typeError} />
                    </div>
                </div>

                {/* Type hint */}
                <p className="text-xs text-muted-foreground">
                    {FIELD_TYPE_HINTS[field.field_type as FieldType] ?? ''}
                </p>

                {/* Options editor — choice types only */}
                {isChoice && (
                    <div className="grid gap-2 rounded-md border border-white/10 bg-white/[0.02] p-3">
                        <div className="flex items-center justify-between">
                            <Label className="text-xs uppercase tracking-wider text-muted-foreground">
                                Choices
                            </Label>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={addOption}
                                disabled={disabled}
                                className="h-7 text-xs"
                            >
                                <Plus className="mr-1 h-3 w-3" />
                                Add choice
                            </Button>
                        </div>

                        {field.options.length === 0 ? (
                            <p className="text-xs text-muted-foreground">
                                Add at least two choices.
                            </p>
                        ) : (
                            <div className="flex flex-col gap-2">
                                {field.options.map((option, optionIndex) => {
                                    const optionError =
                                        errors[
                                            `fields.${index}.options.${optionIndex}`
                                        ];
                                    return (
                                        <div
                                            key={optionIndex}
                                            className="grid gap-1"
                                        >
                                            <div className="flex items-center gap-2">
                                                <Input
                                                    value={option}
                                                    onChange={(e) =>
                                                        updateOption(
                                                            optionIndex,
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder={
                                                        OPTION_VALUE_PLACEHOLDER
                                                    }
                                                    disabled={disabled}
                                                    aria-invalid={!!optionError}
                                                    className="flex-1"
                                                />
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        removeOption(optionIndex)
                                                    }
                                                    disabled={disabled}
                                                    aria-label={`Remove choice ${optionIndex + 1}`}
                                                    className="shrink-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                >
                                                    <X className="h-4 w-4" />
                                                </Button>
                                            </div>
                                            <InputError message={optionError} />
                                        </div>
                                    );
                                })}
                            </div>
                        )}

                        <InputError message={optionsError} />
                    </div>
                )}

                {/* Required toggle */}
                <div className="flex items-center gap-3">
                    <Checkbox
                        id={`field-required-${field.id}`}
                        checked={field.is_required}
                        onCheckedChange={(checked) =>
                            onChange({ is_required: checked === true })
                        }
                        disabled={disabled}
                    />
                    <Label
                        htmlFor={`field-required-${field.id}`}
                        className="cursor-pointer text-sm"
                    >
                        Required
                    </Label>
                </div>
            </div>

            {/* Remove field button */}
            <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={onRemove}
                disabled={disabled}
                aria-label={`Remove field ${index + 1}`}
                className="shrink-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
            >
                <Trash2 className="h-4 w-4" />
            </Button>
        </div>
    );
}
