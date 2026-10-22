import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    Clock,
    MapPin,
    Pencil,
    Plus,
    Search,
    Trash2,
    UserRound,
    UserPlus,
    X,
} from 'lucide-react';
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
import { useConfirmDialog } from '@/hooks/use-confirm-dialog';

type AttendanceRow = {
    id: number;
    participant_id: number;
    first_name: string | null;
    last_name: string | null;
    full_name: string | null;
    age: number | null;
    birthday: string | null;
    address: string | null;
    contact_number: string | null;
    fellowship_group_id: number | null;
    attendance_status: string;
    notes: string | null;
};

type GroupRow = {
    id: number;
    name: string;
    display_order: number;
    attendance_count: number;
};

type EventData = {
    id: number;
    fellowship_type: string;
    fellowship_type_label: string;
    name: string;
    event_date: string;
    start_time: string | null;
    end_time: string | null;
    venue: string | null;
    facilitator: string | null;
    created_by: string | null;
};

type Props = {
    event: EventData;
    groups: GroupRow[];
    attendances: AttendanceRow[];
};

const STATUS_OPTIONS = [
    { value: 'present', label: 'Present' },
    { value: 'absent', label: 'Absent' },
];

const STATUS_STYLES: Record<string, string> = {
    present:
        'bg-lime-brand/15 text-lime-700 border-lime-brand/30 dark:text-lime-brand',
    absent: 'bg-destructive/10 text-destructive border-destructive/30',
};

const NO_GROUP = '__none__';

function formatDate(iso: string): string {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

export default function FellowshipAttendance({
    event,
    groups,
    attendances,
}: Props) {
    const [search, setSearch] = useState('');
    const [addOpen, setAddOpen] = useState(false);
    const [groupDialog, setGroupDialog] = useState<
        { mode: 'create' } | { mode: 'edit'; group: GroupRow } | null
    >(null);
    const { dialog, openConfirm } = useConfirmDialog();

    const visibleRows = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (q === '') return attendances;
        return attendances.filter((r) =>
            (r.full_name ?? '').toLowerCase().includes(q),
        );
    }, [attendances, search]);

    /**
     * One PUT updates status, group, or both. The mark request
     * requires attendance_status and accepts an optional
     * fellowship_group_id. When the operator changes one field, the
     * other is sent unchanged so the server never receives a partial
     * payload that would null the untouched field.
     */
    const updateRow = (
        row: AttendanceRow,
        patch: {
            attendance_status?: string;
            fellowship_group_id?: number | null;
        },
    ) => {
        const payload = {
            attendance_status: patch.attendance_status ?? row.attendance_status,
            fellowship_group_id:
                patch.fellowship_group_id !== undefined
                    ? patch.fellowship_group_id
                    : row.fellowship_group_id,
        };

        router.put(
            `/fellowship/events/${event.id}/attendance/${row.id}`,
            payload,
            { preserveScroll: true, preserveState: true },
        );
    };

    const confirmDeleteRow = (row: AttendanceRow) => {
        const name = row.full_name ?? 'this participant';

        openConfirm({
            variant: 'danger',
            title: 'Remove attendance?',
            description: `${name} will be removed from this gathering's attendance list. The participant record is preserved.`,
            confirmLabel: 'Remove',
            onConfirm: () =>
                router.delete(
                    `/fellowship/events/${event.id}/attendance/${row.id}`,
                    { preserveScroll: true },
                ),
        });
    };

    const confirmDeleteGroup = (group: GroupRow) => {
        openConfirm({
            variant: 'danger',
            title: `Delete "${group.name}"?`,
            description: `${group.attendance_count} attendance ${
                group.attendance_count === 1 ? 'record' : 'records'
            } will be reassigned to no group. The records are preserved.`,
            confirmLabel: 'Delete group',
            onConfirm: () =>
                router.delete(
                    `/fellowship/events/${event.id}/groups/${group.id}`,
                    { preserveScroll: true },
                ),
        });
    };

    return (
        <>
            <Head title={`Attendance — ${event.fellowship_type_label}`} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6">
                <Link
                    href={`/fellowship/${event.fellowship_type}`}
                    className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-2 text-sm transition-colors"
                >
                    <ArrowLeft className="h-4 w-4" />
                    {event.fellowship_type_label}
                </Link>

                <Button
                    variant="outline"
                    onClick={() => {
                        if (
                            window.confirm(
                                'Activate the shared Registration → Attendance workflow? Existing attendance will be preserved; adding and editing participants moves to Registration.',
                            )
                        ) {
                            router.post(
                                `/fellowship/events/${event.id}/registration-bridge`,
                            );
                        }
                    }}
                >
                    Set up registration and public link
                </Button>
                {/* Header */}
                <div className="glass-panel rounded-xl p-6">
                    <div className="pl-10">
                        <h1 className="text-foreground text-xl font-semibold">
                            {event.name}
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {formatDate(event.event_date)}
                        </p>
                        <div className="text-muted-foreground mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                            {event.start_time && event.end_time && (
                                <span className="inline-flex items-center gap-1.5">
                                    <Clock className="h-3 w-3" />
                                    {event.start_time} – {event.end_time}
                                </span>
                            )}
                            {event.venue && (
                                <span className="inline-flex items-center gap-1.5">
                                    <MapPin className="h-3 w-3" />
                                    {event.venue}
                                </span>
                            )}
                            {event.facilitator && (
                                <span className="inline-flex items-center gap-1.5">
                                    <UserRound className="h-3 w-3" />
                                    Led by {event.facilitator}
                                </span>
                            )}
                            {event.created_by && (
                                <span className="inline-flex items-center gap-1.5">
                                    <CalendarDays className="h-3 w-3" />
                                    Created by {event.created_by}
                                </span>
                            )}
                        </div>
                    </div>
                </div>

                {/* Groups panel */}
                <div className="glass-panel rounded-xl p-5">
                    <div className="mb-3 flex items-center justify-between">
                        <div>
                            <h2 className="text-foreground text-sm font-semibold">
                                Groups
                            </h2>
                            <p className="text-muted-foreground mt-0.5 text-xs">
                                Optional. Assign participants to a group to
                                track attendance by subgroup.
                            </p>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => setGroupDialog({ mode: 'create' })}
                        >
                            <Plus className="mr-1.5 h-3.5 w-3.5" />
                            Add group
                        </Button>
                    </div>

                    {groups.length === 0 ? (
                        <p className="text-muted-foreground text-xs italic">
                            No groups yet. Participants can be marked without a
                            group.
                        </p>
                    ) : (
                        <div className="flex flex-wrap gap-2">
                            {groups.map((g) => (
                                <div
                                    key={g.id}
                                    className="border-border bg-card/40 inline-flex items-center gap-2 rounded-full border py-1 pr-1.5 pl-3 text-xs"
                                >
                                    <span className="text-foreground font-medium">
                                        {g.name}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {g.attendance_count}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setGroupDialog({
                                                mode: 'edit',
                                                group: g,
                                            })
                                        }
                                        className="text-muted-foreground hover:bg-secondary hover:text-foreground rounded p-0.5 transition-colors"
                                        aria-label={`Edit group ${g.name}`}
                                    >
                                        <Pencil className="h-3 w-3" />
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => confirmDeleteGroup(g)}
                                        className="text-muted-foreground hover:bg-destructive/10 hover:text-destructive rounded p-0.5 transition-colors"
                                        aria-label={`Delete group ${g.name}`}
                                    >
                                        <X className="h-3 w-3" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Toolbar */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="relative max-w-sm flex-1">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search participant name…"
                            className="pl-9"
                        />
                    </div>
                    <Button
                        onClick={() => setAddOpen(true)}
                        className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        Add Participant
                    </Button>
                </div>

                {/* Roster */}
                {visibleRows.length === 0 ? (
                    <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-16 text-center">
                        <div className="bg-lime-brand/15 mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                            <UserPlus className="text-lime-brand h-8 w-8" />
                        </div>
                        <h2 className="text-foreground mb-2 text-lg font-semibold">
                            {search !== ''
                                ? 'No matches'
                                : 'No one is marked yet'}
                        </h2>
                        <p className="text-muted-foreground mb-6 max-w-sm text-sm">
                            {search !== ''
                                ? 'Try a different search term.'
                                : 'Add the first participant to begin recording attendance.'}
                        </p>
                    </div>
                ) : (
                    <div className="glass-panel overflow-hidden rounded-xl">
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-border border-b text-left">
                                        <th className="text-muted-foreground px-3 py-3 text-xs font-semibold tracking-wider uppercase">
                                            Status
                                        </th>
                                        <th className="text-muted-foreground px-3 py-3 text-xs font-semibold tracking-wider uppercase">
                                            Name
                                        </th>
                                        <th className="text-muted-foreground px-3 py-3 text-xs font-semibold tracking-wider uppercase">
                                            Age
                                        </th>
                                        <th className="text-muted-foreground px-3 py-3 text-xs font-semibold tracking-wider uppercase">
                                            Contact
                                        </th>
                                        <th className="text-muted-foreground px-3 py-3 text-xs font-semibold tracking-wider uppercase">
                                            Group
                                        </th>
                                        <th className="text-muted-foreground px-3 py-3 text-xs font-semibold tracking-wider uppercase">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {visibleRows.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-border hover:bg-card/40 border-b transition-colors last:border-b-0"
                                        >
                                            <td className="px-3 py-3">
                                                <select
                                                    value={
                                                        row.attendance_status
                                                    }
                                                    onChange={(e) =>
                                                        updateRow(row, {
                                                            attendance_status:
                                                                e.target.value,
                                                        })
                                                    }
                                                    className={`cursor-pointer rounded-full border px-2.5 py-1 text-xs font-medium outline-none ${
                                                        row.attendance_status ===
                                                        'excused'
                                                            ? 'border-border bg-secondary text-foreground'
                                                            : (STATUS_STYLES[
                                                                  row
                                                                      .attendance_status
                                                              ] ??
                                                              STATUS_STYLES.present)
                                                    }`}
                                                >
                                                    {row.attendance_status ===
                                                        'excused' && (
                                                        <option
                                                            value="excused"
                                                            disabled
                                                        >
                                                            Excused
                                                        </option>
                                                    )}
                                                    {STATUS_OPTIONS.map((o) => (
                                                        <option
                                                            key={o.value}
                                                            value={o.value}
                                                        >
                                                            {o.label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </td>
                                            <td className="text-foreground px-3 py-3 text-sm font-medium">
                                                {row.full_name ?? '—'}
                                            </td>
                                            <td className="text-muted-foreground px-3 py-3 text-sm">
                                                {row.age ?? '—'}
                                            </td>
                                            <td className="text-muted-foreground px-3 py-3 text-sm">
                                                {row.contact_number ?? '—'}
                                            </td>
                                            <td className="px-3 py-3">
                                                <Select
                                                    value={
                                                        row.fellowship_group_id !==
                                                        null
                                                            ? String(
                                                                  row.fellowship_group_id,
                                                              )
                                                            : NO_GROUP
                                                    }
                                                    onValueChange={(v) =>
                                                        updateRow(row, {
                                                            fellowship_group_id:
                                                                v === NO_GROUP
                                                                    ? null
                                                                    : Number(v),
                                                        })
                                                    }
                                                >
                                                    <SelectTrigger className="h-8 w-40 text-xs">
                                                        <SelectValue placeholder="No group" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem
                                                            value={NO_GROUP}
                                                        >
                                                            No group
                                                        </SelectItem>
                                                        {groups.map((g) => (
                                                            <SelectItem
                                                                key={g.id}
                                                                value={String(
                                                                    g.id,
                                                                )}
                                                            >
                                                                {g.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </td>
                                            <td className="px-3 py-3">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        confirmDeleteRow(row)
                                                    }
                                                    className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                    aria-label={`Remove ${row.full_name ?? 'this participant'}`}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>

            {dialog}

            <AddParticipantDialog
                eventId={event.id}
                eventName={event.name}
                groups={groups}
                open={addOpen}
                onOpenChange={setAddOpen}
            />

            {groupDialog !== null && (
                <GroupFormDialog
                    eventId={event.id}
                    mode={groupDialog.mode}
                    existing={
                        groupDialog.mode === 'edit'
                            ? groupDialog.group
                            : undefined
                    }
                    open
                    onOpenChange={(o) => {
                        if (!o) setGroupDialog(null);
                    }}
                />
            )}
        </>
    );
}

type AddParticipantProps = {
    eventId: number;
    eventName: string;
    groups: GroupRow[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function AddParticipantDialog({
    eventId,
    eventName,
    groups,
    open,
    onOpenChange,
}: AddParticipantProps) {
    const form = useForm({
        first_name: '',
        last_name: '',
        age: '' as string | number,
        birthday: '',
        address: '',
        contact_number: '',
        fellowship_group_id: NO_GROUP as string,
    });

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            fellowship_group_id:
                data.fellowship_group_id === NO_GROUP
                    ? null
                    : Number(data.fellowship_group_id),
        }));
        form.post(`/fellowship/events/${eventId}/attendance`, {
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
            preserveScroll: true,
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Add Participant</DialogTitle>
                    <DialogDescription>
                        {eventName}. If the phone number matches an existing
                        participant, that record is reused.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={onSubmit} className="grid gap-4">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="attn-first-name">
                                First Name{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="attn-first-name"
                                value={form.data.first_name}
                                onChange={(e) =>
                                    form.setData('first_name', e.target.value)
                                }
                                placeholder="e.g. Ana"
                                autoFocus
                            />
                            <InputError message={form.errors.first_name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="attn-last-name">
                                Last Name{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="attn-last-name"
                                value={form.data.last_name}
                                onChange={(e) =>
                                    form.setData('last_name', e.target.value)
                                }
                                placeholder="e.g. Santos"
                            />
                            <InputError message={form.errors.last_name} />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="attn-age">Age</Label>
                            <Input
                                id="attn-age"
                                type="number"
                                min={1}
                                max={120}
                                value={form.data.age}
                                onChange={(e) =>
                                    form.setData('age', e.target.value)
                                }
                                placeholder="e.g. 25"
                            />
                            <InputError message={form.errors.age} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="attn-birthday">Birthday</Label>
                            <Input
                                id="attn-birthday"
                                type="date"
                                value={form.data.birthday}
                                onChange={(e) =>
                                    form.setData('birthday', e.target.value)
                                }
                            />
                            <InputError message={form.errors.birthday} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="attn-contact">Contact Number</Label>
                        <Input
                            id="attn-contact"
                            value={form.data.contact_number}
                            onChange={(e) =>
                                form.setData('contact_number', e.target.value)
                            }
                            placeholder="09XXXXXXXXX or +639XXXXXXXXX"
                        />
                        <InputError message={form.errors.contact_number} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="attn-address">Address</Label>
                        <Input
                            id="attn-address"
                            value={form.data.address}
                            onChange={(e) =>
                                form.setData('address', e.target.value)
                            }
                            placeholder="e.g. Barangay, City"
                        />
                        <InputError message={form.errors.address} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="attn-group">Group</Label>
                        <Select
                            value={form.data.fellowship_group_id}
                            onValueChange={(v) =>
                                form.setData('fellowship_group_id', v)
                            }
                        >
                            <SelectTrigger id="attn-group" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NO_GROUP}>
                                    No group
                                </SelectItem>
                                {groups.map((g) => (
                                    <SelectItem key={g.id} value={String(g.id)}>
                                        {g.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.fellowship_group_id} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                        >
                            {form.processing ? 'Adding…' : 'Add'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

type GroupFormProps = {
    eventId: number;
    mode: 'create' | 'edit';
    existing?: GroupRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function GroupFormDialog({
    eventId,
    mode,
    existing,
    open,
    onOpenChange,
}: GroupFormProps) {
    const isEdit = mode === 'edit';

    const form = useForm({
        name: existing?.name ?? '',
        display_order: existing?.display_order ?? 0,
    });

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();

        const onSuccess = () => {
            form.reset();
            onOpenChange(false);
        };

        if (isEdit && existing) {
            form.put(`/fellowship/events/${eventId}/groups/${existing.id}`, {
                onSuccess,
                preserveScroll: true,
            });
        } else {
            form.post(`/fellowship/events/${eventId}/groups`, {
                onSuccess,
                preserveScroll: true,
            });
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-sm">
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? 'Rename group' : 'Add group'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEdit
                            ? 'Rename this group. Participants assigned to it keep their assignment.'
                            : 'Create a group to track attendance by subgroup.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={onSubmit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="group-name">
                            Name <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="group-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="e.g. Parents, Youth, Children"
                            autoFocus
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="group-order">Display order</Label>
                        <Input
                            id="group-order"
                            type="number"
                            min={0}
                            max={9999}
                            value={form.data.display_order}
                            onChange={(e) =>
                                form.setData(
                                    'display_order',
                                    Number(e.target.value),
                                )
                            }
                        />
                        <p className="text-muted-foreground text-xs">
                            Lower numbers appear first.
                        </p>
                        <InputError message={form.errors.display_order} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                        >
                            {form.processing
                                ? 'Saving…'
                                : isEdit
                                  ? 'Save'
                                  : 'Create'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

FellowshipAttendance.layout = {
    breadcrumbs: [
        { title: 'Fellowship', href: '/events/church/fellowship' },
        { title: 'Attendance', href: '#' },
    ],
};
