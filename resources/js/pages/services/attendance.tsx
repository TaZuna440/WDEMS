import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Clock, Plus, Search, Trash2, UserPlus } from 'lucide-react';
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
import { useConfirmDialog } from '@/hooks/use-confirm-dialog';

type BaseRow = {
    id: number;
    participant_id: number;
    first_name: string | null;
    last_name: string | null;
    full_name: string | null;
    age: number | null;
    birthday: string | null;
    address: string | null;
    contact_number: string | null;
    attendance_status: string;
    notes: string | null;
};

type MemberRow = BaseRow & { cell_leader: string | null };
type FirstTimerRow = BaseRow & { invited_by: string | null };

type Occurrence = {
    id: number;
    service_type: string;
    service_type_label: string;
    occurrence_date: string;
    start_time: string | null;
    end_time: string | null;
    service_leader: string | null;
    notes: string | null;
    created_by: string | null;
};

type Props = {
    occurrence: Occurrence;
    members: MemberRow[];
    first_timers: FirstTimerRow[];
};

const STATUS_OPTIONS = [
    { value: 'present', label: 'Present' },
    { value: 'absent', label: 'Absent' },
    { value: 'excused', label: 'Excused' },
];

const STATUS_STYLES: Record<string, string> = {
    present:
        'bg-lime-brand/15 text-lime-700 border-lime-brand/30 dark:text-lime-brand',
    absent: 'bg-destructive/10 text-destructive border-destructive/30',
    excused:
        'bg-[#13253B]/10 text-[#13253B] border-[#13253B]/30 dark:bg-yellow-500/10 dark:text-yellow-500 dark:border-yellow-500/30',
};

function formatDate(iso: string): string {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

export default function ServiceAttendance({
    occurrence,
    members,
    first_timers,
}: Props) {
    const [tab, setTab] = useState<'members' | 'first_timers'>('members');
    const [search, setSearch] = useState('');
    const [addOpen, setAddOpen] = useState(false);
    const { dialog, openConfirm } = useConfirmDialog();

    const isMembersTab = tab === 'members';

    const visibleMembers = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (q === '') return members;
        return members.filter((r) =>
            (r.full_name ?? '').toLowerCase().includes(q),
        );
    }, [members, search]);

    const visibleFirstTimers = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (q === '') return first_timers;
        return first_timers.filter((r) =>
            (r.full_name ?? '').toLowerCase().includes(q),
        );
    }, [first_timers, search]);

    const visibleRows: (MemberRow | FirstTimerRow)[] = isMembersTab
        ? visibleMembers
        : visibleFirstTimers;

    const updateStatus = (row: MemberRow | FirstTimerRow, status: string) => {
        const url = isMembersTab
            ? `/services/occurrences/${occurrence.id}/attendance/members/${row.id}`
            : `/services/occurrences/${occurrence.id}/attendance/first-timers/${row.id}`;

        router.put(
            url,
            { attendance_status: status },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    const confirmDelete = (row: MemberRow | FirstTimerRow) => {
        const name = row.full_name ?? 'this participant';

        openConfirm({
            variant: 'danger',
            title: 'Remove attendance?',
            description: `${name} will be removed from this service's ${
                isMembersTab ? 'members' : 'first-timers'
            } list. The participant record is preserved.`,
            confirmLabel: 'Remove',
            onConfirm: () => {
                const url = isMembersTab
                    ? `/services/occurrences/${occurrence.id}/attendance/members/${row.id}`
                    : `/services/occurrences/${occurrence.id}/attendance/first-timers/${row.id}`;

                router.delete(url, { preserveScroll: true });
            },
        });
    };

    return (
        <>
            <Head title={`Attendance — ${occurrence.service_type_label}`} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6">
                <Link
                    href={`/services/${occurrence.service_type}`}
                    className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-2 text-sm transition-colors"
                >
                    <ArrowLeft className="h-4 w-4" />
                    {occurrence.service_type_label}
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
                                `/services/occurrences/${occurrence.id}/registration-bridge`,
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
                            {occurrence.service_type_label}
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {formatDate(occurrence.occurrence_date)}
                        </p>
                        <div className="text-muted-foreground mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                            {occurrence.start_time && occurrence.end_time && (
                                <span className="inline-flex items-center gap-1.5">
                                    <Clock className="h-3 w-3" />
                                    {occurrence.start_time} –{' '}
                                    {occurrence.end_time}
                                </span>
                            )}
                            {occurrence.service_leader && (
                                <span>Led by {occurrence.service_leader}</span>
                            )}
                        </div>
                        {occurrence.notes && (
                            <p className="text-muted-foreground mt-3 max-w-2xl text-sm italic">
                                {occurrence.notes}
                            </p>
                        )}
                    </div>
                </div>

                {/* Tabs */}
                <div className="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        onClick={() => {
                            setTab('members');
                            setSearch('');
                        }}
                        className={`inline-flex items-center gap-2 rounded-full border px-4 py-1.5 text-sm font-medium transition-colors ${
                            isMembersTab
                                ? 'border-lime-brand/40 bg-lime-brand/25 dark:bg-lime-brand/20 dark:text-lime-brand text-lime-700'
                                : 'border-border bg-card/70 text-foreground hover:bg-card'
                        }`}
                    >
                        Members
                        <span
                            className={
                                isMembersTab
                                    ? 'text-lime-brand'
                                    : 'text-muted-foreground/60'
                            }
                        >
                            ({members.length})
                        </span>
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            setTab('first_timers');
                            setSearch('');
                        }}
                        className={`inline-flex items-center gap-2 rounded-full border px-4 py-1.5 text-sm font-medium transition-colors ${
                            !isMembersTab
                                ? 'border-lime-brand/40 bg-lime-brand/25 dark:bg-lime-brand/20 dark:text-lime-brand text-lime-700'
                                : 'border-border bg-card/70 text-foreground hover:bg-card'
                        }`}
                    >
                        First-Timers
                        <span
                            className={
                                !isMembersTab
                                    ? 'text-lime-brand'
                                    : 'text-muted-foreground/60'
                            }
                        >
                            ({first_timers.length})
                        </span>
                    </button>
                </div>

                {/* Toolbar */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="relative max-w-sm flex-1">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={
                                isMembersTab
                                    ? 'Search member name…'
                                    : 'Search first-timer name…'
                            }
                            className="pl-9"
                        />
                    </div>
                    <Button
                        onClick={() => setAddOpen(true)}
                        className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        {isMembersTab ? 'Add Member' : 'Add First-Timer'}
                    </Button>
                </div>

                {/* Table */}
                {visibleRows.length === 0 ? (
                    <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-16 text-center">
                        <div className="bg-lime-brand/15 mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                            <UserPlus className="text-lime-brand h-8 w-8" />
                        </div>
                        <h2 className="text-foreground mb-2 text-lg font-semibold">
                            {search !== ''
                                ? 'No matches'
                                : `No ${
                                      isMembersTab ? 'members' : 'first-timers'
                                  } yet`}
                        </h2>
                        <p className="text-muted-foreground mb-6 max-w-sm text-sm">
                            {search !== ''
                                ? 'Try a different search term.'
                                : `Add the first ${
                                      isMembersTab ? 'member' : 'first-timer'
                                  } to this service.`}
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
                                            Address
                                        </th>
                                        <th className="text-muted-foreground px-3 py-3 text-xs font-semibold tracking-wider uppercase">
                                            Birthday
                                        </th>
                                        <th className="text-muted-foreground px-3 py-3 text-xs font-semibold tracking-wider uppercase">
                                            Contact
                                        </th>
                                        <th className="text-muted-foreground px-3 py-3 text-xs font-semibold tracking-wider uppercase">
                                            {isMembersTab
                                                ? 'Cell Leader'
                                                : 'Invited By'}
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
                                                        updateStatus(
                                                            row,
                                                            e.target.value,
                                                        )
                                                    }
                                                    className={`cursor-pointer rounded-full border px-2.5 py-1 text-xs font-medium outline-none ${
                                                        STATUS_STYLES[
                                                            row
                                                                .attendance_status
                                                        ] ??
                                                        STATUS_STYLES.present
                                                    }`}
                                                >
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
                                            <td className="text-muted-foreground max-w-[220px] truncate px-3 py-3 text-sm">
                                                {row.address ?? '—'}
                                            </td>
                                            <td className="text-muted-foreground px-3 py-3 text-sm">
                                                {row.birthday ?? '—'}
                                            </td>
                                            <td className="text-muted-foreground px-3 py-3 text-sm">
                                                {row.contact_number ?? '—'}
                                            </td>
                                            <td className="text-muted-foreground px-3 py-3 text-sm">
                                                {isMembersTab
                                                    ? ((row as MemberRow)
                                                          .cell_leader ?? '—')
                                                    : ((row as FirstTimerRow)
                                                          .invited_by ?? '—')}
                                            </td>
                                            <td className="px-3 py-3">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        confirmDelete(row)
                                                    }
                                                    className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                    aria-label={`Remove ${
                                                        row.full_name ??
                                                        'this participant'
                                                    }`}
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

            <AddAttendanceDialog
                key={tab}
                open={addOpen}
                onOpenChange={setAddOpen}
                occurrenceId={occurrence.id}
                track={tab}
                serviceTypeLabel={occurrence.service_type_label}
            />
        </>
    );
}

type AddDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    occurrenceId: number;
    track: 'members' | 'first_timers';
    serviceTypeLabel: string;
};

function AddAttendanceDialog({
    open,
    onOpenChange,
    occurrenceId,
    track,
    serviceTypeLabel,
}: AddDialogProps) {
    const form = useForm({
        track: track === 'members' ? 'member' : 'first_timer',
        first_name: '',
        last_name: '',
        age: '' as string | number,
        birthday: '',
        address: '',
        contact_number: '',
        invited_by: '',
    });

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/services/occurrences/${occurrenceId}/attendance`, {
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
                    <DialogTitle>
                        Add {track === 'members' ? 'Member' : 'First-Timer'}
                    </DialogTitle>
                    <DialogDescription>
                        {serviceTypeLabel}. If the email or phone matches an
                        existing participant, that record is reused.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={onSubmit} className="grid gap-4">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="first_name">
                                First Name{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="first_name"
                                value={form.data.first_name}
                                onChange={(e) =>
                                    form.setData('first_name', e.target.value)
                                }
                                placeholder="e.g. Ana"
                            />
                            <InputError message={form.errors.first_name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="last_name">
                                Last Name{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="last_name"
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
                            <Label htmlFor="age">Age</Label>
                            <Input
                                id="age"
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
                            <Label htmlFor="birthday">Birthday</Label>
                            <Input
                                id="birthday"
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
                        <Label htmlFor="contact_number">Contact Number</Label>
                        <Input
                            id="contact_number"
                            value={form.data.contact_number}
                            onChange={(e) =>
                                form.setData('contact_number', e.target.value)
                            }
                            placeholder="09XXXXXXXXX or +639XXXXXXXXX"
                        />
                        <InputError message={form.errors.contact_number} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="address">Address</Label>
                        <Input
                            id="address"
                            value={form.data.address}
                            onChange={(e) =>
                                form.setData('address', e.target.value)
                            }
                            placeholder="e.g. Barangay, City"
                        />
                        <InputError message={form.errors.address} />
                    </div>

                    {track === 'first_timers' && (
                        <div className="grid gap-2">
                            <Label htmlFor="invited_by">Invited By</Label>
                            <Input
                                id="invited_by"
                                value={form.data.invited_by}
                                onChange={(e) =>
                                    form.setData('invited_by', e.target.value)
                                }
                                placeholder="e.g. Pastor Mark"
                            />
                            <InputError message={form.errors.invited_by} />
                        </div>
                    )}

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

ServiceAttendance.layout = {
    breadcrumbs: [
        { title: 'Services', href: '/events/church/services' },
        { title: 'Attendance', href: '#' },
    ],
};
