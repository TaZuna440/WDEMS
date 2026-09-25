import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    FileText,
    Lock,
    Plus,
    Save,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import InputError from '@/components/input-error';
import RegistrationFieldEditor, {
    type FieldDraft,
} from '@/components/registration-field-editor';
import SortableFieldList from '@/components/sortable-field-list';
import { useConfirmDialog } from '@/hooks/use-confirm-dialog';
import {
    validateRegistrationForm,
} from '@/lib/registration-field-validation';

type EventData = {
    id: number;
    event_name: string;
    status: string;
    status_label: string;
    can_edit: boolean;
};

type ServerField = {
    id: number;
    label: string;
    field_type: string;
    options: string[] | null;
    is_required: boolean;
    validation_rules: Record<string, string | number | boolean | null> | null;
    display_order: number;
};

type Props = {
    event: EventData;
    fields: ServerField[];
};

const statusStyles: Record<string, string> = {
    draft: 'bg-secondary text-foreground',
    registration_open: 'bg-lime-brand/20 text-lime-brand',
    registration_closed: 'bg-yellow-500/15 text-yellow-500',
    ongoing: 'bg-accent/20 text-accent',
    completed: 'bg-green-500/15 text-green-500',
    cancelled: 'bg-destructive/15 text-destructive',
};

/**
 * The six structural participant fields. Every registration form
 * includes these automatically. They are not editable — they live in
 * the participants table and are the same for every event.
 */
const COMMON_FIELDS: { label: string; required: boolean }[] = [
    { label: 'First name', required: true },
    { label: 'Last name', required: true },
    { label: 'Email', required: true },
    { label: 'Contact number', required: true },
    { label: 'Age', required: true },
    { label: 'Address', required: false },
];

function generateClientId(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }
    return Math.random().toString(36).slice(2) + Date.now().toString(36);
}

export default function RegistrationForm({ event, fields }: Props) {
    const { dialog, openConfirm } = useConfirmDialog();
    const [hasAttemptedSave, setHasAttemptedSave] = useState(false);

    const initialFields: FieldDraft[] = useMemo(
        () =>
            fields.map((field) => ({
                id: generateClientId(),
                label: field.label,
                field_type: field.field_type,
                options: field.options ?? [],
                is_required: field.is_required,
                validation_rules: field.validation_rules,
            })),
        [fields],
    );

    const form = useForm<{ fields: FieldDraft[] }>({
        fields: initialFields,
    });

    const clientErrors = hasAttemptedSave
        ? validateRegistrationForm(form.data.fields)
        : {};
    const allErrors: Record<string, string> = {
        ...form.errors,
        ...clientErrors,
    };

    const canEdit = event.can_edit;

    const addField = () => {
        form.setData('fields', [
            ...form.data.fields,
            {
                id: generateClientId(),
                label: '',
                field_type: 'text',
                options: [],
                is_required: false,
                validation_rules: null,
            },
        ]);
    };

    const updateField = (index: number, patch: Partial<FieldDraft>) => {
        form.setData(
            'fields',
            form.data.fields.map((field, i) =>
                i === index ? { ...field, ...patch } : field,
            ),
        );
    };

    const removeField = (index: number) => {
        const target = form.data.fields[index];
        openConfirm({
            variant: 'danger',
            title: 'Remove this field?',
            description: `"${target?.label || 'Untitled field'}" will be removed from the form. Changes are not saved until you click Save.`,
            confirmLabel: 'Remove Field',
            onConfirm: () => {
                form.setData(
                    'fields',
                    form.data.fields.filter((_, i) => i !== index),
                );
            },
        });
    };

    const reorderFields = (next: FieldDraft[]) => {
        form.setData('fields', next);
    };

    const saveForm = () => {
        setHasAttemptedSave(true);

        const errors = validateRegistrationForm(form.data.fields);
        if (Object.keys(errors).length > 0) {
            return;
        }

        openConfirm({
            variant: 'primary',
            title: 'Save this registration form?',
            description:
                'The form will be saved. You can still edit it until you open registration. Once registration opens, the form locks and cannot be changed.',
            confirmLabel: 'Save Form',
            onConfirm: () => {
                form.transform((data) => ({
                    fields: data.fields.map((field) => ({
                        label: field.label,
                        field_type: field.field_type,
                        options: field.options,
                        is_required: field.is_required,
                        validation_rules: field.validation_rules,
                    })),
                }));
                form.put(`/events/${event.id}/registration-form`);
            },
        });
    };

    return (
        <>
            <Head title={`Registration Form — ${event.event_name}`} />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-6">
                <Link
                    href={`/events/${event.id}`}
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Event
                </Link>

                {/* Header */}
                <div>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Create Registration Form
                    </h1>
                    <div className="mt-2 flex items-center gap-3">
                        <span
                            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${
                                statusStyles[event.status] ??
                                statusStyles.draft
                            }`}
                        >
                            {event.status_label}
                        </span>
                        <span className="text-sm text-muted-foreground">
                            {event.event_name}
                        </span>
                    </div>
                    <p className="mt-3 text-sm text-muted-foreground">
                        Design the form participants will fill out to
                        register. Common fields are added automatically.
                        Add custom fields for anything event-specific.
                    </p>
                </div>

                {/* Locked banner */}
                {!canEdit && (
                    <div className="glass-panel flex items-start gap-3 rounded-xl p-4">
                        <Lock className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            This form is locked. It was published when
                            registration opened and can no longer be
                            edited.
                        </p>
                    </div>
                )}

                {/* Common fields — read-only */}
                <section className="flex flex-col gap-3">
                    <div className="flex items-baseline justify-between">
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                            Common Fields
                        </h2>
                        <span className="text-xs text-muted-foreground">
                            Automatic · not editable
                        </span>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Every registration includes these fields.
                    </p>

                    <div className="glass-panel grid gap-3 rounded-xl p-4 sm:grid-cols-2">
                        {COMMON_FIELDS.map((field) => (
                            <div
                                key={field.label}
                                className="flex items-center gap-3 rounded-md border border-white/5 bg-white/[0.02] px-3 py-2"
                            >
                                <CheckCircle2 className="h-4 w-4 shrink-0 text-lime-brand/60" />
                                <span className="text-sm text-foreground">
                                    {field.label}
                                </span>
                                {field.required && (
                                    <span className="text-xs text-destructive">
                                        *
                                    </span>
                                )}
                                {!field.required && (
                                    <span className="ml-auto text-xs text-muted-foreground">
                                        Optional
                                    </span>
                                )}
                            </div>
                        ))}
                    </div>
                </section>

                {/* Custom fields */}
                <section className="flex flex-col gap-3">
                    <div className="flex items-baseline justify-between">
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                            Custom Fields
                        </h2>
                        <span className="text-xs text-muted-foreground">
                            {form.data.fields.length} / 30
                        </span>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Drag the handle to reorder. Changes are not saved
                        until you click Save.
                    </p>

                    {form.data.fields.length === 0 ? (
                        <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-12 text-center">
                            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-lime-brand/15">
                                <FileText className="h-8 w-8 text-lime-brand" />
                            </div>
                            <h3 className="mb-2 text-base font-semibold text-foreground">
                                No custom fields yet
                            </h3>
                            <p className="mb-6 max-w-sm text-sm text-muted-foreground">
                                Add fields like Shirt Size, Emergency
                                Contact, or Distance Preference. The form
                                can be saved with just the common fields.
                            </p>
                            {canEdit && (
                                <Button
                                    type="button"
                                    onClick={addField}
                                    className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Custom Field
                                </Button>
                            )}
                        </div>
                    ) : (
                        <>
                            <SortableFieldList
                                items={form.data.fields}
                                onReorder={reorderFields}
                                disabled={!canEdit}
                            >
                                {(field, index, handle) => (
                                    <RegistrationFieldEditor
                                        index={index}
                                        field={field}
                                        onChange={(patch) =>
                                            updateField(index, patch)
                                        }
                                        onRemove={() => removeField(index)}
                                        handle={handle}
                                        errors={allErrors}
                                        disabled={!canEdit}
                                    />
                                )}
                            </SortableFieldList>

                            {canEdit && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={addField}
                                    className="w-fit"
                                    disabled={form.data.fields.length >= 30}
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Field
                                </Button>
                            )}
                        </>
                    )}

                    <InputError message={allErrors.fields} />
                </section>

                {/* Save bar */}
                {canEdit && (
                    <div className="glass-panel sticky bottom-4 flex flex-col gap-3 rounded-xl p-4 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-xs text-muted-foreground">
                            Fields save as one batch. Until you click Save,
                            nothing has been written.
                        </p>
                        <Button
                            type="button"
                            onClick={saveForm}
                            disabled={form.processing}
                            className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                        >
                            <Save className="mr-2 h-4 w-4" />
                            {form.processing
                                ? 'Saving...'
                                : 'Save Registration Form'}
                        </Button>
                    </div>
                )}
            </div>

            {dialog}
        </>
    );
}

RegistrationForm.layout = {
    breadcrumbs: [
        { title: 'Events', href: '/events' },
        { title: 'Registration Form', href: '#' },
    ],
};
