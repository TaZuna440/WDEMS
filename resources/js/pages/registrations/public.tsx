import { Head, Link, useForm } from '@inertiajs/react';
import { CalendarDays, CheckCircle2, ExternalLink, MapPin } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    formatBirthdateInput,
    birthdateInputError,
    birthdateToIso,
} from '@/lib/participant-input';
import TshirtPhotoGallery from '@/components/tshirt-photo-gallery';
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
    disclaimer_html?: string | null;
    form_version?: number;
    tshirt?: {
        label: string;
        required: boolean;
        choices: string[];
        photos: string[];
    } | null;
    privacy_consent_text: string;
    event_slug: string;
    preview?: boolean;
    preview_return_url?: string | null;
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
    | 'emergency_contact_first_name'
    | 'emergency_contact_last_name'
    | 'emergency_contact_relationship_other'
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
    disclaimer_html = null,
    form_version = 1,
    tshirt = null,
    privacy_consent_text,
    event_slug,
    preview = false,
    preview_return_url = null,
}: Props) {
    const [previewStep, setPreviewStep] = useState(0);
    const form = useForm<RegistrationFormData>({
        first_name: preview ? 'Maria' : '',
        tshirt_choice: '',
        similarity_acknowledged: false,
        last_name: preview ? 'Santos' : '',
        middle_name: '',
        suffix: '',
        email: preview ? 'participant@example.com' : '',
        contact_number: preview ? '09171234567' : '',
        age: '',
        birthday: preview ? '2000-01-15' : '',
        birthday_input: preview ? '01/15/2000' : '',
        address: preview ? 'Sample Barangay, Sample City' : '',
        emergency_contact_name: preview ? 'Juan Santos' : '',
        emergency_contact_first_name: preview ? 'Juan' : '',
        emergency_contact_last_name: preview ? 'Santos' : '',
        emergency_contact_relationship_other: '',
        emergency_contact_relationship: preview ? 'Parent' : '',
        emergency_contact_number: preview ? '09179876543' : '',
        consent_accepted: false,
        responses: {},
    });

    const identityKey = JSON.stringify({
        first_name: form.data.first_name,
        middle_name: form.data.middle_name,
        last_name: form.data.last_name,
        suffix: form.data.suffix,
        birthday: form.data.birthday,
        email: form.data.email,
        contact_number: form.data.contact_number,
    });
    const [similarity, setSimilarity] = useState<{
        key: string;
        similar: boolean;
        failed: boolean;
    } | null>(null);
    const reviewedIdentity = form.data.similarity_reviewed_identity ?? '';
    const setReviewedIdentity = (value: string) =>
        form.setData('similarity_reviewed_identity', value);
    const [retrySimilarity, setRetrySimilarity] = useState(0);
    useEffect(() => {
        if (
            preview ||
            !form.data.consent_accepted ||
            form.data.first_name.trim().length < 2 ||
            form.data.last_name.trim().length < 2
        )
            return;
        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            try {
                const csrf = document.cookie
                    .split('; ')
                    .find((c) => c.startsWith('XSRF-TOKEN='))
                    ?.slice('XSRF-TOKEN='.length);
                const response = await fetch(`${submit_url}/similarity`, {
                    method: 'POST',
                    signal: controller.signal,
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': csrf ? decodeURIComponent(csrf) : '',
                    },
                    body: identityKey,
                });
                if (
                    !response.ok ||
                    !response.headers
                        .get('content-type')
                        ?.includes('application/json')
                )
                    throw new Error('Check failed');
                const result = (await response.json()) as { similar: boolean };
                setSimilarity({
                    key: identityKey,
                    similar: result.similar,
                    failed: false,
                });
            } catch {
                if (!controller.signal.aborted)
                    setSimilarity({
                        key: identityKey,
                        similar: false,
                        failed: true,
                    });
            }
        }, 700);
        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [
        identityKey,
        preview,
        submit_url,
        form.data.consent_accepted,
        retrySimilarity,
    ]);

    const [privacyOpen, setPrivacyOpen] = useState(false);
    const [termsOpen, setTermsOpen] = useState(false);

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
                    'tshirt_choice',
                    'similarity_acknowledged',
                ],
                validate: (data) => {
                    const all = validateRegistrationForm(
                        data as RegistrationFormData,
                        fields,
                        common_field_requirements,
                        form_version,
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
                    if (
                        !preview &&
                        (similarity?.key !== identityKey || similarity.failed)
                    )
                        out.similarity_acknowledged =
                            'Wait for the registration check, or retry it below.';
                    if (
                        !preview &&
                        similarity?.key === identityKey &&
                        similarity.similar &&
                        reviewedIdentity !== identityKey
                    )
                        out.similarity_acknowledged =
                            'Review the warning and confirm you want to continue.';
                    if (
                        tshirt?.required &&
                        !(data as RegistrationFormData).tshirt_choice
                    )
                        out.tshirt_choice = 'Choose a T-shirt option.';
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
                    'emergency_contact_first_name',
                    'emergency_contact_last_name',
                    'emergency_contact_relationship_other',
                    'emergency_contact_relationship',
                    'emergency_contact_number',
                ],
                validate: (data) => {
                    const all = validateRegistrationForm(
                        data as RegistrationFormData,
                        fields,
                        common_field_requirements,
                        form_version,
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
        [
            fields,
            common_field_requirements,
            form_version,
            tshirt,
            preview,
            similarity,
            identityKey,
            reviewedIdentity,
        ],
    );

    const submit = () => {
        if (preview) return;
        form.transform((data) => ({
            ...data,
            similarity_acknowledged: reviewedIdentity === identityKey,
        }));
        form.post(submit_url);
    };

    if (success !== null) {
        return (
            <>
                <Head title={`Registered — ${event.event_name}`} />
                <div className="border-lime-brand/30 bg-lime-brand/5 rounded-xl border p-10 text-center">
                    <div className="bg-lime-brand/15 mx-auto mb-6 flex h-14 w-14 items-center justify-center rounded-full">
                        <CheckCircle2 className="text-lime-brand h-7 w-7" />
                    </div>
                    <h1 className="text-foreground text-xl font-semibold">
                        You are registered.
                    </h1>
                    <p className="text-muted-foreground mt-2 text-sm">
                        {success}
                    </p>
                    <p className="text-foreground mt-6 text-sm">
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
                <h2 className="text-muted-foreground text-sm font-semibold tracking-wider uppercase">
                    Participant Disclaimer & Agreement
                </h2>
                <div className="border-border bg-card/40 flex flex-col gap-3 rounded-xl border p-6">
                    {disclaimer_html ? (
                        <div
                            className="text-sm leading-relaxed [&_h1]:text-2xl [&_h2]:text-xl [&_p]:my-3"
                            dangerouslySetInnerHTML={{
                                __html: disclaimer_html,
                            }}
                        />
                    ) : (
                        disclaimer_paragraphs.map((paragraph, i) => (
                            <p
                                key={i}
                                className="text-foreground/80 text-sm leading-relaxed"
                            >
                                {paragraph}
                            </p>
                        ))
                    )}
                </div>
            </div>
            <div className="flex flex-col gap-4">
                <h2 className="text-muted-foreground text-sm font-semibold tracking-wider uppercase">
                    Data Privacy Consent
                </h2>
                <div className="border-border bg-card/40 rounded-xl border p-6">
                    <p className="text-foreground/80 text-sm leading-relaxed">
                        {privacy_consent_text}
                    </p>
                </div>
                <div className="grid gap-1.5">
                    <label className="border-border bg-card/40 flex cursor-pointer items-start gap-3 rounded-lg border p-4 text-sm">
                        <input
                            type="checkbox"
                            checked={!!form.data.consent_accepted}
                            onChange={(e) =>
                                onCommonChange(
                                    'consent_accepted' as CommonFieldKey,
                                    e.target.checked,
                                )
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
                                className="text-lime-brand font-medium underline-offset-2 hover:underline"
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
                                className="text-lime-brand font-medium underline-offset-2 hover:underline"
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
            {!preview && (
                <aside
                    className="space-y-2 rounded-xl border p-4 text-sm"
                    aria-live="polite"
                >
                    {similarity?.key === identityKey && similarity.similar ? (
                        <>
                            <p className="font-medium text-amber-700 dark:text-amber-300">
                                A similar registration may already exist. Check
                                your name, birthdate, and contact details. If
                                this is a different person, you can continue.
                            </p>
                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={reviewedIdentity === identityKey}
                                    onChange={(e) => {
                                        setReviewedIdentity(
                                            e.target.checked ? identityKey : '',
                                        );
                                        clearServerErrors(
                                            'similarity_acknowledged',
                                        );
                                        form.setData(
                                            'similarity_acknowledged',
                                            e.target.checked,
                                        );
                                    }}
                                />
                                I reviewed my details and want to continue.
                            </label>
                        </>
                    ) : (
                        <p className="text-muted-foreground">
                            {similarity?.key === identityKey &&
                            !similarity.failed
                                ? 'Registration check complete.'
                                : 'Enter valid personal details to check for a similar registration.'}
                        </p>
                    )}
                    {similarity?.key === identityKey && similarity.failed && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                setRetrySimilarity((value) => value + 1)
                            }
                        >
                            Retry registration check
                        </Button>
                    )}
                    <InputError message={stepErrors.similarity_acknowledged} />
                </aside>
            )}
            <section className="flex flex-col gap-4">
                <h2 className="text-muted-foreground text-sm font-semibold tracking-wider uppercase">
                    Your Details
                </h2>
                <p className="text-muted-foreground text-xs">
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
                            international
                            id="contact_number"
                            placeholder="09XXXXXXXXX or +639XXXXXXXXX"
                            value={form.data.contact_number}
                            onChange={(v) =>
                                onCommonChange('contact_number', v)
                            }
                            aria-invalid={!!stepErrors.contact_number}
                        />
                        <p className="text-muted-foreground text-xs">
                            PH mobile only — +63 9xxxxxxxxx
                        </p>
                        <InputError message={stepErrors.contact_number} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="birthday">Date of birth *</Label>
                        <Input
                            id="birthday"
                            inputMode="numeric"
                            autoComplete="bday"
                            placeholder="MM/DD/YYYY"
                            maxLength={10}
                            value={
                                form.data.birthday_input ||
                                (form.data.birthday
                                    ? `${form.data.birthday.slice(5, 7)}/${form.data.birthday.slice(8, 10)}/${form.data.birthday.slice(0, 4)}`
                                    : '')
                            }
                            onChange={(e) => {
                                const value = formatBirthdateInput(
                                    e.target.value,
                                );
                                form.setData('birthday_input', value);
                                onCommonChange(
                                    'birthday',
                                    birthdateToIso(value),
                                );
                            }}
                            aria-invalid={
                                !!stepErrors.birthday ||
                                !!birthdateInputError(
                                    form.data.birthday_input ?? '',
                                )
                            }
                        />
                        <InputError
                            message={
                                birthdateInputError(
                                    form.data.birthday_input ?? '',
                                ) ?? undefined
                            }
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

            {tshirt && (
                <section className="grid gap-3">
                    {!preview && <TshirtPhotoGallery photos={tshirt.photos} />}
                    <Label htmlFor="tshirt-choice">
                        {tshirt.label}
                        {tshirt.required && ' *'}
                    </Label>
                    <Select
                        value={form.data.tshirt_choice ?? ''}
                        onValueChange={(value) => {
                            clearServerErrors('tshirt_choice');
                            form.setData('tshirt_choice', value);
                        }}
                    >
                        <SelectTrigger id="tshirt-choice">
                            <SelectValue placeholder="Choose an option" />
                        </SelectTrigger>
                        <SelectContent>
                            {tshirt.choices.map((choice) => (
                                <SelectItem key={choice} value={choice}>
                                    {choice}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={stepErrors.tshirt_choice} />
                </section>
            )}

            {fields.length > 0 && (
                <section className="flex flex-col gap-4">
                    <h2 className="text-muted-foreground text-sm font-semibold tracking-wider uppercase">
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
                                        value={
                                            typeof value === 'string'
                                                ? value
                                                : ''
                                        }
                                        onChange={(e) =>
                                            setResponse(
                                                field.id,
                                                e.target.value,
                                            )
                                        }
                                        rows={4}
                                        aria-invalid={!!error}
                                        className="border-input text-foreground placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 flex min-h-[80px] w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px]"
                                    />
                                )}
                                {field.field_type === 'select' && (
                                    <Select
                                        value={
                                            typeof value === 'string'
                                                ? value
                                                : ''
                                        }
                                        onValueChange={(v) =>
                                            setResponse(field.id, v)
                                        }
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
                                                <SelectItem
                                                    key={option}
                                                    value={option}
                                                >
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
                                                className="text-foreground flex cursor-pointer items-center gap-2 text-sm"
                                            >
                                                <input
                                                    type="radio"
                                                    name={`field-${field.id}`}
                                                    value={option}
                                                    checked={value === option}
                                                    onChange={() =>
                                                        setResponse(
                                                            field.id,
                                                            option,
                                                        )
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
                                            const list = Array.isArray(value)
                                                ? value
                                                : [];
                                            return (
                                                <label
                                                    key={option}
                                                    className="text-foreground flex cursor-pointer items-center gap-2 text-sm"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        value={option}
                                                        checked={list.includes(
                                                            option,
                                                        )}
                                                        onChange={() =>
                                                            toggleCheckboxOption(
                                                                field.id,
                                                                option,
                                                            )
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
                                                  : field.field_type ===
                                                      'number'
                                                    ? 'number'
                                                    : 'text'
                                        }
                                        value={
                                            typeof value === 'string'
                                                ? value
                                                : ''
                                        }
                                        onChange={(e) =>
                                            setResponse(
                                                field.id,
                                                e.target.value,
                                            )
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
                <h2 className="text-muted-foreground text-sm font-semibold tracking-wider uppercase">
                    Emergency Contact
                </h2>
                <p className="text-muted-foreground text-xs">
                    * indicates required question
                </p>
                <div className="grid gap-4 sm:grid-cols-2">
                    {form_version >= 2 ? (
                        (
                            [
                                'emergency_contact_first_name',
                                'emergency_contact_last_name',
                            ] as const
                        ).map((key) => (
                            <div key={key} className="grid gap-1.5">
                                <Label htmlFor={key}>
                                    {key.endsWith('first_name')
                                        ? 'First name'
                                        : 'Last name'}{' '}
                                    *
                                </Label>
                                <Input
                                    id={key}
                                    maxLength={50}
                                    value={form.data[key] ?? ''}
                                    onChange={(e) =>
                                        onCommonChange(key, e.target.value)
                                    }
                                />
                                <InputError message={stepErrors[key]} />
                            </div>
                        ))
                    ) : (
                        <div className="grid gap-1.5 sm:col-span-2">
                            <Label htmlFor="emergency_contact_name">
                                Contact name *
                            </Label>
                            <Input
                                id="emergency_contact_name"
                                placeholder="Jose Santos"
                                value={form.data.emergency_contact_name ?? ''}
                                onChange={(e) =>
                                    onCommonChange(
                                        'emergency_contact_name',
                                        e.target.value,
                                    )
                                }
                                aria-invalid={
                                    !!stepErrors.emergency_contact_name
                                }
                            />
                            <InputError
                                message={stepErrors.emergency_contact_name}
                            />
                        </div>
                    )}
                    <div className="grid gap-1.5">
                        <Label htmlFor="emergency_contact_relationship">
                            Relationship *
                        </Label>
                        {form_version >= 2 ? (
                            <div
                                role="radiogroup"
                                aria-label="Relationship"
                                className="grid gap-2"
                            >
                                {['Parent', 'Guardian', 'Other'].map(
                                    (value) => (
                                        <label
                                            key={value}
                                            className="flex items-center gap-2"
                                        >
                                            <input
                                                type="radio"
                                                name="emergency_contact_relationship"
                                                value={value}
                                                checked={
                                                    form.data
                                                        .emergency_contact_relationship ===
                                                    value
                                                }
                                                onChange={() =>
                                                    onCommonChange(
                                                        'emergency_contact_relationship',
                                                        value,
                                                    )
                                                }
                                            />
                                            {value}
                                        </label>
                                    ),
                                )}
                            </div>
                        ) : (
                            <Input
                                id="emergency_contact_relationship"
                                placeholder="Father"
                                value={
                                    form.data.emergency_contact_relationship ??
                                    ''
                                }
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
                        )}
                        <InputError
                            message={stepErrors.emergency_contact_relationship}
                        />
                    </div>
                    {form_version >= 2 &&
                        form.data.emergency_contact_relationship ===
                            'Other' && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="emergency_contact_relationship_other">
                                    Specify relationship *
                                </Label>
                                <Input
                                    id="emergency_contact_relationship_other"
                                    maxLength={15}
                                    value={
                                        form.data
                                            .emergency_contact_relationship_other ??
                                        ''
                                    }
                                    onChange={(e) =>
                                        onCommonChange(
                                            'emergency_contact_relationship_other',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        stepErrors.emergency_contact_relationship_other
                                    }
                                />
                            </div>
                        )}
                    <div className="grid gap-1.5">
                        <Label htmlFor="emergency_contact_number">
                            Contact number *
                        </Label>
                        <PhoneInput
                            international
                            id="emergency_contact_number"
                            placeholder="09XXXXXXXXX or +639XXXXXXXXX"
                            value={form.data.emergency_contact_number ?? ''}
                            onChange={(v) =>
                                onCommonChange('emergency_contact_number', v)
                            }
                            aria-invalid={!!stepErrors.emergency_contact_number}
                        />
                        <p className="text-muted-foreground text-xs">
                            PH mobile only — +63 9xxxxxxxxx
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
                <h1 className="text-foreground text-2xl font-semibold">
                    {event.event_name}
                </h1>
                <div className="text-muted-foreground mt-3 flex flex-col gap-2 text-sm">
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
                                    event.start_time !== null ||
                                    event.end_time !== null
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
                            <span>
                                {[event.venue, event.venue_address]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </span>
                        </div>
                    )}
                    {event.venue_map_url && (
                        <a
                            href={event.venue_map_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-lime-brand flex items-center gap-2 hover:underline"
                        >
                            <ExternalLink className="h-4 w-4" />
                            View on map
                        </a>
                    )}
                </div>
                {event.description !== null && event.description !== '' && (
                    <p className="text-foreground/80 mt-4 text-sm whitespace-pre-line">
                        {event.description}
                    </p>
                )}
                {event.accessibility.length > 0 && (
                    <p className="text-muted-foreground mt-3 text-sm">
                        {event.accessibility.join(' · ')}
                    </p>
                )}
                {event.partners.length > 0 && (
                    <p className="text-muted-foreground mt-3 text-sm">
                        With{' '}
                        {event.partners
                            .map(
                                (partner) =>
                                    `${partner.name} (${partner.type})`,
                            )
                            .join(' · ')}
                    </p>
                )}
            </div>

            {preview ? (
                <div className="flex flex-col gap-6">
                    <div className="border-border bg-card rounded-xl border p-4">
                        <p className="font-semibold">
                            Registration form preview
                        </p>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Explore the saved form. Inputs are disabled and no
                            registration will be submitted.
                        </p>
                    </div>
                    <nav
                        aria-label="Preview steps"
                        className="flex flex-wrap gap-2"
                    >
                        {steps.map((step, index) => (
                            <Button
                                key={step.id}
                                variant={
                                    previewStep === index
                                        ? 'default'
                                        : 'outline'
                                }
                                aria-current={
                                    previewStep === index ? 'step' : undefined
                                }
                                onClick={() => setPreviewStep(index)}
                            >
                                {index + 1}. {step.label}
                            </Button>
                        ))}
                    </nav>
                    {previewStep === 1 && tshirt && (
                        <TshirtPhotoGallery photos={tshirt.photos} />
                    )}
                    <fieldset
                        disabled
                        inert
                        className="glass-panel min-w-0 rounded-xl p-8"
                    >
                        {previewStep === 0
                            ? renderDisclaimerStep({})
                            : previewStep === 1
                              ? renderPersonalStep({})
                              : renderEmergencyStep({})}
                    </fieldset>
                    {preview_return_url && (
                        <Button variant="outline" asChild>
                            <Link href={preview_return_url}>Close Preview</Link>
                        </Button>
                    )}
                </div>
            ) : (
                <Wizard
                    steps={steps}
                    storageKey={`wdems-registration-${event_slug}`}
                    clearOnSubmit={false}
                    data={form.data}
                    setData={form.setData}
                    errors={form.errors}
                    processing={form.processing}
                    onSubmit={submit}
                    submitLabel="Continue to Email Verification"
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
            )}

            <Dialog open={privacyOpen} onOpenChange={setPrivacyOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Privacy Notice</DialogTitle>
                        <DialogDescription>
                            How we handle the personal information you provide.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="max-h-[60vh] overflow-y-auto pr-2">
                        <pre className="text-muted-foreground font-sans text-sm leading-relaxed whitespace-pre-wrap">
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
                            The rules and agreements for participating in this
                            event.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="max-h-[60vh] overflow-y-auto pr-2">
                        {disclaimer_html ? (
                            <div
                                className="text-sm [&_h1]:text-2xl [&_h2]:text-xl [&_p]:my-3"
                                dangerouslySetInnerHTML={{
                                    __html: disclaimer_html,
                                }}
                            />
                        ) : (
                            <pre className="text-muted-foreground font-sans text-sm leading-relaxed whitespace-pre-wrap">
                                {disclaimer_paragraphs.join('\n\n')}
                            </pre>
                        )}
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
