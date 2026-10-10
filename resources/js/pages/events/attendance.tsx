import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    CheckCircle2,
    Clock,
    Plus,
    Search,
    Unlock,
    Lock,
    UserCheck,
    MoreHorizontal,
    Save,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import AttendanceFilterChips, {
    type AttendanceFilter,
} from '@/components/attendance-filter-chips';
import BulkMarkToolbar from '@/components/bulk-mark-toolbar';
import BulkWalkInTable from '@/components/bulk-walk-in-table';
import Pagination from '@/components/pagination';
import WalkInDialog from '@/components/walk-in-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    makeBulkWalkInRow,
    validateBulkWalkInRows,
    type BulkWalkInRow,
} from '@/lib/attendance-bulk-validation';

type AttendanceStatus = 'present' | 'late' | 'absent' | 'excused';

type PageMode = 'mark' | 'confirm' | 'batch';

type StatusOption = {
    value: AttendanceStatus;
    label: string;
};

type AttendanceRow = {
    id: number;
    source: string;
    registered_at: string | null;
    participant: {
        id: number | null;
        full_name: string | null;
        email: string | null;
        contact_number: string | null;
    };
    attendance: {
        status: AttendanceStatus;
        status_label: string;
        time: string | null;
        notes: string | null;
    } | null;
};

type ServerField = {
    id: number;
    label: string;
    field_type: string;
    options: string[];
    is_required: boolean;
};

type EventData = {
    id: number;
    event_name: string;
    status: string;
    status_label: string;
    event_date: string | null;
    venue: string | null;
    common_field_requirements: {
        email: boolean;
        contact_number: boolean;
        address: boolean;
    };
    registration_fields: ServerField[];
};

type Props = {
    event: EventData;
    rows: AttendanceRow[];
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    counts: Record<AttendanceFilter, number>;
    filters: {
        q: string;
        filter: AttendanceFilter;
    };
    can_mark: boolean;
    is_admin: boolean;
    force_open: boolean;
    attendance_statuses: StatusOption[];
};

const STATUS_STYLES: Record<AttendanceStatus, string> = {
    present: 'bg-green-500/15 text-green-400 border-green-500/30',
    late: 'bg-yellow-500/15 text-yellow-500 border-yellow-500/30',
    absent: 'bg-destructive/15 text-destructive border-destructive/30',
    excused: 'bg-blue-500/15 text-blue-400 border-blue-500/30',
};

const FILTER_LABELS: Record<AttendanceFilter, string> = {
    all: 'all registrations',
    unmarked: 'unmarked',
    present: 'present',
    absent: 'absent',
    late: 'late',
    excused: 'excused',
};

const MAX_BULK_ROWS = 100;

function sourceLabel(source: string): string {
    return source === 'walk_in'
        ? 'Walk-in'
        : source === 'paper'
          ? 'Paper'
          : 'Form';
}

export default function EventsAttendance({
    event,
    rows,
    pagination,
    counts,
    filters,
    can_mark,
    is_admin,
    force_open,
    attendance_statuses,
}: Props) {
    const [localSearch, setLocalSearch] = useState(filters.q);
    const [marking, setMarking] = useState<number | null>(null);
    const [walkInOpen, setWalkInOpen] = useState(false);
    const [mode, setMode] = useState<PageMode>('mark');
    const [selected, setSelected] = useState<Set<number>>(new Set());
    const [confirm, setConfirm] = useState<{
        shape: 'selected' | 'all';
        count: number;
    } | null>(null);
    const [bulkWorking, setBulkWorking] = useState(false);

    // Bulk walk-in rows. Starts with a single empty row so the table
    // has something to render on first entry.
    const [bulkRows, setBulkRows] = useState<BulkWalkInRow[]>([
        makeBulkWalkInRow(),
    ]);
    const [bulkErrors, setBulkErrors] = useState<Record<string, string>>({});
    const [bulkSubmitting, setBulkSubmitting] = useState(false);

    const baseUrl = `/events/${event.id}/attendance`;

    const sharedQuery = (overrides: Record<string, string | number> = {}) => ({
        ...(localSearch !== '' ? { q: localSearch } : {}),
        ...(filters.filter !== 'all' ? { filter: filters.filter } : {}),
        ...(force_open ? { force_open: 1 } : {}),
        ...overrides,
    });

    useEffect(() => {
        setSelected(new Set());
    }, [rows]);

    useEffect(() => {
        const timer = setTimeout(() => {
            if (localSearch === filters.q) {
                return;
            }

            router.get(baseUrl, sharedQuery(), {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ['rows', 'pagination', 'counts', 'filters'],
            });
        }, 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [localSearch]);

    const mark = (registrationId: number, status: AttendanceStatus) => {
        setMarking(registrationId);
        router.post(
            `${baseUrl}/${registrationId}/mark`,
            {
                status,
                force_open: force_open ? 1 : 0,
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['rows', 'counts'],
                onFinish: () => setMarking(null),
            },
        );
    };

    const toggleForceOpen = () => {
        router.get(
            baseUrl,
            sharedQuery(force_open ? {} : { force_open: 1 }),
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ['can_mark', 'force_open'],
            },
        );
    };

    const toggleRow = (id: number) => {
        setSelected((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    const toggleAllOnPage = () => {
        const allOnPage = rows.map((r) => r.id);
        const allSelected = allOnPage.every((id) => selected.has(id));

        setSelected((prev) => {
            const next = new Set(prev);
            if (allSelected) {
                allOnPage.forEach((id) => next.delete(id));
            } else {
                allOnPage.forEach((id) => next.add(id));
            }
            return next;
        });
    };

    const requestMarkSelected = () => {
        if (selected.size === 0) return;
        setConfirm({ shape: 'selected', count: selected.size });
    };

    const requestMarkAllVisible = () => {
        const count = counts[filters.filter] ?? 0;
        if (count === 0) return;
        setConfirm({ shape: 'all', count });
    };

    const runBulkMark = () => {
        if (confirm === null) return;
        setBulkWorking(true);

        const payload: Record<string, string | number | boolean | number[]> = {
            force_open: force_open ? 1 : 0,
        };

        if (confirm.shape === 'selected') {
            payload.registration_ids = Array.from(selected);
        } else {
            payload.mark_all_visible = true;
            payload.filter = filters.filter;
            payload.q = localSearch;
        }

        router.post(`${baseUrl}/bulk-mark`, payload, {
            preserveScroll: true,
            preserveState: true,
            only: ['rows', 'counts'],
            onSuccess: () => {
                setSelected(new Set());
                setConfirm(null);
            },
            onFinish: () => setBulkWorking(false),
        });
    };

    const submitBulkRows = () => {
        // Strip empty trailing rows before validation. If the operator
        // added a row and left it blank, they do not want an error —
        // they want the empty row ignored.
        const nonEmpty = bulkRows.filter((r) =>
            r.first_name.trim() !== '' ||
            r.last_name.trim() !== '' ||
            r.age !== '' ||
            r.email.trim() !== '' ||
            r.contact_number.trim() !== '' ||
            r.address.trim() !== '' ||
            Object.keys(r.responses).some(
                (k) => r.responses[Number(k)] !== '' &&
                       (Array.isArray(r.responses[Number(k)])
                           ? (r.responses[Number(k)] as string[]).length > 0
                           : true),
            ),
        );

        if (nonEmpty.length === 0) {
            setBulkErrors({
                rows: 'Add at least one row before submitting.',
            });
            return;
        }

        const { errors } = validateBulkWalkInRows(
            nonEmpty,
            event.registration_fields,
            event.common_field_requirements,
        );

        setBulkErrors(errors);

        if (Object.keys(errors).length > 0) {
            return;
        }

        setBulkSubmitting(true);

        router.post(
            `${baseUrl}/bulk-walk-in`,
            {
                rows: nonEmpty,
                force_open: force_open ? 1 : 0,
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['rows', 'counts'],
                onError: (serverErrors) => {
                    // Server returned per-row business errors. Keep the
                    // rows so the operator can fix the failed ones.
                    // Successful rows already persisted and their
                    // client_uuid is cached — resubmitting them is a
                    // no-op.
                    setBulkErrors(serverErrors as Record<string, string>);
                },
                onSuccess: () => {
                    // All rows accepted (or idempotently skipped).
                    // Reset to a single empty row for the next batch.
                    setBulkRows([makeBulkWalkInRow()]);
                    setBulkErrors({});
                },
                onFinish: () => setBulkSubmitting(false),
            },
        );
    };

    const otherStatuses = attendance_statuses.filter(
        (s) => s.value !== 'present',
    );

    const showForceToggle = is_admin && (!can_mark || force_open);
    const isConfirmMode = mode === 'confirm';
    const isBatchMode = mode === 'batch';

    const allOnPageSelected =
        rows.length > 0 && rows.every((r) => selected.has(r.id));
    const someOnPageSelected =
        ! allOnPageSelected && rows.some((r) => selected.has(r.id));

    return (
        <>
            <Head title={`Attendance — ${event.event_name}`} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6">
                <Link
                    href={`/events/${event.id}`}
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Event
                </Link>

                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Attendance
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {event.event_name}
                            {event.event_date && ` · ${event.event_date}`}
                            {event.venue && ` · ${event.venue}`}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {showForceToggle && (
                            <Button
                                type="button"
                                size="sm"
                                variant={force_open ? 'default' : 'outline'}
                                onClick={toggleForceOpen}
                                className={
                                    force_open
                                        ? 'bg-amber-500/20 text-amber-300 hover:bg-amber-500/30'
                                        : ''
                                }
                            >
                                {force_open ? (
                                    <>
                                        <Lock className="mr-2 h-3.5 w-3.5" />
                                        Force close
                                    </>
                                ) : (
                                    <>
                                        <Unlock className="mr-2 h-3.5 w-3.5" />
                                        Force open
                                    </>
                                )}
                            </Button>
                        )}
                        <span className="inline-flex w-fit rounded-full bg-secondary px-2.5 py-1 text-xs font-medium text-foreground">
                            {event.status_label}
                        </span>
                    </div>
                </div>

                {force_open && (
                    <div className="flex items-start gap-3 rounded-xl border border-amber-500/30 bg-amber-500/5 p-4">
                        <Unlock className="mt-0.5 h-4 w-4 shrink-0 text-amber-400" />
                        <p className="text-sm text-amber-200">
                            Admin override active. Marking is enabled
                            outside the normal attendance window.
                        </p>
                    </div>
                )}

                {!can_mark && (
                    <div className="glass-panel flex items-start gap-3 rounded-xl p-4">
                        <Clock className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            This event is closed for attendance recording.
                            You can review the final attendance list, but new
                            marks cannot be added.
                        </p>
                    </div>
                )}

                {/* Mode toggle — Mark is the default. Confirm adds a
                    checkbox column for bulk mark. Batch opens the
                    multi-row walk-in table. */}
                {can_mark && (
                    <div className="flex items-center gap-3">
                        <div className="inline-flex rounded-lg border border-white/10 bg-white/[0.02] p-0.5">
                            <button
                                type="button"
                                onClick={() => setMode('mark')}
                                className={
                                    mode === 'mark'
                                        ? 'rounded-md bg-white/10 px-3 py-1.5 text-xs font-medium text-foreground'
                                        : 'rounded-md px-3 py-1.5 text-xs font-medium text-muted-foreground hover:text-foreground'
                                }
                            >
                                Mark
                            </button>
                            <button
                                type="button"
                                onClick={() => setMode('confirm')}
                                className={
                                    mode === 'confirm'
                                        ? 'rounded-md bg-white/10 px-3 py-1.5 text-xs font-medium text-foreground'
                                        : 'rounded-md px-3 py-1.5 text-xs font-medium text-muted-foreground hover:text-foreground'
                                }
                            >
                                Confirm
                            </button>
                            <button
                                type="button"
                                onClick={() => setMode('batch')}
                                className={
                                    mode === 'batch'
                                        ? 'rounded-md bg-white/10 px-3 py-1.5 text-xs font-medium text-foreground'
                                        : 'rounded-md px-3 py-1.5 text-xs font-medium text-muted-foreground hover:text-foreground'
                                }
                            >
                                Batch
                            </button>
                        </div>
                        <span className="text-xs text-muted-foreground">
                            {isConfirmMode
                                ? 'Select rows and mark as present in bulk.'
                                : isBatchMode
                                  ? 'Transcribe a stack of paper forms into the system.'
                                  : 'Mark participants one at a time.'}
                        </span>
                    </div>
                )}

                {/* Batch mode replaces the entire list view */}
                {isBatchMode ? (
                    <div className="glass-panel flex flex-col gap-4 rounded-xl p-6">
                        <div className="flex flex-col gap-1">
                            <h2 className="text-sm font-semibold text-foreground">
                                Bulk walk-in entry
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Each row is registered as a paper submission
                                and marked present. Already-processed rows
                                are skipped on resubmit.
                            </p>
                        </div>

                        {bulkErrors.rows && (
                            <div className="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
                                {bulkErrors.rows}
                            </div>
                        )}

                        <BulkWalkInTable
                            rows={bulkRows}
                            fields={event.registration_fields}
                            requirements={event.common_field_requirements}
                            errors={bulkErrors}
                            onChange={setBulkRows}
                            disabled={bulkSubmitting}
                            maxRows={MAX_BULK_ROWS}
                        />

                        <div className="flex flex-col gap-2 border-t border-white/5 pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-xs text-muted-foreground">
                                All rows are marked present. To skip a row,
                                remove it before submitting.
                            </p>
                            <Button
                                type="button"
                                onClick={submitBulkRows}
                                disabled={bulkSubmitting}
                                className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                            >
                                <Save className="mr-2 h-4 w-4" />
                                {bulkSubmitting
                                    ? 'Saving…'
                                    : 'Save batch'}
                            </Button>
                        </div>
                    </div>
                ) : (
                    <div className="glass-panel flex flex-col gap-4 rounded-xl p-6">
                        {/* Toolbar */}
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="relative w-full sm:max-w-xs">
                                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={localSearch}
                                    onChange={(e) =>
                                        setLocalSearch(e.target.value)
                                    }
                                    placeholder="Search name, email, phone..."
                                    className="pl-9"
                                />
                            </div>

                            {can_mark && ! isConfirmMode && (
                                <Button
                                    type="button"
                                    onClick={() => setWalkInOpen(true)}
                                    className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Walk-In
                                </Button>
                            )}
                        </div>

                        {/* Filter chips */}
                        <AttendanceFilterChips
                            active={filters.filter}
                            counts={counts}
                            baseUrl={baseUrl}
                            search={localSearch}
                        />

                        {/* Confirm-mode bulk toolbar */}
                        {can_mark && isConfirmMode && (
                            <BulkMarkToolbar
                                selectedCount={selected.size}
                                filterCount={counts[filters.filter] ?? 0}
                                filterLabel={FILTER_LABELS[filters.filter]}
                                disabled={bulkWorking}
                                onMarkSelected={requestMarkSelected}
                                onMarkAllVisible={requestMarkAllVisible}
                            />
                        )}

                        {/* Table or empty states */}
                        {rows.length === 0 ? (
                            <EmptyState
                                hasSearch={localSearch !== ''}
                                hasFilter={filters.filter !== 'all'}
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-white/5 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                            {isConfirmMode && (
                                                <th className="w-10 pb-3 pr-3">
                                                    <Checkbox
                                                        checked={
                                                            allOnPageSelected
                                                                ? true
                                                                : someOnPageSelected
                                                                  ? 'indeterminate'
                                                                  : false
                                                        }
                                                        onCheckedChange={
                                                            toggleAllOnPage
                                                        }
                                                        aria-label="Select all on this page"
                                                    />
                                                </th>
                                            )}
                                            <th className="pb-3 pr-4">
                                                Participant
                                            </th>
                                            <th className="pb-3 pr-4">
                                                Contact
                                            </th>
                                            <th className="pb-3 pr-4">
                                                Source
                                            </th>
                                            <th className="pb-3 pr-4">
                                                Status
                                            </th>
                                            <th className="pb-3 text-right">
                                                {can_mark ? 'Mark as' : ''}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((row) => {
                                            const isPresent =
                                                row.attendance?.status ===
                                                'present';
                                            const isMarking =
                                                marking === row.id;
                                            const isSelected =
                                                selected.has(row.id);

                                            return (
                                                <tr
                                                    key={row.id}
                                                    className="border-b border-white/5 last:border-b-0"
                                                >
                                                    {isConfirmMode && (
                                                        <td className="py-3 pr-3">
                                                            <Checkbox
                                                                checked={
                                                                    isSelected
                                                                }
                                                                onCheckedChange={() =>
                                                                    toggleRow(
                                                                        row.id,
                                                                    )
                                                                }
                                                                aria-label={`Select ${row.participant.full_name ?? 'participant'}`}
                                                            />
                                                        </td>
                                                    )}
                                                    <td className="py-3 pr-4">
                                                        <div className="font-medium text-foreground">
                                                            {row.participant
                                                                .full_name ??
                                                                '—'}
                                                        </div>
                                                        {row.participant
                                                            .email && (
                                                            <div className="text-xs text-muted-foreground">
                                                                {
                                                                    row
                                                                        .participant
                                                                        .email
                                                                }
                                                            </div>
                                                        )}
                                                    </td>
                                                    <td className="py-3 pr-4 text-muted-foreground">
                                                        {row.participant
                                                            .contact_number ??
                                                            '—'}
                                                    </td>
                                                    <td className="py-3 pr-4 text-muted-foreground">
                                                        {sourceLabel(
                                                            row.source,
                                                        )}
                                                    </td>
                                                    <td className="py-3 pr-4">
                                                        {row.attendance ? (
                                                            <span
                                                                className={`inline-flex rounded-full border px-2 py-0.5 text-xs font-medium ${
                                                                    STATUS_STYLES[
                                                                        row
                                                                            .attendance
                                                                            .status
                                                                    ]
                                                                }`}
                                                            >
                                                                {
                                                                    row
                                                                        .attendance
                                                                        .status_label
                                                                }
                                                            </span>
                                                        ) : (
                                                            <span className="text-xs italic text-muted-foreground/60">
                                                                Not marked
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="py-3 text-right">
                                                        {can_mark && (
                                                            <div className="flex items-center justify-end gap-1">
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant={
                                                                        isPresent
                                                                            ? 'default'
                                                                            : 'outline'
                                                                    }
                                                                    onClick={() =>
                                                                        mark(
                                                                            row.id,
                                                                            'present',
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        isMarking ||
                                                                        isPresent
                                                                    }
                                                                    className={
                                                                        isPresent
                                                                            ? 'bg-green-500/20 text-green-400'
                                                                            : 'hover:border-green-500 hover:bg-green-500 hover:text-navy-900'
                                                                    }
                                                                >
                                                                    <Check className="mr-1 h-3.5 w-3.5" />
                                                                    Present
                                                                </Button>

                                                                <DropdownMenu>
                                                                    <DropdownMenuTrigger
                                                                        asChild
                                                                    >
                                                                        <Button
                                                                            type="button"
                                                                            size="sm"
                                                                            variant="ghost"
                                                                            disabled={
                                                                                isMarking
                                                                            }
                                                                            className="h-8 w-8 p-0"
                                                                        >
                                                                            <MoreHorizontal className="h-4 w-4" />
                                                                        </Button>
                                                                    </DropdownMenuTrigger>
                                                                    <DropdownMenuContent align="end">
                                                                        {otherStatuses.map(
                                                                            (
                                                                                s,
                                                                            ) => (
                                                                                <DropdownMenuItem
                                                                                    key={
                                                                                        s.value
                                                                                    }
                                                                                    onSelect={() =>
                                                                                        mark(
                                                                                            row.id,
                                                                                            s.value,
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        s.label
                                                                                    }
                                                                                </DropdownMenuItem>
                                                                            ),
                                                                        )}
                                                                    </DropdownMenuContent>
                                                                </DropdownMenu>
                                                            </div>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        <Pagination
                            currentPage={pagination.current_page}
                            lastPage={pagination.last_page}
                            perPage={pagination.per_page}
                            total={pagination.total}
                            baseUrl={baseUrl}
                            query={sharedQuery()}
                        />
                    </div>
                )}
            </div>

            <WalkInDialog
                eventId={event.id}
                open={walkInOpen}
                onOpenChange={setWalkInOpen}
                requirements={event.common_field_requirements}
                fields={event.registration_fields}
                forceOpen={force_open}
            />

            {/* Bulk mark confirmation */}
            <Dialog
                open={confirm !== null}
                onOpenChange={(open) => {
                    if (! open) setConfirm(null);
                }}
            >
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <CheckCircle2 className="h-4 w-4 text-lime-brand" />
                            Confirm bulk mark
                        </DialogTitle>
                        <DialogDescription>
                            {confirm?.shape === 'selected'
                                ? `Mark ${confirm.count} selected ${confirm.count === 1 ? 'participant' : 'participants'} as present?`
                                : `Mark all ${confirm?.count ?? 0} registrations matching the current filter as present?`}
                        </DialogDescription>
                    </DialogHeader>

                    <p className="text-xs text-muted-foreground">
                        Already-present rows are unaffected. This action is
                        idempotent — rerunning has no additional effect.
                    </p>

                    <DialogFooter className="gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setConfirm(null)}
                            disabled={bulkWorking}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            onClick={runBulkMark}
                            disabled={bulkWorking}
                            className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                        >
                            {bulkWorking
                                ? 'Marking…'
                                : `Mark ${confirm?.count ?? 0} as present`}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function EmptyState({
    hasSearch,
    hasFilter,
}: {
    hasSearch: boolean;
    hasFilter: boolean;
}) {
    if (hasSearch || hasFilter) {
        return (
            <div className="flex flex-col items-center justify-center py-16 text-center">
                <Search className="h-8 w-8 text-muted-foreground/40" />
                <p className="mt-3 text-sm text-foreground">
                    No submissions match this filter.
                </p>
                <p className="mt-1 text-xs text-muted-foreground">
                    Try a different search or filter.
                </p>
            </div>
        );
    }

    return (
        <div className="flex flex-col items-center justify-center py-16 text-center">
            <UserCheck className="h-10 w-10 text-muted-foreground/40" />
            <p className="mt-3 text-sm text-foreground">
                No registrations yet.
            </p>
            <p className="mt-1 text-xs text-muted-foreground">
                Participants will appear here once registration opens and
                responses come in.
            </p>
        </div>
    );
}

EventsAttendance.layout = {
    breadcrumbs: [
        { title: 'Events', href: '/events' },
        { title: 'Attendance', href: '#' },
    ],
};
