import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { CHURCH_PARTICIPANT_TYPES } from '@/lib/church-participant-types';
import { humanNameQualityError } from '@/lib/event-validation';

type ChurchEvent = {
    event_name: string;
    church_category: string;
    registration_slug: string;
};

type Props = {
    event: ChurchEvent;
    submit_url: string;
};

function dateYearsAgo(years: number): string {
    const date = new Date();
    date.setFullYear(date.getFullYear() - years);
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

export default function ChurchPublic({ event, submit_url }: Props) {
    const [clientErrors, setClientErrors] = useState<Record<string, string>>(
        {},
    );
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

    useEffect(() => {
        try {
            const saved = localStorage.getItem(draftKey);
            if (saved) {
                form.setData({
                    ...form.data,
                    ...JSON.parse(saved),
                    consent: false,
                });
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

    const fields: Array<{
        key:
            | 'first_name'
            | 'middle_name'
            | 'last_name'
            | 'suffix'
            | 'email'
            | 'contact_number'
            | 'birthday'
            | 'address';
        label: string;
        required?: boolean;
        type?: string;
    }> = [
        { key: 'first_name', label: 'First name', required: true },
        { key: 'middle_name', label: 'Middle name' },
        { key: 'last_name', label: 'Last name', required: true },
        { key: 'suffix', label: 'Suffix' },
        { key: 'email', label: 'Email', type: 'email', required: true },
        { key: 'contact_number', label: 'Contact number', type: 'tel' },
        { key: 'birthday', label: 'Birthdate', type: 'date', required: true },
        { key: 'address', label: 'Barangay & City', required: true },
    ] as const;
    const guardian = ['Kids Ministry', 'Youth'].includes(event.church_category);
    const errorFor = (key: keyof typeof form.data) =>
        form.errors[key] as string | undefined;

    return (
        <>
            <Head title={`Register — ${event.event_name}`} />
            <main className="mx-auto max-w-xl space-y-5 p-6">
                <h1 className="text-2xl font-semibold">{event.event_name}</h1>
                <p>
                    Complete your details, then verify your email. Your
                    registration is for this event only.
                </p>
                <form
                    className="space-y-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        const validationErrors: Record<string, string> = {};
                        for (const [key, label] of [
                            [
                                'emergency_contact_first_name',
                                'Emergency contact first name',
                            ],
                            [
                                'emergency_contact_last_name',
                                'Emergency contact last name',
                            ],
                        ] as const) {
                            const value = form.data[key].trim();
                            if (
                                value.length < 2 ||
                                value.length > 50 ||
                                !/^[\p{L}\p{M} '-]+$/u.test(value)
                            ) {
                                validationErrors[key] =
                                    'Use 2–50 characters: letters, spaces, hyphens, or apostrophes.';
                            } else {
                                const qualityError = humanNameQualityError(
                                    value,
                                    label,
                                );
                                if (qualityError) {
                                    validationErrors[key] = qualityError;
                                }
                            }
                        }
                        if (
                            !/^(?:09|\+639)[0-9]{9}$/.test(
                                form.data.emergency_contact_number,
                            )
                        ) {
                            validationErrors.emergency_contact_number =
                                'Enter a valid Philippine mobile number: 09xxxxxxxxx or +639xxxxxxxxx.';
                        }
                        setClientErrors(validationErrors);
                        if (Object.keys(validationErrors).length > 0) return;
                        form.post(submit_url);
                    }}
                >
                    {(form.errors as Record<string, string>).identity && (
                        <p role="alert" className="text-destructive text-sm">
                            {(form.errors as Record<string, string>).identity}
                        </p>
                    )}
                    <div className="grid grid-cols-2 gap-3">
                        {fields.map(({ key, label, required, type }) => (
                            <div key={key} className="space-y-1">
                                <Label htmlFor={key}>{label}</Label>
                                <Input
                                    id={key}
                                    type={type ?? 'text'}
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
                                        form.setData(key, e.target.value)
                                    }
                                    aria-invalid={!!errorFor(key)}
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
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="participant_type">
                            Participant type
                        </Label>
                        <select
                            id="participant_type"
                            className="bg-background w-full rounded border p-2"
                            value={form.data.participant_type}
                            onChange={(e) =>
                                form.setData('participant_type', e.target.value)
                            }
                        >
                            {CHURCH_PARTICIPANT_TYPES.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    {guardian && (
                        <fieldset className="grid grid-cols-2 gap-3">
                            <legend className="col-span-2 text-sm font-medium">
                                Parent / guardian (optional)
                            </legend>
                            {(
                                ['guardian_name', 'guardian_contact'] as const
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
                                            form.setData(key, e.target.value)
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
                    <fieldset className="grid grid-cols-2 gap-3">
                        <legend className="col-span-2 text-sm font-medium">
                            Emergency contact
                        </legend>
                        {(
                            [
                                ['emergency_contact_first_name', 'First name'],
                                ['emergency_contact_last_name', 'Last name'],
                            ] as const
                        ).map(([key, label]) => (
                            <div key={key} className="space-y-1">
                                <Label htmlFor={key}>{label}</Label>
                                <Input
                                    id={key}
                                    value={form.data[key]}
                                    required
                                    aria-invalid={
                                        !!clientErrors[key] || !!errorFor(key)
                                    }
                                    onChange={(e) =>
                                        form.setData(key, e.target.value)
                                    }
                                />
                                {(clientErrors[key] || errorFor(key)) && (
                                    <p
                                        role="alert"
                                        className="text-destructive text-xs"
                                    >
                                        {clientErrors[key] || errorFor(key)}
                                    </p>
                                )}
                            </div>
                        ))}
                        <div className="space-y-1">
                            <Label htmlFor="emergency_contact_relationship">
                                Relationship
                            </Label>
                            <select
                                id="emergency_contact_relationship"
                                className="bg-background w-full rounded border p-2"
                                value={form.data.emergency_contact_relationship}
                                onChange={(e) =>
                                    form.setData(
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
                                    <option key={relationship}>
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
                                        form.setData(
                                            'emergency_contact_relationship_other',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                        )}
                        <div className="col-span-2 space-y-1">
                            <Label htmlFor="emergency_contact_number">
                                Emergency contact number
                            </Label>
                            <Input
                                id="emergency_contact_number"
                                type="tel"
                                value={form.data.emergency_contact_number}
                                required
                                aria-invalid={
                                    !!clientErrors.emergency_contact_number ||
                                    !!errorFor('emergency_contact_number')
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'emergency_contact_number',
                                        e.target.value,
                                    )
                                }
                            />
                            {(clientErrors.emergency_contact_number ||
                                errorFor('emergency_contact_number')) && (
                                <p
                                    role="alert"
                                    className="text-destructive text-xs"
                                >
                                    {clientErrors.emergency_contact_number ||
                                        errorFor('emergency_contact_number')}
                                </p>
                            )}
                        </div>
                    </fieldset>
                    <label className="flex items-start gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.consent}
                            onChange={(e) =>
                                form.setData('consent', e.target.checked)
                            }
                            required
                        />
                        I agree that the organizer may use these details to
                        manage my registration and attendance for this event.
                    </label>
                    {errorFor('consent') && (
                        <p role="alert" className="text-destructive text-xs">
                            {errorFor('consent')}
                        </p>
                    )}
                    <Button disabled={form.processing}>
                        {form.processing
                            ? 'Submitting…'
                            : 'Continue to email verification'}
                    </Button>
                </form>
            </main>
        </>
    );
}
