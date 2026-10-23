import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
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
import PhoneInput from '@/components/phone-input';
import WizardProgress, { type WizardStep } from '@/components/wizard-progress';
import { PUBLIC_CHURCH_PARTICIPANT_TYPES } from '@/lib/church-participant-types';
import { humanNameQualityError } from '@/lib/event-validation';

type ChurchEvent = {
    event_name: string;
    church_category: string;
    registration_slug: string;
};

type Props = {
    event: ChurchEvent;
    common_field_requirements: Record<string, boolean>;
    submit_url: string;
    disclaimer_paragraphs: string[];
    privacy_consent_text: string;
};

type FieldKey =
    | 'first_name'
    | 'middle_name'
    | 'last_name'
    | 'suffix'
    | 'email'
    | 'contact_number'
    | 'birthday'
    | 'address'
    | 'guardian_name'
    | 'guardian_contact'
    | 'emergency_contact_first_name'
    | 'emergency_contact_last_name'
    | 'emergency_contact_relationship_other'
    | 'emergency_contact_number';

const STEPS: WizardStep[] = [
    { id: 'disclaimer', label: 'Disclaimer' },
    { id: 'personal', label: 'Personal Info' },
    { id: 'emergency', label: 'Emergency Contact' },
];

const CONSENT_ERROR =
    'You must agree to the Privacy Notice and Terms & Conditions.';

function dateYearsAgo(years: number): string {
    const date = new Date();
    date.setFullYear(date.getFullYear() - years);
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

export default function ChurchPublic({
    event,
    common_field_requirements,
    submit_url,
    disclaimer_paragraphs,
    privacy_consent_text,
}: Props) {
    const [clientErrors, setClientErrors] = useState<Record<string, string>>(
        {},
    );
    const [currentStep, setCurrentStep] = useState(0);
    const [maxReachedStep, setMaxReachedStep] = useState(0);
    const [privacyOpen, setPrivacyOpen] = useState(false);
    const [termsOpen, setTermsOpen] = useState(false);
    const form = useForm({
        first_name: '',
        middle_name: '',
        last_name: '',
        suffix: '',
        email: '',
        contact_number: '',
        birthday: '',
        address: '',
        participant_type: 'Guest',
        guardian_name: '',
        guardian_contact: '',
        emergency_contact_first_name: '',
        emergency_contact_last_name: '',
        emergency_contact_relationship: 'Parent',
        emergency_contact_relationship_other: '',
        emergency_contact_number: '',
        consent: false,
    });
    const draftKey = `church-registration:${event.registration_slug}`;
    const [draftLoaded, setDraftLoaded] = useState(false);
    const guardian = ['Kids Ministry', 'Youth'].includes(event.church_category);

    useEffect(() => {
        try {
            const saved = localStorage.getItem(draftKey);
            if (saved) {
                const parsed: unknown = JSON.parse(saved);
                if (
                    typeof parsed === 'object' &&
                    parsed !== null &&
                    !Array.isArray(parsed)
                ) {
                    const restored = parsed as Record<string, unknown>;
                    const participantType =
                        PUBLIC_CHURCH_PARTICIPANT_TYPES.find(
                            (option) =>
                                option.value === restored.participant_type,
                        )?.value ?? 'Guest';

                    form.setData({
                        ...form.data,
                        ...restored,
                        participant_type: participantType,
                        consent: false,
                    });
                }
            }
        } catch {
            // Browser storage is optional.
        } finally {
            setDraftLoaded(true);
        }
        // Restore once for this event; subsequent changes are saved below.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [draftKey]);

    useEffect(() => {
        if (!draftLoaded) return;
        try {
            localStorage.setItem(draftKey, JSON.stringify(form.data));
        } catch {
            // Browser storage is optional.
        }
    }, [draftKey, draftLoaded, form.data]);

    useEffect(() => {
        const errorKeys = Object.keys(form.errors);
        if (errorKeys.some((key) => ['consent'].includes(key))) {
            setCurrentStep(0);
        } else if (
            errorKeys.some((key) =>
                [
                    'first_name',
                    'middle_name',
                    'last_name',
                    'suffix',
                    'email',
                    'contact_number',
                    'birthday',
                    'address',
                    'participant_type',
                    'guardian_name',
                    'guardian_contact',
                ].includes(key),
            )
        ) {
            setCurrentStep(1);
        } else if (
            errorKeys.some((key) => key.startsWith('emergency_contact'))
        ) {
            setCurrentStep(2);
        }
    }, [form.errors]);

    const isRequired = (key: string) =>
        ['first_name', 'last_name', 'birthday'].includes(key) ||
        (common_field_requirements[key] ?? true);

    const fields: Array<{
        key: Extract<
            FieldKey,
            | 'first_name'
            | 'middle_name'
            | 'last_name'
            | 'suffix'
            | 'email'
            | 'contact_number'
            | 'birthday'
            | 'address'
        >;
        label: string;
        required?: boolean;
        type?: string;
    }> = [
        {
            key: 'first_name',
            label: 'First name',
            required: isRequired('first_name'),
        },
        { key: 'middle_name', label: 'Middle name' },
        {
            key: 'last_name',
            label: 'Last name',
            required: isRequired('last_name'),
        },
        { key: 'suffix', label: 'Suffix' },
        {
            key: 'email',
            label: 'Email',
            type: 'email',
            required: isRequired('email'),
        },
        {
            key: 'contact_number',
            label: 'Contact number',
            type: 'tel',
            required: isRequired('contact_number'),
        },
        {
            key: 'birthday',
            label: 'Birthdate',
            type: 'date',
            required: isRequired('birthday'),
        },
        {
            key: 'address',
            label: 'Barangay & City',
            required: isRequired('address'),
        },
    ];

    const errorFor = (key: string) =>
        clientErrors[key] ??
        (form.errors as Record<string, string>)[key] ??
        undefined;

    const setField = (key: keyof typeof form.data, value: string | boolean) => {
        form.setData(key, value as never);
        setClientErrors((current) => {
            const next = { ...current };
            delete next[key];
            return next;
        });
        form.clearErrors(key);
    };

    const validateStep = (): boolean => {
        const errors: Record<string, string> = {};
        if (currentStep === 0) {
            if (!form.data.consent) errors.consent = CONSENT_ERROR;
        } else if (currentStep === 1) {
            for (const [key, label] of [
                ['first_name', 'First name'],
                ['last_name', 'Last name'],
                ['email', 'Email'],
                ['birthday', 'Birthdate'],
                ['address', 'Barangay & City'],
            ] as const) {
                if (isRequired(key) && !form.data[key].trim()) {
                    errors[key] = `${label} is required.`;
                }
            }
            for (const [key, label] of [
                ['first_name', 'First name'],
                ['middle_name', 'Middle name'],
                ['last_name', 'Last name'],
            ] as const) {
                const value = form.data[key].trim();
                if (!value) continue;
                const qualityError = humanNameQualityError(value, label);
                if (qualityError) errors[key] = qualityError;
            }
            if (
                form.data.email &&
                !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.data.email)
            ) {
                errors.email = 'Enter a valid email address.';
            }
            if (
                form.data.contact_number &&
                !/^(?:09|\+639)[0-9]{9}$/.test(form.data.contact_number)
            ) {
                errors.contact_number =
                    'Enter a valid Philippine mobile number: 09xxxxxxxxx or +639xxxxxxxxx.';
            }
        } else {
            for (const [key, label] of [
                [
                    'emergency_contact_first_name',
                    'Emergency contact first name',
                ],
                ['emergency_contact_last_name', 'Emergency contact last name'],
            ] as const) {
                const value = form.data[key].trim();
                if (!value) {
                    errors[key] = `${label} is required.`;
                } else if (
                    value.length < 2 ||
                    value.length > 50 ||
                    !/^[\p{L}\p{M} '-]+$/u.test(value)
                ) {
                    errors[key] =
                        'Use 2–50 characters: letters, spaces, hyphens, or apostrophes.';
                } else {
                    const qualityError = humanNameQualityError(value, label);
                    if (qualityError) errors[key] = qualityError;
                }
            }
            if (
                !/^(?:09|\+639)[0-9]{9}$/.test(
                    form.data.emergency_contact_number,
                )
            ) {
                errors.emergency_contact_number =
                    'Enter a valid Philippine mobile number: 09xxxxxxxxx or +639xxxxxxxxx.';
            }
            if (
                form.data.emergency_contact_relationship === 'Other' &&
                !form.data.emergency_contact_relationship_other.trim()
            ) {
                errors.emergency_contact_relationship_other =
                    'Specify the relationship.';
            }
        }

        setClientErrors(errors);

        return Object.keys(errors).length === 0;
    };

    const handleNext = () => {
        if (!validateStep()) return;
        const next = Math.min(currentStep + 1, STEPS.length - 1);
        setCurrentStep(next);
        setMaxReachedStep((max) => Math.max(max, next));
    };

    const handleSubmit = () => {
        if (!validateStep()) return;
        form.post(submit_url);
    };

    return (
        <>
            <Head title={`Register — ${event.event_name}`} />
            <main className="mx-auto max-w-3xl space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        {event.event_name}
                    </h1>
                    <p className="text-muted-foreground mt-2 text-sm">
                        Complete your details, then verify your email. Your
                        registration is for this event only.
                    </p>
                </div>

                <WizardProgress
                    steps={STEPS}
                    currentStep={currentStep}
                    maxReachedStep={maxReachedStep}
                    onStepClick={setCurrentStep}
                />

                <form
                    className="glass-panel rounded-xl p-6 sm:p-8"
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (currentStep === STEPS.length - 1) {
                            handleSubmit();
                        } else {
                            handleNext();
                        }
                    }}
                >
                    {(form.errors as Record<string, string>).identity && (
                        <p
                            role="alert"
                            className="text-destructive mb-5 text-sm"
                        >
                            {(form.errors as Record<string, string>).identity}
                        </p>
                    )}

                    {currentStep === 0 && (
                        <section className="flex flex-col gap-6">
                            <div className="flex flex-col gap-4">
                                <h2 className="text-muted-foreground text-sm font-semibold tracking-wider uppercase">
                                    Participant Disclaimer &amp; Agreement
                                </h2>
                                <div className="border-border bg-card/40 flex flex-col gap-3 rounded-xl border p-6">
                                    {disclaimer_paragraphs.map(
                                        (paragraph, index) => (
                                            <p
                                                key={index}
                                                className="text-foreground/80 text-sm leading-relaxed"
                                            >
                                                {paragraph}
                                            </p>
                                        ),
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
                                <label className="border-border bg-card/40 flex cursor-pointer items-start gap-3 rounded-lg border p-4 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={form.data.consent}
                                        onChange={(e) =>
                                            setField(
                                                'consent',
                                                e.target.checked,
                                            )
                                        }
                                        aria-invalid={!!errorFor('consent')}
                                        className="mt-0.5 h-4 w-4 shrink-0"
                                    />
                                    <span className="text-muted-foreground">
                                        I agree to the Participant Disclaimer
                                        &amp; Agreement and the Data Privacy
                                        Consent stated above.{' '}
                                        <button
                                            type="button"
                                            onClick={() => setPrivacyOpen(true)}
                                            className="text-lime-brand font-medium underline-offset-2 hover:underline"
                                        >
                                            Privacy Notice
                                        </button>{' '}
                                        and{' '}
                                        <button
                                            type="button"
                                            onClick={() => setTermsOpen(true)}
                                            className="text-lime-brand font-medium underline-offset-2 hover:underline"
                                        >
                                            Terms &amp; Conditions
                                        </button>
                                        .
                                    </span>
                                </label>
                                {errorFor('consent') && (
                                    <p
                                        role="alert"
                                        className="text-destructive text-sm"
                                    >
                                        {errorFor('consent')}
                                    </p>
                                )}
                            </div>
                        </section>
                    )}

                    {currentStep === 1 && (
                        <section className="space-y-5">
                            <div>
                                <h2 className="text-foreground text-lg font-semibold">
                                    Personal Information
                                </h2>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    <span className="text-destructive">*</span>{' '}
                                    indicates required question
                                </p>
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {fields.map(
                                    ({ key, label, required, type }) => (
                                        <div key={key} className="space-y-1">
                                            <Label htmlFor={key}>
                                                {label}
                                                {required && (
                                                    <span className="text-destructive">
                                                        {' '}
                                                        *
                                                    </span>
                                                )}
                                            </Label>
                                            {key === 'contact_number' ? (
                                                <PhoneInput
                                                    international
                                                    id={key}
                                                    value={form.data[key]}
                                                    onChange={(value) =>
                                                        setField(key, value)
                                                    }
                                                    aria-invalid={
                                                        !!errorFor(key)
                                                    }
                                                />
                                            ) : (
                                                <Input
                                                    id={key}
                                                    type={type ?? 'text'}
                                                    placeholder={
                                                        key === 'first_name'
                                                            ? 'Juan'
                                                            : key ===
                                                                'last_name'
                                                              ? 'Dela Cruz'
                                                              : key ===
                                                                  'middle_name'
                                                                ? 'Santos (optional)'
                                                                : key ===
                                                                    'suffix'
                                                                  ? 'Jr. (optional)'
                                                                  : key ===
                                                                      'email'
                                                                    ? 'you@example.com'
                                                                    : key ===
                                                                        'birthday'
                                                                      ? 'MM/DD/YYYY'
                                                                      : key ===
                                                                          'address'
                                                                        ? 'ex. Gen. T. De Leon, Valenzuela City'
                                                                        : undefined
                                                    }
                                                    value={form.data[key]}
                                                    required={required}
                                                    min={
                                                        key === 'birthday'
                                                            ? dateYearsAgo(120)
                                                            : undefined
                                                    }
                                                    max={
                                                        key === 'birthday'
                                                            ? dateYearsAgo(3)
                                                            : undefined
                                                    }
                                                    onChange={(e) =>
                                                        setField(
                                                            key,
                                                            e.target.value,
                                                        )
                                                    }
                                                    aria-invalid={
                                                        !!errorFor(key)
                                                    }
                                                />
                                            )}
                                            {key === 'contact_number' && (
                                                <p className="text-muted-foreground text-xs">
                                                    PH mobile only — +63
                                                    9xxxxxxxxx
                                                </p>
                                            )}
                                            {errorFor(key) && (
                                                <p
                                                    role="alert"
                                                    className="text-destructive text-xs"
                                                >
                                                    {errorFor(key)}
                                                </p>
                                            )}
                                        </div>
                                    ),
                                )}
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="participant_type">
                                    Participant type
                                </Label>
                                <select
                                    id="participant_type"
                                    className="bg-background w-full rounded-md border p-2"
                                    value={form.data.participant_type}
                                    onChange={(e) =>
                                        setField(
                                            'participant_type',
                                            e.target.value,
                                        )
                                    }
                                >
                                    {PUBLIC_CHURCH_PARTICIPANT_TYPES.map(
                                        (type) => (
                                            <option
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </div>
                            {guardian && (
                                <fieldset className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <legend className="col-span-full text-sm font-medium">
                                        Parent / guardian (optional)
                                    </legend>
                                    {(
                                        [
                                            'guardian_name',
                                            'guardian_contact',
                                        ] as const
                                    ).map((key) => (
                                        <div key={key} className="space-y-1">
                                            <Label htmlFor={key}>
                                                {key === 'guardian_name'
                                                    ? 'Guardian name'
                                                    : 'Guardian contact'}
                                            </Label>
                                            <Input
                                                id={key}
                                                type={
                                                    key === 'guardian_contact'
                                                        ? 'tel'
                                                        : 'text'
                                                }
                                                value={form.data[key]}
                                                onChange={(e) =>
                                                    setField(
                                                        key,
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            {errorFor(key) && (
                                                <p
                                                    role="alert"
                                                    className="text-destructive text-xs"
                                                >
                                                    {errorFor(key)}
                                                </p>
                                            )}
                                        </div>
                                    ))}
                                </fieldset>
                            )}
                        </section>
                    )}

                    {currentStep === 2 && (
                        <section className="space-y-5">
                            <div>
                                <h2 className="text-foreground text-lg font-semibold">
                                    Emergency Contact
                                </h2>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    <span className="text-destructive">*</span>{' '}
                                    indicates required question
                                </p>
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {(
                                    [
                                        [
                                            'emergency_contact_first_name',
                                            'First name',
                                        ],
                                        [
                                            'emergency_contact_last_name',
                                            'Last name',
                                        ],
                                    ] as const
                                ).map(([key, label]) => (
                                    <div key={key} className="space-y-1">
                                        <Label htmlFor={key}>
                                            {label}
                                            <span className="text-destructive">
                                                {' '}
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id={key}
                                            value={form.data[key]}
                                            required
                                            aria-invalid={!!errorFor(key)}
                                            onChange={(e) =>
                                                setField(key, e.target.value)
                                            }
                                        />
                                        {errorFor(key) && (
                                            <p
                                                role="alert"
                                                className="text-destructive text-xs"
                                            >
                                                {errorFor(key)}
                                            </p>
                                        )}
                                    </div>
                                ))}
                                <div className="space-y-1">
                                    <Label htmlFor="emergency_contact_relationship">
                                        Relationship
                                        <span className="text-destructive">
                                            {' '}
                                            *
                                        </span>
                                    </Label>
                                    <select
                                        id="emergency_contact_relationship"
                                        className="bg-background w-full rounded-md border p-2"
                                        value={
                                            form.data
                                                .emergency_contact_relationship
                                        }
                                        onChange={(e) =>
                                            setField(
                                                'emergency_contact_relationship',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        {[
                                            'Parent',
                                            'Sibling',
                                            'Spouse',
                                            'Friend',
                                            'Guardian',
                                            'Other',
                                        ].map((relationship) => (
                                            <option
                                                key={relationship}
                                                value={relationship}
                                            >
                                                {relationship}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                {form.data.emergency_contact_relationship ===
                                    'Other' && (
                                    <div className="space-y-1">
                                        <Label htmlFor="emergency_contact_relationship_other">
                                            Specify relationship
                                        </Label>
                                        <Input
                                            id="emergency_contact_relationship_other"
                                            value={
                                                form.data
                                                    .emergency_contact_relationship_other
                                            }
                                            required
                                            onChange={(e) =>
                                                setField(
                                                    'emergency_contact_relationship_other',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {errorFor(
                                            'emergency_contact_relationship_other',
                                        ) && (
                                            <p
                                                role="alert"
                                                className="text-destructive text-xs"
                                            >
                                                {errorFor(
                                                    'emergency_contact_relationship_other',
                                                )}
                                            </p>
                                        )}
                                    </div>
                                )}
                                <div className="space-y-1 sm:col-span-2">
                                    <Label htmlFor="emergency_contact_number">
                                        Emergency contact number
                                        <span className="text-destructive">
                                            {' '}
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        id="emergency_contact_number"
                                        type="tel"
                                        placeholder="09XXXXXXXXX or +639XXXXXXXXX"
                                        value={
                                            form.data.emergency_contact_number
                                        }
                                        required
                                        aria-invalid={
                                            !!errorFor(
                                                'emergency_contact_number',
                                            )
                                        }
                                        onChange={(e) =>
                                            setField(
                                                'emergency_contact_number',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <p className="text-muted-foreground text-xs">
                                        PH mobile only — +63 9xxxxxxxxx
                                    </p>
                                    {errorFor('emergency_contact_number') && (
                                        <p
                                            role="alert"
                                            className="text-destructive text-xs"
                                        >
                                            {errorFor(
                                                'emergency_contact_number',
                                            )}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </section>
                    )}

                    <div className="mt-8 flex items-center justify-between">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={currentStep === 0 || form.processing}
                            onClick={() => {
                                setCurrentStep((step) => Math.max(step - 1, 0));
                                setClientErrors({});
                            }}
                        >
                            Back
                        </Button>
                        {currentStep === STEPS.length - 1 ? (
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                            >
                                {form.processing
                                    ? 'Saving...'
                                    : 'Continue to email verification'}
                            </Button>
                        ) : (
                            <Button type="submit">Next</Button>
                        )}
                    </div>
                </form>
            </main>

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
                            The guidelines for participating in this Church
                            Event.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="max-h-[60vh] overflow-y-auto pr-2">
                        <pre className="text-muted-foreground font-sans text-sm leading-relaxed whitespace-pre-wrap">
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
