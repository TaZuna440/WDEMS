import { Head, useForm } from '@inertiajs/react';
import { CalendarDays, CheckCircle2, ExternalLink, MapPin } from 'lucide-react';
import { useMemo, useState, type FormEvent } from 'react';
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
import { DEMO_PRIVACY_NOTICE, DEMO_TERMS } from '@/lib/demo-legal-content';
import {
    validateRegistrationForm,
    type RegistrationFormData,
    type ServerField,
} from '@/lib/public-registration-validation';

type EventPayload = {
    event_name: string;
    event_date: string | null;
    start_time: string | null;
    venue: string;
    venue_address: string;
    venue_map_url: string | null;
    course_url: string | null;
    description: string | null;
};

type Props = {
    event: EventPayload;
    fields: ServerField[];
    common_field_requirements: Record<string, boolean>;
    submit_url: string;
    success: string | null;
};

type CommonFieldKey =
    | 'first_name'
    | 'last_name'
    | 'email'
    | 'contact_number'
    | 'age'
    | 'address';

const CONSENT_ERROR =
    'You must agree to the Privacy Notice and Terms & Conditions.';

export default function PublicRegistration({
    event,
    fields,
    common_field_requirements,
    submit_url,
    success,
}: Props) {
    const form = useForm<RegistrationFormData>({
        first_name: '',
        last_name: '',
        email: '',
        contact_number: '',
        age: '',
        address: '',
        responses: {},
    });

    // Errors are computed on every render from the current form data,
    // but only *revealed* once the user has interacted with the field
    // or attempted submit. This matches the blur-time validation rule
    // established for the wizard and registration form builder.
    const [touched, setTouched] = useState<Set<string>>(new Set());
    const [hasAttemptedSubmit, setHasAttemptedSubmit] = useState(false);
    // Consent is a React-controlled gate, not an HTML5 `required`
    // attribute. HTML5 `required` blocks the submit event entirely,
    // which means the submit handler, our client validators, and the
    // server never run — the user sees a native tooltip and nothing
    // else. Not persisted across reloads: consent is not yet recorded
    // server-side (see ISSUE-010), so asking again after a reload is
    // the honest behavior.
    const [consentAccepted, setConsentAccepted] = useState(false);
    const [privacyOpen, setPrivacyOpen] = useState(false);
    const [termsOpen, setTermsOpen] = useState(false);

    const clientErrors = useMemo(
        () =>
            validateRegistrationForm(
                form.data,
                fields,
                common_field_requirements,
            ),
        [form.data, fields, common_field_requirements],
    );

    const visibleClientErrors: Record<string, string> = {};
    for (const [key, message] of Object.entries(clientErrors)) {
        if (hasAttemptedSubmit || touched.has(key)) {
            visibleClientErrors[key] = message;
        }
    }

    const allErrors: Record<string, string> = {
        ...form.errors,
        ...visibleClientErrors,
    };

    if (hasAttemptedSubmit && !consentAccepted) {
        allErrors.consent = CONSENT_ERROR;
    }

    const isRequired = (field: string): boolean =>
        common_field_requirements[field] ?? true;

    const markTouched = (key: string) => {
        setTouched((prev) => {
            if (prev.has(key)) return prev;
            const next = new Set(prev);
            next.add(key);
            return next;
        });
    };

    // Mirrors FIX-022: a server error for a field clears the moment
    // the user edits that field or any of its subkeys.
    const clearServerErrors = (key: string) => {
        const matching = Object.keys(form.errors).filter(
            (k) => k === key || k.startsWith(`${key}.`),
        );
        if (matching.length > 0) {
            form.clearErrors(...(matching as (keyof typeof form.data)[]));
        }
    };

    const onCommonChange = (key: CommonFieldKey, value: string) => {
        clearServerErrors(key);
        form.setData(key, value);
    };

    const onCommonBlur = (key: CommonFieldKey) => {
        markTouched(key);
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
        markTouched(`responses.${fieldId}`);
    };

    const submit = (event_: FormEvent) => {
        event_.preventDefault();
        setHasAttemptedSubmit(true);

        const errors = validateRegistrationForm(
            form.data,
            fields,
            common_field_requirements,
        );

        if (!consentAccepted) {
            errors.consent = CONSENT_ERROR;
        }

        if (Object.keys(errors).length > 0) {
            return;
        }

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

    return (
        <>
            <Head title={`Register — ${event.event_name}`} />

            {/* Event header */}
            <div className="mb-8">
                <h1 className="text-2xl font-semibold text-foreground">
                    {event.event_name}
                </h1>

                <div className="mt-3 flex flex-col gap-2 text-sm text-muted-foreground">
                    {(event.event_date !== null || event.start_time !== null) && (
                        <div className="flex items-center gap-2">
                            <CalendarDays className="h-4 w-4" />
                            <span>
                                {event.event_date}
                                {event.start_time !== null && ` · ${event.start_time}`}
                            </span>
                        </div>
                    )}
                    <div className="flex items-center gap-2">
                        <MapPin className="h-4 w-4" />
                        <span>{event.venue} — {event.venue_address}</span>
                    </div>
                    {event.venue_map_url !== null && (
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
            </div>

            {/* Form */}
            <form onSubmit={submit} className="flex flex-col gap-8">
                {/* Common fields */}
                <section className="flex flex-col gap-4">
                    <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                        Your Details
                    </h2>

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
                                onBlur={() => onCommonBlur('first_name')}
                                autoComplete="given-name"
                                aria-invalid={!!allErrors.first_name}
                            />
                            <InputError message={allErrors.first_name} />
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
                                onBlur={() => onCommonBlur('last_name')}
                                autoComplete="family-name"
                                aria-invalid={!!allErrors.last_name}
                            />
                            <InputError message={allErrors.last_name} />
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
                                onBlur={() => onCommonBlur('email')}
                                autoComplete="email"
                                aria-invalid={!!allErrors.email}
                            />
                            <InputError message={allErrors.email} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="contact_number">
                                Contact number{isRequired('contact_number') && ' *'}
                            </Label>
                            <Input
                                id="contact_number"
                                inputMode="tel"
                                placeholder="09171234567"
                                value={form.data.contact_number}
                                onChange={(e) =>
                                    onCommonChange('contact_number', e.target.value)
                                }
                                onBlur={() => onCommonBlur('contact_number')}
                                autoComplete="tel"
                                aria-invalid={!!allErrors.contact_number}
                            />
                            <InputError message={allErrors.contact_number} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="age">Age *</Label>
                            <Input
                                id="age"
                                type="number"
                                inputMode="numeric"
                                min={1}
                                max={120}
                                placeholder="25"
                                value={form.data.age}
                                onChange={(e) =>
                                    onCommonChange('age', e.target.value)
                                }
                                onBlur={() => onCommonBlur('age')}
                                aria-invalid={!!allErrors.age}
                            />
                            <InputError message={allErrors.age} />
                        </div>

                        <div className="grid gap-1.5 sm:col-span-2">
                            <Label htmlFor="address">
                                Address{isRequired('address') && ' *'}
                            </Label>
                            <Input
                                id="address"
                                placeholder="Street, Barangay, City"
                                value={form.data.address}
                                onChange={(e) =>
                                    onCommonChange('address', e.target.value)
                                }
                                onBlur={() => onCommonBlur('address')}
                                autoComplete="street-address"
                                aria-invalid={!!allErrors.address}
                            />
                            <InputError message={allErrors.address} />
                        </div>
                    </div>
                </section>

                {/* Custom fields */}
                {fields.length > 0 && (
                    <section className="flex flex-col gap-4">
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                            Additional Information
                        </h2>

                        {fields.map((field) => {
                            const key = `responses.${field.id}`;
                            const error = allErrors[key];
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
                                            onBlur={() =>
                                                markTouched(`responses.${field.id}`)
                                            }
                                            rows={4}
                                            aria-invalid={!!error}
                                            className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm text-foreground shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40"
                                        />
                                    )}

                                    {field.field_type === 'select' && (
                                        <Select
                                            value={typeof value === 'string' ? value : ''}
                                            onValueChange={(v) => {
                                                setResponse(field.id, v);
                                                markTouched(`responses.${field.id}`);
                                            }}
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
                                                    className="flex cursor-pointer items-center gap-2 text-sm text-foreground"
                                                >
                                                    <input
                                                        type="radio"
                                                        name={`field-${field.id}`}
                                                        value={option}
                                                        checked={value === option}
                                                        onChange={() => {
                                                            setResponse(field.id, option);
                                                            markTouched(`responses.${field.id}`);
                                                        }}
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
                                            onBlur={() =>
                                                markTouched(`responses.${field.id}`)
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

                <div className="flex flex-col gap-3 pt-2">
                    {form.processing && (
                        <p className="text-xs text-muted-foreground">Submitting…</p>
                    )}

                    <InputError message={allErrors.identity} />

                    {/* Consent — React-controlled gate. Not HTML5
                        `required`, because that blocks the submit
                        event entirely. Not persisted across reloads;
                        consent is not yet recorded server-side. See
                        ISSUE-010. */}
                    <div className="grid gap-1.5">
                        <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-white/10 bg-white/[0.02] p-4 text-sm">
                            <input
                                type="checkbox"
                                checked={consentAccepted}
                                onChange={(e) => {
                                    setConsentAccepted(e.target.checked);
                                }}
                                aria-invalid={!!allErrors.consent}
                                className="mt-0.5 h-4 w-4 shrink-0"
                            />
                            <span className="text-muted-foreground">
                                I have read and agree to the{' '}
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
                        <InputError message={allErrors.consent} />
                    </div>

                    <Button
                        type="submit"
                        disabled={form.processing}
                        className="w-full bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                    >
                        {form.processing ? 'Submitting…' : 'Submit Registration'}
                    </Button>
                    <p className="text-center text-xs text-muted-foreground">
                        * Required
                    </p>
                </div>
            </form>

            {/* Privacy Notice modal */}
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
                            {DEMO_PRIVACY_NOTICE}
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

            {/* Terms & Conditions modal */}
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
                            {DEMO_TERMS}
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
