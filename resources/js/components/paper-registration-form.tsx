import EncoderNameSearch from '@/components/encoder-name-search';
import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Field = {
    id: number;
    label: string;
    field_type: string;
    options: string[];
    is_required: boolean;
};
type Props = {
    eventId: number;
    forceOpen: boolean;
    version: number;
    fields: Field[];
    tshirt: { label: string; choices: string[]; required: boolean } | null;
};
export default function PaperRegistrationForm({
    eventId,
    forceOpen,
    version,
    fields,
    tshirt,
}: Props) {
    const form = useForm({
        first_name: '',
        middle_name: '',
        last_name: '',
        suffix: '',
        birthday: '',
        email: '',
        contact_number: '',
        address: '',
        emergency_contact_name: '',
        emergency_contact_first_name: '',
        emergency_contact_last_name: '',
        emergency_contact_relationship: '',
        emergency_contact_relationship_other: '',
        emergency_contact_number: '',
        tshirt_choice: '',
        encoding_mode: 'live',
        arrival_time: '',
        responses: {} as Record<number, string | string[]>,
        identity: '',
    });
    const errors = form.errors as Record<string, string>;
    const input = (
        key: keyof typeof form.data,
        label: string,
        type = 'text',
    ) => (
        <div className="space-y-1" key={key}>
            <Label htmlFor={`paper-${key}`}>{label}</Label>
            <Input
                id={`paper-${key}`}
                type={type}
                value={String(form.data[key] as any)}
                onChange={(e) => form.setData(key, e.target.value)}
                disabled={form.processing}
            />
            <InputError message={errors[key]} />
        </div>
    );
    const setResponse = (id: number, value: string | string[]) =>
        form.setData('responses', { ...form.data.responses, [id]: value });
    return (
        <form
            className="space-y-5"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(
                    `/events/${eventId}/attendance/encode-paper${forceOpen ? '?force_open=1' : ''}`,
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            const mode = form.data.encoding_mode;
                            form.reset();
                            form.setData('encoding_mode', mode);
                        },
                    },
                );
            }}
        >
            <p className="text-muted-foreground text-sm">
                Enter the completed paper form. Saving attests that the paper
                form includes agreement to the disclaimer and privacy notice and
                attests to its supplied contact details, records your account as
                encoder, and marks attendance Present. No participant email OTP
                is sent.
            </p>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1">
                    <Label htmlFor="paper-mode">Encoding mode</Label>
                    <select
                        id="paper-mode"
                        className="bg-background h-9 w-full rounded-md border px-3"
                        value={form.data.encoding_mode}
                        onChange={(e) =>
                            form.setData('encoding_mode', e.target.value)
                        }
                    >
                        <option value="live">Live — arriving now</option>
                        <option value="deferred">
                            Deferred — paper submission time
                        </option>
                    </select>
                </div>
                {form.data.encoding_mode === 'deferred' &&
                    input('arrival_time', 'Submission time', 'time')}
                {input('first_name', 'First name')}
                {input('last_name', 'Last name')}
                {input('middle_name', 'Middle name (optional)')}
                {input('suffix', 'Suffix (optional)')}
                {input('birthday', 'Birthdate', 'date')}
                {input('email', 'Email', 'email')}
                {input('contact_number', 'Contact number', 'tel')}
                {input('address', 'Barangay and city')}
            </div>
            <h3 className="font-semibold">Emergency contact</h3>
            <div className="grid gap-4 sm:grid-cols-2">
                {version >= 2 ? (
                    <>
                        {input('emergency_contact_first_name', 'First name')}
                        {input('emergency_contact_last_name', 'Last name')}
                    </>
                ) : (
                    input('emergency_contact_name', 'Full name')
                )}
                {version >= 2 ? (
                    <div className="space-y-1">
                        <Label htmlFor="paper-relationship">Relationship</Label>
                        <select
                            id="paper-relationship"
                            className="bg-background h-9 w-full rounded-md border px-3"
                            value={form.data.emergency_contact_relationship}
                            onChange={(e) =>
                                form.setData(
                                    'emergency_contact_relationship',
                                    e.target.value,
                                )
                            }
                        >
                            <option value="">Choose relationship</option>
                            {[
                                'Parent',
                                'Sibling',
                                'Spouse',
                                'Friend',
                                'Guardian',
                                'Other',
                            ].map((value) => (
                                <option key={value}>{value}</option>
                            ))}
                        </select>
                        <InputError
                            message={errors.emergency_contact_relationship}
                        />
                    </div>
                ) : (
                    input('emergency_contact_relationship', 'Relationship')
                )}
                {version >= 2 &&
                    form.data.emergency_contact_relationship === 'Other' &&
                    input(
                        'emergency_contact_relationship_other',
                        'Other relationship',
                    )}
                {input('emergency_contact_number', 'Emergency phone', 'tel')}
            </div>
            {tshirt && (
                <div className="space-y-1">
                    <Label htmlFor="paper-shirt">
                        {tshirt.label}
                        {tshirt.required ? ' *' : ''}
                    </Label>
                    <select
                        id="paper-shirt"
                        className="bg-background h-9 w-full rounded-md border px-3"
                        value={form.data.tshirt_choice}
                        onChange={(e) =>
                            form.setData('tshirt_choice', e.target.value)
                        }
                    >
                        <option value="">Choose size</option>
                        {tshirt.choices.map((choice) => (
                            <option key={choice}>{choice}</option>
                        ))}
                    </select>
                    <InputError message={errors.tshirt_choice} />
                </div>
            )}
            {fields.map((field) => (
                <div key={field.id} className="space-y-2">
                    <Label htmlFor={`paper-field-${field.id}`}>
                        {field.label}
                        {field.is_required ? ' *' : ''}
                    </Label>
                    {field.field_type === 'checkbox' ? (
                        field.options.map((option) => (
                            <label
                                key={option}
                                className="mr-4 inline-flex items-center gap-2"
                            >
                                <input
                                    type="checkbox"
                                    checked={
                                        Array.isArray(
                                            form.data.responses[field.id],
                                        ) &&
                                        form.data.responses[field.id].includes(
                                            option,
                                        )
                                    }
                                    onChange={(e) => {
                                        const previous =
                                            form.data.responses[field.id];
                                        const values = Array.isArray(previous)
                                            ? previous
                                            : [];
                                        setResponse(
                                            field.id,
                                            e.target.checked
                                                ? [...values, option]
                                                : values.filter(
                                                      (value) =>
                                                          value !== option,
                                                  ),
                                        );
                                    }}
                                />
                                {option}
                            </label>
                        ))
                    ) : ['select', 'radio'].includes(field.field_type) ? (
                        <select
                            id={`paper-field-${field.id}`}
                            className="bg-background h-9 w-full rounded-md border px-3"
                            value={String(form.data.responses[field.id] ?? '')}
                            onChange={(e) =>
                                setResponse(field.id, e.target.value)
                            }
                        >
                            <option value="">Choose an answer</option>
                            {field.options.map((option) => (
                                <option key={option}>{option}</option>
                            ))}
                        </select>
                    ) : field.field_type === 'textarea' ? (
                        <textarea
                            id={`paper-field-${field.id}`}
                            className="bg-background min-h-20 w-full rounded-md border p-2"
                            value={String(form.data.responses[field.id] ?? '')}
                            onChange={(e) =>
                                setResponse(field.id, e.target.value)
                            }
                        />
                    ) : (
                        <Input
                            id={`paper-field-${field.id}`}
                            type={
                                ['number', 'email', 'date'].includes(
                                    field.field_type,
                                )
                                    ? field.field_type
                                    : 'text'
                            }
                            value={String(form.data.responses[field.id] ?? '')}
                            onChange={(e) =>
                                setResponse(field.id, e.target.value)
                            }
                        />
                    )}
                    <InputError message={errors[`responses.${field.id}`]} />
                </div>
            ))}
            <InputError message={errors.identity} />
            <EncoderNameSearch
                eventId={eventId}
                forceOpen={forceOpen}
                name={[
                    form.data.first_name,
                    form.data.middle_name,
                    form.data.last_name,
                    form.data.suffix,
                ]
                    .filter(Boolean)
                    .join(' ')}
            />
            <Button type="submit" disabled={form.processing}>
                {form.processing
                    ? 'Saving…'
                    : 'Save paper registration and mark present'}
            </Button>
        </form>
    );
}
