import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type FormData = {
    first_name: string;
    middle_name: string;
    last_name: string;
    suffix: string;
    email: string;
    contact_number: string;
    birthday: string;
    age: string;
    address: string;
    participant_type: string;
    guardian_name: string;
    guardian_contact: string;
    fellowship_group_id: string;
};
type Registration = {
    id: number;
    history_url: string;
    source: string;
    participant: Partial<FormData>;
    birthday: string | null;
    participant_type: string | null;
    guardian_name: string | null;
    guardian_contact: string | null;
    fellowship_group_id: number | null;
    attendance_code: string | null;
    registration_status: string;
    held_reason: string | null;
};
type Props = {
    event: {
        id: number;
        event_name: string;
        church_category: string | null;
        church_registration_open: boolean;
        church_activity?: string | null;
    };
    registrations: Registration[];
    message?: string;
    public_url?: string | null;
    groups: { id: number; name: string }[];
};
const empty: FormData = {
    first_name: '',
    middle_name: '',
    last_name: '',
    suffix: '',
    email: '',
    contact_number: '',
    birthday: '',
    age: '',
    address: '',
    participant_type: 'Member',
    guardian_name: '',
    guardian_contact: '',
    fellowship_group_id: '',
};
const fields: {
    key: keyof FormData;
    label: string;
    type?: string;
    placeholder?: string;
}[] = [
    { key: 'first_name', label: 'First name', placeholder: 'e.g. Maria' },
    {
        key: 'middle_name',
        label: 'Middle name',
        placeholder: 'Optional middle name',
    },
    { key: 'last_name', label: 'Last name', placeholder: 'e.g. Santos' },
    { key: 'suffix', label: 'Suffix', placeholder: 'Optional (Jr., Sr.)' },
    {
        key: 'email',
        label: 'Email',
        type: 'email',
        placeholder: 'name@example.com',
    },
    {
        key: 'contact_number',
        label: 'Contact number',
        type: 'tel',
        placeholder: '09XXXXXXXXX or +639XXXXXXXXX',
    },
    { key: 'birthday', label: 'Birthdate', type: 'date' },
    {
        key: 'age',
        label: 'Age (if birthdate is unknown)',
        type: 'number',
        placeholder: 'Enter age only if birthdate is unknown',
    },
    {
        key: 'address',
        label: 'Barangay & City',
        placeholder: 'e.g. Barangay, City',
    },
];
export default function ChurchRegistrations({
    event,
    registrations,
    message,
    public_url,
    groups,
}: Props) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<number | null>(null);
    const [deleteError, setDeleteError] = useState('');
    const form = useForm<FormData>(empty);
    const base = `/events/${event.id}/registrations`;
    const guardian =
        event.church_category === 'Kids Ministry' ||
        event.church_category === 'Youth' ||
        !!form.data.guardian_name;
    const errors = form.errors as Partial<
        Record<keyof FormData | 'identity', string>
    >;
    const edit = (row?: Registration) => {
        form.clearErrors();
        setEditing(row?.id ?? null);
        const values = { ...empty };
        if (row) {
            for (const key of Object.keys(values) as (keyof FormData)[])
                values[key] = String(row.participant[key] ?? '');
            values.birthday = row.birthday ?? '';
            values.participant_type = row.participant_type ?? 'Member';
            values.guardian_name = row.guardian_name ?? '';
            values.guardian_contact = row.guardian_contact ?? '';
            values.fellowship_group_id =
                row.fellowship_group_id?.toString() ?? '';
        }
        form.setData(values);
        setOpen(true);
    };
    return (
        <>
            <Head title={`Registrations — ${event.event_name}`} />
            <main className="mx-auto max-w-6xl space-y-6 p-6">
                <Link
                    href={`/events/${event.id}`}
                    className="text-sm underline"
                >
                    Back to event
                </Link>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            {event.event_name}
                        </h1>
                        <p className="text-muted-foreground">
                            Church registration ·{' '}
                            {
                                registrations.filter(
                                    (r) =>
                                        r.registration_status === 'confirmed',
                                ).length
                            }{' '}
                            confirmed
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link
                                href={`/events/${event.id}/possible-duplicates`}
                            >
                                Organizer review
                            </Link>
                        </Button>
                        <Button onClick={() => edit()}>Add Participant</Button>
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <Button variant="outline" asChild>
                        <Link href={`/events/${event.id}/church-attendance`}>
                            Attendance &amp; Summary
                        </Link>
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() =>
                            router.post(
                                `/events/${event.id}/church-publication`,
                                { open: !event.church_registration_open },
                            )
                        }
                    >
                        {event.church_registration_open
                            ? 'Close public registration'
                            : 'Open public registration'}
                    </Button>
                    {public_url && (
                        <a
                            className="break-all underline"
                            href={public_url}
                            target="_blank"
                            rel="noreferrer"
                        >
                            {public_url}
                        </a>
                    )}
                </div>
                {message && <p role="status">{message}</p>}
                {event.church_category === 'Fellowship' &&
                    groups.length > 0 && (
                        <p className="text-muted-foreground text-sm">
                            Fellowship groups are available in each registration
                            form.
                        </p>
                    )}
                {deleteError && (
                    <p role="alert" className="text-destructive">
                        {deleteError}
                    </p>
                )}
                <div className="bg-card overflow-x-auto rounded-xl border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted">
                            <tr>
                                {[
                                    'Name',
                                    'Contact',
                                    'Type / guardian',
                                    ...(groups.length > 0 ? ['Group'] : []),
                                    'Source',
                                    'Status',
                                    'Code',
                                    'Actions',
                                ].map((label) => (
                                    <th key={label} className="p-4">
                                        {label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {registrations.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="p-4">
                                        <Link
                                            className="underline"
                                            href={row.history_url}
                                        >
                                            {[
                                                row.participant.first_name,
                                                row.participant.middle_name,
                                                row.participant.last_name,
                                                row.participant.suffix,
                                            ]
                                                .filter(Boolean)
                                                .join(' ')}
                                        </Link>
                                    </td>
                                    <td className="p-4">
                                        <div>{row.participant.email}</div>
                                        <div>
                                            {row.participant.contact_number}
                                        </div>
                                    </td>
                                    <td className="p-4">
                                        {row.participant_type}
                                        <div className="text-muted-foreground">
                                            {row.guardian_name}{' '}
                                            {row.guardian_contact}
                                        </div>
                                    </td>
                                    {groups.length > 0 && (
                                        <td className="p-4">
                                            {groups.find(
                                                (group) =>
                                                    group.id ===
                                                    row.fellowship_group_id,
                                            )?.name ?? '—'}
                                        </td>
                                    )}
                                    <td className="p-4">{row.source}</td>
                                    <td className="p-4">
                                        {row.registration_status === 'held'
                                            ? 'Held / For Review'
                                            : row.registration_status}
                                        <div className="text-muted-foreground text-xs">
                                            {row.held_reason}
                                        </div>
                                    </td>
                                    <td className="p-4 font-mono">
                                        {row.attendance_code ?? '—'}
                                    </td>
                                    <td className="p-4">
                                        {row.registration_status ===
                                            'confirmed' && (
                                            <div className="flex gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => edit(row)}
                                                >
                                                    Edit
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => {
                                                        if (
                                                            !window.confirm(
                                                                'Delete this registration and its attendance? The participant record will be kept.',
                                                            )
                                                        )
                                                            return;
                                                        router.delete(
                                                            `${base}/${row.id}`,
                                                            {
                                                                preserveScroll: true,
                                                                onStart: () =>
                                                                    setDeleteError(
                                                                        '',
                                                                    ),
                                                                onError: (e) =>
                                                                    setDeleteError(
                                                                        Object.values(
                                                                            e,
                                                                        ).join(
                                                                            ' ',
                                                                        ),
                                                                    ),
                                                            },
                                                        );
                                                    }}
                                                >
                                                    Delete
                                                </Button>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {registrations.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={groups.length > 0 ? 8 : 7}
                                        className="text-muted-foreground p-6 text-center"
                                    >
                                        No registrations yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </main>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit registration' : 'Add Participant'}
                        </DialogTitle>
                    </DialogHeader>
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            const options = { onSuccess: () => setOpen(false) };
                            if (editing)
                                form.patch(`${base}/${editing}`, options);
                            else form.post(base, options);
                        }}
                    >
                        {errors.identity && (
                            <p
                                role="alert"
                                className="text-destructive text-sm"
                            >
                                {errors.identity}
                            </p>
                        )}
                        {!form.data.email && !form.data.contact_number && (
                            <p
                                role="status"
                                className="text-muted-foreground text-sm"
                            >
                                Without an email or mobile number, this person
                                cannot be matched automatically at future
                                events. Check the event list before adding them.
                            </p>
                        )}
                        <div className="grid grid-cols-2 gap-3">
                            {fields.map(({ key, label, type, placeholder }) => (
                                <div key={key} className="space-y-1">
                                    <Label htmlFor={`church-${key}`}>
                                        {label}
                                    </Label>
                                    <Input
                                        id={`church-${key}`}
                                        type={type ?? 'text'}
                                        value={form.data[key]}
                                        placeholder={placeholder}
                                        disabled={
                                            key === 'age' &&
                                            !!form.data.birthday
                                        }
                                        required={
                                            key === 'first_name' ||
                                            key === 'last_name'
                                        }
                                        onChange={(e) =>
                                            form.setData(key, e.target.value)
                                        }
                                    />
                                    {errors[key] && (
                                        <p
                                            role="alert"
                                            className="text-destructive text-xs"
                                        >
                                            {errors[key]}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                        <Label htmlFor="church-type">Participant type</Label>
                        <select
                            id="church-type"
                            className="bg-background w-full rounded-md border p-2"
                            value={form.data.participant_type}
                            onChange={(e) =>
                                form.setData('participant_type', e.target.value)
                            }
                        >
                            {['Member', 'First-Timer', 'Guest'].map((type) => (
                                <option key={type}>{type}</option>
                            ))}
                        </select>
                        {guardian && (
                            <div className="grid grid-cols-2 gap-3">
                                {(
                                    [
                                        'guardian_name',
                                        'guardian_contact',
                                    ] as const
                                ).map((key) => (
                                    <div key={key}>
                                        <Label htmlFor={key}>
                                            {key === 'guardian_name'
                                                ? 'Parent / guardian'
                                                : 'Guardian contact'}
                                        </Label>
                                        <Input
                                            id={key}
                                            value={form.data[key]}
                                            placeholder={
                                                key === 'guardian_name'
                                                    ? 'Parent or guardian name'
                                                    : '09XXXXXXXXX or +639XXXXXXXXX'
                                            }
                                            onChange={(e) =>
                                                form.setData(
                                                    key,
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {errors[key] && (
                                            <p
                                                role="alert"
                                                className="text-destructive text-xs"
                                            >
                                                {errors[key]}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                        {groups.length > 0 && (
                            <div className="space-y-1">
                                <Label htmlFor="fellowship-group">
                                    Fellowship group
                                </Label>
                                <select
                                    id="fellowship-group"
                                    className="bg-background w-full rounded-md border p-2"
                                    value={form.data.fellowship_group_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'fellowship_group_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">No group</option>
                                    {groups.map((group) => (
                                        <option key={group.id} value={group.id}>
                                            {group.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.fellowship_group_id && (
                                    <p
                                        role="alert"
                                        className="text-destructive text-xs"
                                    >
                                        {errors.fellowship_group_id}
                                    </p>
                                )}
                            </div>
                        )}
                        <div className="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button disabled={form.processing}>
                                {form.processing
                                    ? 'Saving…'
                                    : 'Save registration'}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
ChurchRegistrations.layout = {
    breadcrumbs: [{ title: 'Church registrations', href: '#' }],
};
