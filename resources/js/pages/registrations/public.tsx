import { Head, useForm } from '@inertiajs/react';
import { CalendarDays, CheckCircle2, ExternalLink, MapPin } from 'lucide-react';
import { useMemo, useState } from 'react';
import DatePicker from '@/components/date-picker';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import Wizard, { type WizardStepConfig } from '@/components/wizard';
import PhoneInput from '@/components/phone-input';
import {
    birthdayPickerBounds,
    emergencyContactWarning,
    validateRegistrationForm,
    type RegistrationFormData,
    type ServerField,
} from '@/lib/public-registration-validation';

type EventPayload = {
    event_name: string;
    event_type: string | null;
    event_date: string | null;
    start_time: string | null;
    end_time: string | null;
    distance: string | null;
    venue: string | null;
    venue_address: string | null;
    venue_map_url: string | null;
    description: string | null;
    partners: { name: string; type: string }[];
    accessibility: string[];
};

type Props = {
    event: EventPayload;
    fields: ServerField[];
    common_field_requirements: Record<string, boolean>;
    submit_url: string;
    success: string | null;
    disclaimer_paragraphs: string[];
    privacy_consent_text: string;
    event_slug: string;
};

type CommonFieldKey =
    | 'first_name'
    | 'last_name'
    | 'middle_name'
    | 'suffix'
    | 'email'
    | 'contact_number'
    | 'birthday'
    | 'age'
    | 'address'
    | 'emergency_contact_name'
    | 'emergency_contact_relationship'
    | 'emergency_contact_number';

const CONSENT_ERROR =
    'You must agree to the Privacy Notice and Terms & Conditions.';

export default function PublicRegistration({
    event,
    fields,
    common_field_requirements,
    submit_url,
    success,
    disclaimer_paragraphs,
    privacy_consent_text,
    event_slug,
}: Props) {
    const form = useForm<RegistrationFormData>({
        first_name: '',
        last_name: '',
        middle_name: '',
        suffix: '',
        email: '',
        contact_number: '',
        age: '',
        birthday: '',
        address: '',
        emergency_contact_name: '',
        emergency_contact_relationship: '',
        emergency_contact_number: '',
        consent_accepted: false,
        responses: {},
    });

    const [privacyOpen, setPrivacyOpen] = useState(false);
    const [termsOpen, setTermsOpen] = useState(false);

    const birthdayBounds = useMemo(() => birthdayPickerBounds(), []);

    const isRequired = (field: string): boolean =>
        common_field_requirements[field] ?? true;

    const clearServerErrors = (key: string) => {
        const matching = Object.keys(form.errors).filter(
            (k) => k === key || k.startsWith(`${key}.`),
        );
        if (matching.length > 0) {
            form.clearErrors(...(matching as (keyof typeof form.data)[]));
        }
    };

    const onCommonChange = (key: CommonFieldKey, value: string | boolean) => {
        clearServerErrors(key);
        form.setData(key as keyof RegistrationFormData, value as never);
    };

    const setResponse = (fieldId: number, value: string | string[]) => {
        clearServerErrors(`responses.${fieldId}`);
        form.setData('responses', {
            ...form.data.responses,
            [fieldId]: value,
        });
    };

    const toggleCheckboxOption = (fieldId: number, option: string) => {
        const current = form.data.responses[fieldId];
        const list = Array.isArray(current) ? current : [];
        const next = list.includes(option)
            ? list.filter((v) => v !== option)
            : [...list, option];
        setResponse(fieldId, next);
    };

    const steps: WizardStepConfig[] = useMemo(
        () => [
            {
                id: 'disclaimer',
                label: 'Disclaimer',
                fields: ['consent_accepted'],
                validate: (data): Record<string, string> => {
                    const d = data as unknown as RegistrationFormData;
                    const out: Record<string, string> = {};
                    if (!d.consent_accepted) {
                        out.consent = CONSENT_ERROR;
                    }
                    return out;
                },
            },
            {
                id: 'personal',
                label: 'Personal Info',
                fields: [
                    'first_name',
                    'last_name',
                    'middle_name',
                    'suffix',
                    'email',
                    'contact_number',
                    'birthday',
                    'address',
                    'responses',
                ],
                validate: (data) => {
                    const all = validateRegistrationForm(
                        data as RegistrationFormData,
                        fields,
                        common_field_requirements,
                    );
                    const keep = new Set([
                        'first_name',
                        'last_name',
                        'middle_name',
                        'suffix',
                        'email',
                        'contact_number',
                        'birthday',
                        'age',
                        'address',
                    ]);
                    const out: Record<string, string> = {};
                    for (const [k, v] of Object.entries(all)) {
                        if (keep.has(k) || k.startsWith('responses.')) {
                            out[k] = v;
                        }
                    }
                    return out;
                },
            },
            {
                id: 'emergency',
                label: 'Emergency Contact',
                fields: [
                    'emergency_contact_name',
                    'emergency_contact_relationship',
                    'emergency_contact_number',
                ],
                validate: (data) => {
                    const all = validateRegistrationForm(
                        data as RegistrationFormData,
                        fields,
                        common_field_requirements,
                    );
                    const out: Record<string, string> = {};
                    for (const [k, v] of Object.entries(all)) {
                        if (k.startsWith('emergency_contact')) {
                            out[k] = v;
                        }
                    }
                    return out;
                },
            },
        ],
        [fields, common_field_requirements],
    );

    const submit = () => {
        form.post(submit_url);
    };

    if (success !== null) {
        return (
            <>
                <Head title={`Registered — ${event.event_name}`} />
                <div className="rounded-xl border border-lime-brand/30 bg-lime-brand/5 p-10 text-center">
                    <div className="mx-auto mb-6 flex h-14 w-14 items-center justify-center rounded-full bg-lime-brand/15">
                        <CheckCircle2 className="h-7 w-7 text-lime-brand" />
                    </div>
                    <h1 className="text-xl font-semibold text-foreground">
                        You are registered.
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {success}
                    </p>
                    <p className="mt-6 text-sm text-foreground">
                        {event.event_name}
                        {event.event_date !== null && ` · ${event.event_date}`}
                    </p>
                </div>
            </>
        );
    }

    const renderDisclaimerStep = (stepErrors: Record<string, string>) => (
        <section className="flex flex-col gap-6">
            <div className="flex flex-col gap-4">
                <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                    Participant Disclaimer & Agreement
                </h2>
                <div className="flex flex-col gap-3 rounded-xl border border-border bg-card/40 p-6">
                    {disclaimer_paragraphs.map((paragraph, i) => (
                        <p
                            key={i}
                            className="text-sm leading-relaxed text-foreground/80"
                        >
                            {paragraph}
                        </p>
                    ))}
                </div>
            </div>
            <div className="flex flex-col gap-4">
                <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                    Data Privacy Consent
                </h2>
                <div className="rounded-xl border border-border bg-card/40 p-6">
                    <p className="text-sm leading-relaxed text-foreground/80">
                        {privacy_consent_text}
                    </p>
                </div>
                <div className="grid gap-1.5">
                    <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-border bg-card/40 p-4 text-sm">
                        <input
                            type="checkbox"
                            checked={!!form.data.consent_accepted}
                            onChange={(e) =>
                                onCommonChange('consent_accepted' as CommonFieldKey, e.target.checked)
                            }
                            aria-invalid={!!stepErrors.consent}
                            className="mt-0.5 h-4 w-4 shrink-0"
                        />
                        <span className="text-muted-foreground">
                            I agree to the Participant Disclaimer & Agreement
                            and the Data Privacy Consent stated above.{' '}
                            <button
                                type="button"
                                onClick={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    setPrivacyOpen(true);
                                }}
                                className="font-medium text-lime-brand underline-offset-2 hover:underline"
                            >
                                Privacy Notice
                            </button>{' '}
                            and{' '}
                            <button
                                type="button"
                                onClick={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    setTermsOpen(true);
                                }}
                                className="font-medium text-lime-brand underline-offset-2 hover:underline"
                            >
                                Terms &amp; Conditions
                            </button>
                            .
                        </span>
                    </label>
                    <InputError message={stepErrors.consent} />
                </div>
            </div>
        </section>
    );

    const renderPersonalStep = (stepErrors: Record<string, string>) => (
        <>
            <section className="flex flex-col gap-4">
                <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                    Your Details
                </h2>
                <p className="text-xs text-muted-foreground">
                    * indicates required question
                </p>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-1.5">
                        <Label htmlFor="first_name">First name *</Label>
                        <Input
                            id="first_name"
                            placeholder="Juan"
                            value={form.data.first_name}
                            onChange={(e) =>
                                onCommonChange('first_name', e.target.value)
                            }
                            autoComplete="given-name"
                            aria-invalid={!!stepErrors.first_name}
                        />
                        <InputError message={stepErrors.first_name} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="last_name">Last name *</Label>
                        <Input
                            id="last_name"
                            placeholder="Dela Cruz"
                            value={form.data.last_name}
                            onChange={(e) =>
                                onCommonChange('last_name', e.target.value)
                            }
                            autoComplete="family-name"
                            aria-invalid={!!stepErrors.last_name}
                        />
                        <InputError message={stepErrors.last_name} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="middle_name">Middle name</Label>
                        <Input
                            id="middle_name"
                            placeholder="Santos (optional)"
                            value={form.data.middle_name ?? ''}
                            onChange={(e) =>
                                onCommonChange('middle_name', e.target.value)
                            }
                            autoComplete="additional-name"
                            aria-invalid={!!stepErrors.middle_name}
                        />
                        <InputError message={stepErrors.middle_name} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="suffix">Suffix</Label>
                        <Input
                            id="suffix"
                            placeholder="Jr. (optional)"
                            value={form.data.suffix ?? ''}
                            onChange={(e) =>
                                onCommonChange('suffix', e.target.value)
                            }
                            aria-invalid={!!stepErrors.suffix}
                        />
                        <InputError message={stepErrors.suffix} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="email">
                            Email{isRequired('email') && ' *'}
                        </Label>
                        <Input
                            id="email"
                            type="email"
                            inputMode="email"
                            placeholder="you@example.com"
                            value={form.data.email}
                            onChange={(e) =>
                                onCommonChange('email', e.target.value)
                            }
                            autoComplete="email"
                            aria-invalid={!!stepErrors.email}
                        />
                        <InputError message={stepErrors.email} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="contact_number">
                            Contact number{isRequired('contact_number') && ' *'}
                        </Label>
                        <PhoneInput
                            id="contact_number"
                            placeholder="09XXXXXXXXX or +639XXXXXXXXX"
                            value={form.data.contact_number}
                            onChange={(v) =>
                                onCommonChange('contact_number', v)
                            }
                            aria-invalid={!!stepErrors.contact_number}
                        />
                        <p className="text-xs text-muted-foreground">
                            PH mobile only — e.g. 09171234567 or +639171234567
                        </p>
                        <InputError message={stepErrors.contact_number} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="birthday">Date of birth *</Label>
                        <DatePicker
                            id="birthday"
                            value={form.data.birthday ?? ''}
                            onChange={(value) =>
                                onCommonChange('birthday', value)
                            }
                            placeholder="MM/DD/YYYY"
                            minDate={birthdayBounds.minDate}
                            maxDate={birthdayBounds.maxDate}
                            autoFocusTyped
                        />
                        <InputError message={stepErrors.birthday} />
                    </div>
                    <div className="grid gap-1.5 sm:col-span-2">
                        <Label htmlFor="address">
                            Barangay & City{isRequired('address') && ' *'}
                        </Label>
                        <Input
                            id="address"
                            placeholder="ex. Gen. T. De Leon, Valenzuela City"
                            value={form.data.address}
                            onChange={(e) =>
                                onCommonChange('address', e.target.value)
                            }
                            autoComplete="street-address"
                            aria-invalid={!!stepErrors.address}
                        />
                        <InputError message={stepErrors.address} />
                    </div>
                </div>
            </section>

            {fields.length > 0 && (
                <section className="flex flex-col gap-4">
                    <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                        Additional Information
                    </h2>
                    {fields.map((field) => {
                        const key = `responses.${field.id}`;
                        const error = stepErrors[key];
                        const value = form.data.responses[field.id];
                        return (
                            <div key={field.id} className="grid gap-1.5">
                                <Label htmlFor={`field-${field.id}`}>
                                    {field.label}
                                    {field.is_required && ' *'}
                                </Label>
                                {field.field_type === 'textarea' && (
                                    <textarea
                                        id={`field-${field.id}`}
                                        value={typeof value === 'string' ? value : ''}
                                        onChange={(e) =>
                                            setResponse(field.id, e.target.value)
                                        }
                                        rows={4}
                                        aria-invalid={!!error}
                                        className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm text-foreground shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40"
                                    />
                                )}
                                {field.field_type === 'select' && (
                                    <Select
                                        value={typeof value === 'string' ? value : ''}
                                        onValueChange={(v) => setResponse(field.id, v)}
                                    >
                                        <SelectTrigger
                                            id={`field-${field.id}`}
                                            className="w-full"
                                            aria-invalid={!!error}
                                        >
                                            <SelectValue placeholder="Choose…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {field.options.map((option) => (
                                                <SelectItem key={option} value={option}>
                                                    {option}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                                {field.field_type === 'radio' && (
                                    <div className="flex flex-col gap-2 pt-1">
                                        {field.options.map((option) => (
                                            <label
                                                key={option}
                                                className="flex cursor-pointer items-center gap-2 text-sm text-foreground"
                                            >
                                                <input
                                                    type="radio"
                                                    name={`field-${field.id}`}
                                                    value={option}
                                                    checked={value === option}
                                                    onChange={() =>
                                                        setResponse(field.id, option)
                                                    }
                                                    className="h-4 w-4"
                                                />
                                                {option}
                                            </label>
                                        ))}
                                    </div>
                                )}
                                {field.field_type === 'checkbox' && (
                                    <div className="flex flex-col gap-2 pt-1">
                                        {field.options.map((option) => {
                                            const list = Array.isArray(value) ? value : [];
                                            return (
                                                <label
                                                    key={option}
                                                    className="flex cursor-pointer items-center gap-2 text-sm text-foreground"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        value={option}
                                                        checked={list.includes(option)}
                                                        onChange={() =>
                                                            toggleCheckboxOption(field.id, option)
                                                        }
                                                        className="h-4 w-4"
                                                    />
                                                    {option}
                                                </label>
                                            );
                                        })}
                                    </div>
                                )}
                                {(field.field_type === 'text' ||
                                    field.field_type === 'number' ||
                                    field.field_type === 'email' ||
                                    field.field_type === 'date') && (
                                    <Input
                                        id={`field-${field.id}`}
                                        type={
                                            field.field_type === 'date'
                                                ? 'date'
                                                : field.field_type === 'email'
                                                  ? 'email'
                                                  : field.field_type === 'number'
                                                    ? 'number'
                                                    : 'text'
                                        }
                                        value={typeof value === 'string' ? value : ''}
                                        onChange={(e) =>
                                            setResponse(field.id, e.target.value)
                                        }
                                        aria-invalid={!!error}
                                    />
                                )}
                                <InputError message={error} />
                            </div>
                        );
                    })}
                </section>
            )}
        </>
    );

    const renderEmergencyStep = (stepErrors: Record<string, string>) => {
        const warning = emergencyContactWarning({
            contact_number: form.data.contact_number,
            emergency_contact_number: form.data.emergency_contact_number ?? '',
        });
        return (
            <section className="flex flex-col gap-4">
                <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                    Emergency Contact
                </h2>
                <p className="text-xs text-muted-foreground">
                    * indicates required question
                </p>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-1.5 sm:col-span-2">
                        <Label htmlFor="emergency_contact_name">
                            Contact name *
                        </Label>
                        <Input
                            id="emergency_contact_name"
                            placeholder="Jose Santos"
                            value={form.data.emergency_contact_name ?? ''}
                            onChange={(e) =>
                                onCommonChange('emergency_contact_name', e.target.value)
                            }
                            aria-invalid={!!stepErrors.emergency_contact_name}
                        />
                        <InputError message={stepErrors.emergency_contact_name} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="emergency_contact_relationship">
                            Relationship *
                        </Label>
                        <Input
                            id="emergency_contact_relationship"
                            placeholder="Father"
                            value={form.data.emergency_contact_relationship ?? ''}
                            onChange={(e) =>
                                onCommonChange(
                                    'emergency_contact_relationship',
                                    e.target.value,
                                )
                            }
                            aria-invalid={
                                !!stepErrors.emergency_contact_relationship
                            }
                        />
                        <InputError
                            message={stepErrors.emergency_contact_relationship}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="emergency_contact_number">
                            Contact number *
                        </Label>
                        <PhoneInput
                            id="emergency_contact_number"
                            placeholder="09XXXXXXXXX or +639XXXXXXXXX"
                            value={form.data.emergency_contact_number ?? ''}
                            onChange={(v) =>
                                onCommonChange(
                                    'emergency_contact_number',
                                    v,
                                )
                            }
                            aria-invalid={!!stepErrors.emergency_contact_number}
                        />
                        <p className="text-xs text-muted-foreground">
                            PH mobile only — e.g. 09171234567 or +639171234567
                        </p>
                        <InputError
                            message={stepErrors.emergency_contact_number}
                        />
                    </div>
                </div>
                {warning !== null && (
                    <p className="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-700 dark:text-amber-300">
                        {warning}
                    </p>
                )}
            </section>
        );
    };

    return (
        <>
            <Head title={`Register — ${event.event_name}`} />

            <div className="mb-8">
                <h1 className="text-2xl font-semibold text-foreground">
                    {event.event_name}
                </h1>
                <div className="mt-3 flex flex-col gap-2 text-sm text-muted-foreground">
                    {(event.event_type !== null ||
                        event.event_date !== null ||
                        event.start_time !== null ||
                        event.end_time !== null ||
                        event.distance !== null) && (
                        <div className="flex items-center gap-2">
                            <CalendarDays className="h-4 w-4" />
                            <span>
                                {[
                                    event.event_type,
                                    event.event_date,
                                    event.start_time !== null || event.end_time !== null
                                        ? [event.start_time, event.end_time]
                                              .filter(Boolean)
                                              .join('–')
                                        : null,
                                    event.distance,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </span>
                        </div>
                    )}
                    {(event.venue || event.venue_address) && (
                        <div className="flex items-center gap-2">
                            <MapPin className="h-4 w-4" />
                            <span>{[event.venue, event.venue_address].filter(Boolean).join(' · ')}</span>
                        </div>
                    )}
                    {event.venue_map_url && (
                        <a
                            href={event.venue_map_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex items-center gap-2 text-lime-brand hover:underline"
                        >
                            <ExternalLink className="h-4 w-4" />
                            View on map
                        </a>
                    )}
                </div>
                {event.description !== null && event.description !== '' && (
                    <p className="mt-4 whitespace-pre-line text-sm text-foreground/80">
                        {event.description}
                    </p>
                )}
                {event.accessibility.length > 0 && (
                    <p className="mt-3 text-sm text-muted-foreground">
                        {event.accessibility.join(' · ')}
                    </p>
                )}
                {event.partners.length > 0 && (
                    <p className="mt-3 text-sm text-muted-foreground">
                        With{' '}
                        {event.partners
                            .map((partner) => `${partner.name} (${partner.type})`)
                            .join(' · ')}
                    </p>
                )}
            </div>

            <Wizard
                steps={steps}
                storageKey={`wdems-registration-${event_slug}`}
                data={form.data}
                setData={form.setData}
                errors={form.errors}
                processing={form.processing}
                onSubmit={submit}
                submitLabel="Submit Registration"
            >
                {(stepId, stepErrors) => {
                    switch (stepId) {
                        case 'disclaimer':
                            return renderDisclaimerStep(stepErrors);
                        case 'personal':
                            return renderPersonalStep(stepErrors);
                        case 'emergency':
                            return renderEmergencyStep(stepErrors);
                        default:
                            return null;
                    }
                }}
            </Wizard>

            <Dialog open={privacyOpen} onOpenChange={setPrivacyOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Privacy Notice</DialogTitle>
                        <DialogDescription>
                            How we handle the personal information you provide.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="max-h-[60vh] overflow-y-auto pr-2">
                        <pre className="whitespace-pre-wrap font-sans text-sm leading-relaxed text-muted-foreground">
                            {privacy_consent_text}
                        </pre>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            onClick={() => setPrivacyOpen(false)}
                        >
                            Close
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={termsOpen} onOpenChange={setTermsOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Terms &amp; Conditions</DialogTitle>
                        <DialogDescription>
                            The rules and agreements for participating in this event.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="max-h-[60vh] overflow-y-auto pr-2">
                        <pre className="whitespace-pre-wrap font-sans text-sm leading-relaxed text-muted-foreground">
                            {disclaimer_paragraphs.join('\n\n')}
                        </pre>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            onClick={() => setTermsOpen(false)}
                        >
                            Close
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
