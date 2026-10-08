import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Clock,
    Plus,
    Search,
    UserCheck,
    UserX,
    X,
    MoreHorizontal,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import AttendanceFilterChips, {
    type AttendanceFilter,
} from '@/components/attendance-filter-chips';
import Pagination from '@/components/pagination';
import WalkInDialog from '@/components/walk-in-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';

type AttendanceStatus = 'present' | 'late' | 'absent' | 'excused';

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
    attendance_statuses: StatusOption[];
};

const STATUS_STYLES: Record<AttendanceStatus, string> = {
    present: 'bg-green-500/15 text-green-400 border-green-500/30',
    late: 'bg-yellow-500/15 text-yellow-500 border-yellow-500/30',
    absent: 'bg-destructive/15 text-destructive border-destructive/30',
    excused: 'bg-blue-500/15 text-blue-400 border-blue-500/30',
};

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
    attendance_statuses,
}: Props) {
    const [localSearch, setLocalSearch] = useState(filters.q);
    const [marking, setMarking] = useState<number | null>(null);
    const [walkInOpen, setWalkInOpen] = useState(false);

    const baseUrl = `/events/${event.id}/attendance`;

    // Debounced search. Fires a partial reload 300ms after the user
    // stops typing. Guards against firing when the URL already
    // matches the local value (e.g. on mount).
    useEffect(() => {
        const timer = setTimeout(() => {
            if (localSearch === filters.q) {
                return;
            }

            router.get(
                baseUrl,
                {
                    ...(localSearch !== '' ? { q: localSearch } : {}),
                    ...(filters.filter !== 'all'
                        ? { filter: filters.filter }
                        : {}),
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                    only: ['rows', 'pagination', 'counts', 'filters'],
                },
            );
        }, 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [localSearch]);

    const mark = (registrationId: number, status: AttendanceStatus) => {
        setMarking(registrationId);
        router.post(
            `${baseUrl}/${registrationId}/mark`,
            { status },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['rows', 'counts'],
                onFinish: () => setMarking(null),
            },
        );
    };

    const otherStatuses = attendance_statuses.filter(
        (s) => s.value !== 'present',
    );

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
                    <span className="inline-flex w-fit rounded-full bg-secondary px-2.5 py-1 text-xs font-medium text-foreground">
                        {event.status_label}
                    </span>
                </div>

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

                        {can_mark && (
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
                                        <th className="pb-3 pr-4">
                                            Participant
                                        </th>
                                        <th className="pb-3 pr-4">Contact</th>
                                        <th className="pb-3 pr-4">Source</th>
                                        <th className="pb-3 pr-4">Status</th>
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

                                        return (
                                            <tr
                                                key={row.id}
                                                className="border-b border-white/5 last:border-b-0"
                                            >
                                                <td className="py-3 pr-4">
                                                    <div className="font-medium text-foreground">
                                                        {row.participant
                                                            .full_name ?? '—'}
                                                    </div>
                                                    {row.participant.email && (
                                                        <div className="text-xs text-muted-foreground">
                                                            {
                                                                row.participant
                                                                    .email
                                                            }
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="py-3 pr-4 text-muted-foreground">
                                                    {row.participant
                                                        .contact_number ?? '—'}
                                                </td>
                                                <td className="py-3 pr-4 text-muted-foreground">
                                                    {sourceLabel(row.source)}
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
                                                                row.attendance
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
                                                                        (s) => (
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
                        query={{
                            ...(localSearch !== ''
                                ? { q: localSearch }
                                : {}),
                            ...(filters.filter !== 'all'
                                ? { filter: filters.filter }
                                : {}),
                        }}
                    />
                </div>
            </div>

            <WalkInDialog
                eventId={event.id}
                open={walkInOpen}
                onOpenChange={setWalkInOpen}
                requirements={event.common_field_requirements}
            />
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
