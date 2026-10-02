import { Head, useForm } from '@inertiajs/react';
import { CalendarDays, CheckCircle2, ExternalLink, MapPin } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

    const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

    const allErrors: Record<string, string> = {
        ...form.errors,
        ...clientErrors,
    };

    const isRequired = (field: string): boolean =>
        common_field_requirements[field] ?? true;

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

    const setResponse = (fieldId: number, value: string | string[]) => {
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

    const submit = (event_: FormEvent) => {
        event_.preventDefault();
        const errors = validateRegistrationForm(
            form.data,
            fields,
            common_field_requirements,
        );
        setClientErrors(errors);
        if (Object.keys(errors).length > 0) {
            return;
        }
        form.post(submit_url);
    };

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
                                value={form.data.first_name}
                                onChange={(e) => form.setData('first_name', e.target.value)}
                                autoComplete="given-name"
                                aria-invalid={!!allErrors.first_name}
                            />
                            <InputError message={allErrors.first_name} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="last_name">Last name *</Label>
                            <Input
                                id="last_name"
                                value={form.data.last_name}
                                onChange={(e) => form.setData('last_name', e.target.value)}
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
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
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
                                value={form.data.contact_number}
                                onChange={(e) => form.setData('contact_number', e.target.value)}
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
                                value={form.data.age}
                                onChange={(e) => form.setData('age', e.target.value)}
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
                                value={form.data.address}
                                onChange={(e) => form.setData('address', e.target.value)}
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
                                            onChange={(e) => setResponse(field.id, e.target.value)}
                                            rows={4}
                                            aria-invalid={!!error}
                                            className="flex min-h-[80px] w-full rounded-md border border-white/10 bg-white/[0.02] px-3 py-2 text-sm text-foreground focus:border-lime-brand/50 focus:outline-none focus:ring-1 focus:ring-lime-brand/50"
                                        />
                                    )}

                                    {field.field_type === 'select' && (
                                        <select
                                            id={`field-${field.id}`}
                                            value={typeof value === 'string' ? value : ''}
                                            onChange={(e) => setResponse(field.id, e.target.value)}
                                            aria-invalid={!!error}
                                            className="h-9 w-full rounded-md border border-white/10 bg-white/[0.02] px-3 text-sm text-foreground focus:border-lime-brand/50 focus:outline-none"
                                        >
                                            <option value="">Choose…</option>
                                            {field.options.map((option) => (
                                                <option key={option} value={option}>
                                                    {option}
                                                </option>
                                            ))}
                                        </select>
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
                                                        onChange={() => setResponse(field.id, option)}
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
                                            onChange={(e) => setResponse(field.id, e.target.value)}
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
        </>
    );
}
