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
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/input-error';
import RegistrationDisclaimerEditor from '@/components/registration-disclaimer-editor';
import TshirtFieldBuilder, {
    validateTshirt,
    type TshirtDraft,
    type TshirtPhoto,
} from '@/components/tshirt-field-builder';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import RegistrationFieldEditor, {
    type FieldDraft,
} from '@/components/registration-field-editor';
import SortableFieldList from '@/components/sortable-field-list';
import { useConfirmDialog } from '@/hooks/use-confirm-dialog';
import {
    MAX_FIELDS,
    validateRegistrationForm,
} from '@/lib/registration-field-validation';

type EventData = {
    id: number;
    event_name: string;
    status: string;
    status_label: string;
    can_edit: boolean;
    form_saved: boolean;
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
    common_field_requirements: Record<string, boolean>;
    disclaimer_html: string | null;
    default_disclaimer_html: string;
    tshirt: {
        label: string;
        required: boolean;
        choices: string[];
        photos: TshirtPhoto[];
    } | null;
};

const statusStyles: Record<string, string> = {
    draft: 'bg-secondary text-foreground',
    registration_open: 'bg-lime-brand/20 text-lime-brand',
    registration_closed:
        'bg-[#13253B] text-white dark:bg-yellow-500/15 dark:text-yellow-500',
    ongoing: 'bg-accent/20 text-accent',
    completed: 'bg-green-500/15 text-green-500',
    cancelled: 'bg-destructive/15 text-destructive',
};

type CommonFieldSpec = {
    key: string;
    label: string;
    toggleable: boolean;
};

const COMMON_FIELDS: CommonFieldSpec[] = [
    { key: 'first_name', label: 'First name', toggleable: false },
    { key: 'last_name', label: 'Last name', toggleable: false },
    { key: 'middle_name', label: 'Middle name', toggleable: false },
    { key: 'suffix', label: 'Suffix', toggleable: false },
    { key: 'email', label: 'Email', toggleable: false },
    { key: 'contact_number', label: 'Contact number', toggleable: true },
    { key: 'age', label: 'Date of birth', toggleable: false },
    { key: 'address', label: 'Barangay & City', toggleable: false },
];

function generateClientId(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }
    return Math.random().toString(36).slice(2) + Date.now().toString(36);
}

export default function RegistrationForm({
    event,
    fields,
    common_field_requirements,
    disclaimer_html,
    default_disclaimer_html,
    tshirt,
}: Props) {
    const [disclaimerOpen, setDisclaimerOpen] = useState(false);
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

    const form = useForm<
        TshirtDraft & {
            fields: FieldDraft[];
            common_field_requirements: Record<string, boolean>;
            disclaimer_html: string | null;
        }
    >({
        fields: initialFields,
        disclaimer_html,
        tshirt_enabled: tshirt !== null,
        tshirt_label: tshirt?.label ?? 'T-shirt Size',
        tshirt_required: tshirt?.required ?? true,
        tshirt_choices: tshirt?.choices ?? ['Small', 'Medium', 'Large'],
        tshirt_existing_photos: tshirt?.photos.map((photo) => photo.path) ?? [],
        tshirt_photos: [],
        common_field_requirements: event.can_edit
            ? { ...common_field_requirements, email: true, address: true }
            : common_field_requirements,
    });

    const clientErrors = hasAttemptedSave
        ? {
              ...validateRegistrationForm(form.data.fields),
              ...validateTshirt(form.data, tshirt?.photos ?? []),
          }
        : {};
    const allErrors: Record<string, string> = {
        ...form.errors,
        ...clientErrors,
    };

    const canEdit = event.can_edit;

    /**
     * Clear any server-side errors whose key matches the given prefix.
     * Used so a red error under a field disappears the moment the
     * organizer starts editing that field — matching the pattern
     * established for the event wizard in FIX-022.
     */
    const clearServerErrors = (prefix: string) => {
        const matching = Object.keys(form.errors).filter(
            (k) => k === prefix || k.startsWith(`${prefix}.`),
        );

        if (matching.length > 0) {
            form.clearErrors(...(matching as (keyof typeof form.data)[]));
        }
    };

    const addField = () => {
        // Adding a new row shifts every index, so old per-field
        // errors are no longer meaningful. Clear them all.
        clearServerErrors('fields');

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
        // Clear errors for this specific field only — the user is
        // fixing it. Other rows' errors are still valid.
        clearServerErrors(`fields.${index}`);

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
                // Removing shifts indices; per-field errors are stale.
                clearServerErrors('fields');

                form.setData(
                    'fields',
                    form.data.fields.filter((_, i) => i !== index),
                );
            },
        });
    };

    const reorderFields = (next: FieldDraft[]) => {
        // Reordering shifts indices; per-field errors are stale.
        clearServerErrors('fields');

        form.setData('fields', next);
    };

    const toggleRequirement = (key: string, value: boolean) => {
        clearServerErrors('common_field_requirements');

        form.setData('common_field_requirements', {
            ...form.data.common_field_requirements,
            [key]: value,
        });
    };

    const saveForm = () => {
        setHasAttemptedSave(true);

        const errors = {
            ...validateRegistrationForm(form.data.fields),
            ...validateTshirt(form.data, tshirt?.photos ?? []),
        };
        if (
            form.data.disclaimer_html !== null &&
            !form.data.disclaimer_html.replace(/<[^>]*>/g, '').trim()
        ) {
            form.setError(
                'disclaimer_html',
                'The disclaimer must contain text.',
            );
            return;
        }
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
                    ...data,
                    _method: 'put',
                    fields: data.fields.map((field) => ({
                        label: field.label,
                        field_type: field.field_type,
                        options: field.options,
                        is_required: field.is_required,
                        validation_rules: field.validation_rules,
                    })),
                    common_field_requirements: data.common_field_requirements,
                    disclaimer_html: data.disclaimer_html,
                }));
                form.post(`/events/${event.id}/registration-form`, {
                    forceFormData: true,
                });
            },
        });
    };

    return (
        <>
            <Head title={`Registration Form — ${event.event_name}`} />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-6">
                <Link
                    href={`/events/${event.id}`}
                    className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-2 text-sm transition-colors"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Event
                </Link>

                <div>
                    <h1 className="text-foreground text-2xl font-semibold">
                        {event.form_saved
                            ? 'Edit Registration Form'
                            : 'Create Registration Form'}
                    </h1>
                    <div className="mt-2 flex items-center gap-3">
                        <span
                            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${
                                statusStyles[event.status] ?? statusStyles.draft
                            }`}
                        >
                            {event.status_label}
                        </span>
                        <span className="text-muted-foreground text-sm">
                            {event.event_name}
                        </span>
                    </div>
                    <p className="text-muted-foreground mt-3 text-sm">
                        Design the form participants will fill out to register.
                        Common fields are added automatically. Add custom fields
                        for anything event-specific.
                    </p>
                </div>

                <section
                    aria-labelledby="participant-workflow"
                    className="glass-panel rounded-xl p-5"
                >
                    <h2 id="participant-workflow" className="font-semibold">
                        Participant registration workflow
                    </h2>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Participants complete these steps in order. Build the
                        form with their experience in mind.
                    </p>
                    <ol className="mt-4 grid gap-4 sm:grid-cols-3">
                        {[
                            [
                                'Disclaimer',
                                'Read the participant agreement and privacy notice, then give consent.',
                            ],
                            [
                                'Personal Info',
                                'Enter personal details and answer your event-specific questions.',
                            ],
                            [
                                'Emergency Contact',
                                'Provide someone we can contact in an emergency.',
                            ],
                            [
                                'Verification',
                                'Verify contact details before registration is completed.',
                            ],
                            [
                                'Confirmation',
                                'Receive the registration code for event check-in.',
                            ],
                        ].map(([title, description], index) => (
                            <li
                                key={title}
                                className="border-border rounded-lg border p-3"
                            >
                                <p className="text-sm font-medium">
                                    {index + 1}. {title}
                                </p>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    {description}
                                </p>
                            </li>
                        ))}
                    </ol>
                    {event.form_saved && (
                        <Button variant="outline" className="mt-4" asChild>
                            <Link
                                href={`/events/${event.id}/registration-form/preview`}
                            >
                                View Saved Registration Form
                            </Link>
                        </Button>
                    )}
                </section>

                <section className="glass-panel space-y-3 rounded-xl p-5">
                    <h2 className="font-semibold">
                        Participant Disclaimer &amp; Agreement
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        {canEdit
                            ? 'Do you want to edit the participant agreement? The default applies unless you customize it.'
                            : 'The published participant agreement is locked.'}
                    </p>
                    <div
                        className="max-h-48 overflow-y-auto text-sm [&_h1]:text-2xl [&_h2]:text-xl [&_p]:my-3"
                        dangerouslySetInnerHTML={{
                            __html:
                                form.data.disclaimer_html ??
                                default_disclaimer_html,
                        }}
                    />
                    {canEdit && (
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                onClick={() => setDisclaimerOpen(true)}
                            >
                                Edit Disclaimer
                            </Button>
                            {form.data.disclaimer_html !== null && (
                                <Button
                                    variant="ghost"
                                    onClick={() =>
                                        form.setData('disclaimer_html', null)
                                    }
                                >
                                    Use Default
                                </Button>
                            )}
                        </div>
                    )}
                    <InputError message={allErrors.disclaimer_html} />
                </section>

                <section className="glass-panel space-y-2 rounded-xl p-5">
                    <h2 className="font-semibold">
                        Emergency contact &amp; verification
                    </h2>
                    {canEdit ? (
                        <>
                            <p className="text-muted-foreground text-sm">
                                Emergency contact first name, last name,
                                relationship, and phone number are required.
                                Relationships: Parent, Sibling, Spouse, Friend,
                                Guardian, or Other (up to 15 characters).
                            </p>
                            <p className="text-muted-foreground text-sm">
                                Newly saved forms verify email only. Requiring a
                                contact number does not enable phone
                                verification.
                            </p>
                        </>
                    ) : (
                        <p className="text-muted-foreground text-sm">
                            Published forms retain the rules they were opened
                            with. View the saved form to inspect the participant
                            experience.
                        </p>
                    )}
                </section>

                {!canEdit && (
                    <div className="glass-panel flex items-start gap-3 rounded-xl p-4">
                        <Lock className="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
                        <p className="text-muted-foreground text-sm">
                            This form is locked. It was published when
                            registration opened and can no longer be edited.
                        </p>
                    </div>
                )}

                <section className="flex flex-col gap-3">
                    <div className="flex items-baseline justify-between">
                        <h2 className="text-muted-foreground text-sm font-semibold tracking-wider uppercase">
                            Common Fields
                        </h2>
                        <span className="text-muted-foreground text-xs">
                            {canEdit
                                ? 'Toggle required per field'
                                : 'Automatic · locked'}
                        </span>
                    </div>
                    <p className="text-muted-foreground text-xs">
                        Every registration includes these fields. First name,
                        last name, date of birth, email, and barangay & city are
                        required on newly saved forms. Contact number can be
                        optional.
                    </p>

                    <div className="glass-panel grid gap-3 rounded-xl p-4 sm:grid-cols-2">
                        {COMMON_FIELDS.map((field) => {
                            const isRequired = [
                                'middle_name',
                                'suffix',
                            ].includes(field.key)
                                ? false
                                : field.toggleable || !canEdit
                                  ? (form.data.common_field_requirements[
                                        field.key
                                    ] ?? true)
                                  : true;

                            return (
                                <div
                                    key={field.key}
                                    className="border-border bg-card/40 flex items-center gap-3 rounded-md border px-3 py-2"
                                >
                                    <CheckCircle2 className="text-lime-brand/60 h-4 w-4 shrink-0" />
                                    <span className="text-foreground text-sm">
                                        {field.label}
                                    </span>

                                    {field.toggleable && canEdit ? (
                                        <label className="ml-auto flex cursor-pointer items-center gap-2">
                                            <Checkbox
                                                checked={isRequired}
                                                onCheckedChange={(checked) =>
                                                    toggleRequirement(
                                                        field.key,
                                                        checked === true,
                                                    )
                                                }
                                                aria-label={`Require ${field.label}`}
                                            />
                                            <span className="text-muted-foreground text-xs">
                                                Required
                                            </span>
                                        </label>
                                    ) : (
                                        <>
                                            {isRequired && (
                                                <span className="text-destructive text-xs">
                                                    *
                                                </span>
                                            )}
                                            {!isRequired && (
                                                <span className="text-muted-foreground ml-auto text-xs">
                                                    Optional
                                                </span>
                                            )}
                                        </>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                    <InputError message={allErrors.common_field_requirements} />
                </section>

                <TshirtFieldBuilder
                    data={form.data}
                    photos={tshirt?.photos ?? []}
                    errors={allErrors}
                    disabled={!canEdit}
                    onChange={(patch) => {
                        form.clearErrors();
                        form.setData({ ...form.data, ...patch });
                    }}
                />

                <section className="flex flex-col gap-3">
                    <div className="flex items-baseline justify-between">
                        <h2 className="text-muted-foreground text-sm font-semibold tracking-wider uppercase">
                            Custom Fields
                        </h2>
                        <span className="text-muted-foreground text-xs">
                            {form.data.fields.length} / {MAX_FIELDS}
                        </span>
                    </div>
                    <p className="text-muted-foreground text-xs">
                        Drag the handle to reorder. Changes are not saved until
                        you click Save.
                    </p>

                    {form.data.fields.length === 0 ? (
                        <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-12 text-center">
                            <div className="bg-lime-brand/15 mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                                <FileText className="text-lime-brand h-8 w-8" />
                            </div>
                            <h3 className="text-foreground mb-2 text-base font-semibold">
                                No custom fields yet
                            </h3>
                            <p className="text-muted-foreground mb-6 max-w-sm text-sm">
                                Add fields like Shirt Size, Emergency Contact,
                                or Distance Preference. The form can be saved
                                with just the common fields.
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
                                    disabled={
                                        form.data.fields.length >= MAX_FIELDS
                                    }
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Field
                                </Button>
                            )}
                        </>
                    )}

                    <InputError message={allErrors.fields} />
                </section>

                {canEdit && (
                    <div className="glass-panel sticky bottom-4 flex flex-col gap-3 rounded-xl p-4 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-muted-foreground text-xs">
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
            <Dialog open={disclaimerOpen} onOpenChange={setDisclaimerOpen}>
                <DialogContent className="sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Edit participant disclaimer</DialogTitle>
                        <DialogDescription>
                            Format the agreement with headings, bold, and italic
                            text.
                        </DialogDescription>
                    </DialogHeader>
                    <RegistrationDisclaimerEditor
                        value={
                            form.data.disclaimer_html ?? default_disclaimer_html
                        }
                        onChange={(html) => {
                            clearServerErrors('disclaimer_html');
                            form.setData('disclaimer_html', html);
                        }}
                    />
                    <Button onClick={() => setDisclaimerOpen(false)}>
                        Done
                    </Button>
                </DialogContent>
            </Dialog>
        </>
    );
}

RegistrationForm.layout = {
    breadcrumbs: [
        { title: 'Events', href: '/events' },
        { title: 'Registration Form', href: '#' },
    ],
};
