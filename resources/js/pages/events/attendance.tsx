import PaperRegistrationForm from '@/components/paper-registration-form';
import CheckInCamera from '@/components/check-in-camera';
import QuickWalkIn from '@/components/quick-walk-in';
import { Head, Link, router, usePoll } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Clock,
    Plus,
    Search,
    Ticket,
    Unlock,
    Lock,
    UserCheck,
    MoreHorizontal,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import AttendanceFilterChips, {
    type AttendanceFilter,
} from '@/components/attendance-filter-chips';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';

type AttendanceStatus = 'present' | 'absent';

type PageMode = 'check-in' | 'walk-in';

type StatusOption = {
    value: AttendanceStatus;
    label: string;
};

type AttendanceRow = {
    id: number;
    source: string;
    registered_at: string | null;
    attendance_code: string | null;
    tshirt_choice?: string | null;
    custom_fields?: { label: string; value: string | string[] }[];
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
        verified_at: string | null;
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
    form_version: number;
    tshirt: { label: string; choices: string[]; required: boolean } | null;
};

type Props = {
    event: EventData;
    urls: {
        monitor: string | null;
        report: string | null;
    };
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
        code: string;
    };
    check_in_summary: {
        total: number;
        present: number;
        recent: { id: number; name: string; time: string | null }[];
    };
    can_mark: boolean;
    is_admin: boolean;
    force_open: boolean;
    attendance_statuses: StatusOption[];
};

const STATUS_STYLES: Record<AttendanceStatus, string> = {
    present:
        'bg-green-500/15 text-green-700 border-green-500/30 dark:text-green-400',
    absent: 'bg-destructive/15 text-destructive border-destructive/30',
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
    urls,
    rows,
    pagination,
    counts,
    filters,
    can_mark,
    check_in_summary,
    is_admin,
    force_open,
    attendance_statuses,
}: Props) {
    const [localSearch, setLocalSearch] = useState(filters.q);
    const [localCode, setLocalCode] = useState(filters.code);
    const codeInput = useRef<HTMLInputElement>(null);
    const [checkInMessage, setCheckInMessage] = useState('');
    const [marking, setMarking] = useState<number | null>(null);
    const [mode, setMode] = useState<PageMode>('check-in');
    const [walkInMode, setWalkInMode] = useState<'quick' | 'paper'>('quick');

    usePoll(10000, { only: ['check_in_summary'] });

    const baseUrl = `/events/${event.id}/attendance`;
    const codeActive = filters.code !== '';

    const sharedQuery = (overrides: Record<string, string | number> = {}) => ({
        ...(codeActive ? { code: filters.code } : {}),
        ...(!codeActive && localSearch !== '' ? { q: localSearch } : {}),
        ...(!codeActive && filters.filter !== 'all'
            ? { filter: filters.filter }
            : {}),
        ...(force_open ? { force_open: 1 } : {}),
        ...overrides,
    });

    // The server returns the canonical code (uppercase, dashes
    // reinstated). Sync the input to it after each lookup so the
    // volunteer sees the normalized form.
    useEffect(() => {
        setLocalCode(filters.code);
    }, [filters.code]);

    useEffect(() => {
        if (codeActive) {
            return;
        }

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
    }, [localSearch, codeActive]);

    const lookupCode = (code: string) => {
        setCheckInMessage('');
        const trimmed = code.trim();
        if (trimmed === '') return;

        // The code takes precedence over search and filter on the
        // server. Send only the code (plus force_open for admins) —
        // the existing q and filter are dropped from the URL.
        router.get(
            baseUrl,
            {
                code: trimmed,
                ...(force_open ? { force_open: 1 } : {}),
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ['rows', 'pagination', 'counts', 'filters'],
            },
        );
    };

    const submitCode = (e: React.FormEvent) => {
        e.preventDefault();
        lookupCode(localCode);
    };

    const clearCode = () => {
        setLocalCode('');
        router.get(baseUrl, force_open ? { force_open: 1 } : {}, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['rows', 'pagination', 'counts', 'filters'],
        });
    };

    /**
     * Mark a single registration.
     *
     * When a code is active and the status being set is a presence
     * state (Present or Late), the lookup code is passed through as
     * `verified_code`. The server checks it against the registration's
     * stored attendance_code and, on match, stamps `verified_at`.
     *
     * For absence statuses (Absent, Excused) the code is not sent —
     * the server would ignore it anyway (a code proves presence, not
     * absence), and omitting it keeps the payload honest about intent.
     */
    const mark = (registrationId: number, status: AttendanceStatus) => {
        setMarking(registrationId);

        const payload: Record<string, string | number> = {
            status,
            force_open: force_open ? 1 : 0,
        };

        const isPresenceStatus = status === 'present';

        if (codeActive && isPresenceStatus) {
            payload.verified_code = filters.code;
        }

        router.post(`${baseUrl}/${registrationId}/mark`, payload, {
            preserveScroll: true,
            preserveState: true,
            only: ['rows', 'counts', 'check_in_summary'],
            onSuccess: () => {
                if (codeActive && isPresenceStatus) {
                    setCheckInMessage(
                        'Checked in successfully. Ready for the next participant.',
                    );
                    clearCode();
                    requestAnimationFrame(() => codeInput.current?.focus());
                }
            },
            onFinish: () => setMarking(null),
        });
    };

    const toggleForceOpen = () => {
        router.get(
            baseUrl,
            sharedQuery(force_open ? { force_open: 0 } : { force_open: 1 }),
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ['can_mark', 'force_open'],
            },
        );
    };

    const otherStatuses = attendance_statuses.filter(
        (s) => s.value !== 'present',
    );

    const showForceToggle = is_admin && (!can_mark || force_open);
    const isWalkInMode = mode === 'walk-in';

    return (
        <>
            <Head title={`Attendance — ${event.event_name}`} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6">
                <Link
                    href={`/events/${event.id}`}
                    className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-2 text-sm transition-colors"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Event
                </Link>

                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold">
                            Attendance
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {event.event_name}
                            {event.event_date && ` · ${event.event_date}`}
                            {event.venue && ` · ${event.venue}`}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {urls.monitor && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={urls.monitor}>Registrations</Link>
                            </Button>
                        )}
                        {urls.report && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={urls.report}>Report</Link>
                            </Button>
                        )}
                        {showForceToggle && (
                            <Button
                                type="button"
                                size="sm"
                                variant={force_open ? 'default' : 'outline'}
                                onClick={toggleForceOpen}
                                className={
                                    force_open
                                        ? 'bg-[#13253B]/20 text-[#13253B] hover:bg-[#13253B]/30 dark:bg-amber-500/20 dark:text-amber-300 dark:hover:bg-amber-500/30'
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
                        <span className="bg-secondary text-foreground inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-medium">
                            {event.status_label}
                        </span>
                    </div>
                </div>

                {force_open && (
                    <div className="flex items-start gap-3 rounded-xl border border-[#13253B]/30 bg-[#13253B]/5 p-4 dark:border-amber-500/30 dark:bg-amber-500/5">
                        <Unlock className="mt-0.5 h-4 w-4 shrink-0 text-[#13253B] dark:text-amber-400" />
                        <p className="text-sm text-[#13253B] dark:text-amber-200">
                            Admin override active. Marking is enabled outside
                            the normal attendance window.
                        </p>
                    </div>
                )}

                {!can_mark && (
                    <div className="glass-panel flex items-start gap-3 rounded-xl p-4">
                        <Clock className="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
                        <p className="text-muted-foreground text-sm">
                            This event is closed for attendance recording. You
                            can review the final attendance list, but new marks
                            cannot be added.
                        </p>
                    </div>
                )}

                <aside
                    className="grid gap-4 rounded-xl border p-4 sm:grid-cols-2"
                    aria-label="Live check-in summary"
                >
                    <div>
                        <h2 className="text-sm font-medium">Checked in</h2>
                        <p className="text-3xl font-semibold">
                            {check_in_summary.present} /{' '}
                            {check_in_summary.total}
                        </p>
                        <p className="text-muted-foreground text-xs">
                            Updates every 10 seconds
                        </p>
                    </div>
                    <div>
                        <h2 className="mb-2 text-sm font-medium">
                            Recent check-ins
                        </h2>
                        <ul className="space-y-1 text-sm">
                            {check_in_summary.recent.map((p) => (
                                <li
                                    key={p.id}
                                    className="flex justify-between gap-2"
                                >
                                    <span>{p.name}</span>
                                    <time className="text-muted-foreground">
                                        {p.time
                                            ? new Date(
                                                  p.time,
                                              ).toLocaleTimeString([], {
                                                  hour: '2-digit',
                                                  minute: '2-digit',
                                              })
                                            : '—'}
                                    </time>
                                </li>
                            ))}
                        </ul>
                        {!check_in_summary.recent.length && (
                            <p className="text-muted-foreground text-sm">
                                No check-ins yet.
                            </p>
                        )}
                    </div>
                </aside>
                {/* Check-in and Walk-in are the two attendance entry points. */}
                {can_mark && (
                    <div className="flex items-center gap-3">
                        <div className="inline-flex rounded-lg border border-white/10 bg-white/[0.02] p-0.5">
                            <button
                                type="button"
                                onClick={() => setMode('check-in')}
                                className={
                                    mode === 'check-in'
                                        ? 'text-foreground rounded-md bg-white/10 px-3 py-1.5 text-xs font-medium'
                                        : 'text-muted-foreground hover:text-foreground rounded-md px-3 py-1.5 text-xs font-medium'
                                }
                            >
                                Check-in
                            </button>
                            <button
                                type="button"
                                onClick={() => setMode('walk-in')}
                                className={
                                    mode === 'walk-in'
                                        ? 'text-foreground rounded-md bg-white/10 px-3 py-1.5 text-xs font-medium'
                                        : 'text-muted-foreground hover:text-foreground rounded-md px-3 py-1.5 text-xs font-medium'
                                }
                            >
                                Walk-in
                            </button>
                        </div>
                        <span className="text-muted-foreground text-xs">
                            {isWalkInMode
                                ? 'Add a quick walk-in or encode paper forms.'
                                : 'Mark participants one at a time.'}
                        </span>
                    </div>
                )}

                {isWalkInMode && (
                    <div className="flex gap-2">
                        <Button
                            variant={
                                walkInMode === 'quick' ? 'default' : 'outline'
                            }
                            onClick={() => setWalkInMode('quick')}
                        >
                            Quick walk-in
                        </Button>
                        <Button
                            variant={
                                walkInMode === 'paper' ? 'default' : 'outline'
                            }
                            onClick={() => setWalkInMode('paper')}
                        >
                            Encode paper forms
                        </Button>
                    </div>
                )}
                {isWalkInMode && walkInMode === 'quick' && (
                    <div className="glass-panel rounded-xl p-6">
                        <QuickWalkIn
                            eventId={event.id}
                            forceOpen={force_open}
                        />
                    </div>
                )}
                {isWalkInMode &&
                walkInMode === 'quick' ? null : isWalkInMode ? (
                    <div className="glass-panel rounded-xl p-6">
                        <PaperRegistrationForm
                            eventId={event.id}
                            forceOpen={force_open}
                            version={event.form_version}
                            fields={event.registration_fields}
                            tshirt={event.tshirt}
                        />
                    </div>
                ) : (
                    <div className="glass-panel flex flex-col gap-4 rounded-xl p-6">
                        {/* Attendance-code lookup. The volunteer types
                            the code the participant speaks; the code
                            narrows the list to the matching row. Search
                            and filters are disabled while a code is
                            active — the code is a lookup, not a filter. */}
                        <form
                            onSubmit={submitCode}
                            className="flex flex-col gap-2 sm:flex-row sm:items-center"
                        >
                            <div className="relative w-full sm:max-w-xs">
                                <Ticket className="text-lime-brand pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                <Input
                                    ref={codeInput}
                                    autoFocus
                                    value={localCode}
                                    onChange={(e) =>
                                        setLocalCode(e.target.value)
                                    }
                                    placeholder="KM4X-P2N7"
                                    autoComplete="off"
                                    maxLength={12}
                                    aria-label="Attendance code"
                                    className="h-14 pl-9 font-mono text-xl tracking-wider uppercase"
                                />
                            </div>
                            <CheckInCamera
                                onCode={(code) => {
                                    setLocalCode(code);
                                    lookupCode(code);
                                }}
                            />
                            {codeActive ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={clearCode}
                                >
                                    <X className="mr-2 h-3.5 w-3.5" />
                                    Clear code
                                </Button>
                            ) : (
                                <Button
                                    type="submit"
                                    disabled={localCode.trim() === ''}
                                >
                                    Look up
                                </Button>
                            )}
                        </form>

                        {checkInMessage && (
                            <p
                                role="status"
                                className="rounded-lg border border-green-500/40 bg-green-500/10 p-4 text-green-700 dark:text-green-300"
                            >
                                {checkInMessage}
                            </p>
                        )}
                        {codeActive && rows.length === 0 && (
                            <p
                                role="alert"
                                className="border-destructive/40 text-destructive rounded-lg border p-4"
                            >
                                Code not found for this event. Check the code or
                                use walk-in entry.
                            </p>
                        )}
                        {codeActive && rows.length === 1 && (
                            <section className="border-lime-brand/40 bg-lime-brand/5 space-y-3 rounded-xl border p-5">
                                <h2 className="text-xl font-semibold">
                                    {rows[0].participant.full_name}
                                </h2>
                                <p>{event.event_name}</p>
                                {rows[0].tshirt_choice && (
                                    <p>T-shirt: {rows[0].tshirt_choice}</p>
                                )}
                                {rows[0].custom_fields?.map((field, index) => (
                                    <p key={index}>
                                        <span className="font-medium">
                                            {field.label}:{' '}
                                        </span>
                                        {Array.isArray(field.value)
                                            ? field.value.join(', ')
                                            : field.value}
                                    </p>
                                ))}
                                {rows[0].attendance?.status === 'present' && (
                                    <p role="status">Already present.</p>
                                )}
                                {can_mark && (
                                    <Button
                                        className="h-14 bg-green-700 px-10 text-lg text-white hover:bg-green-800"
                                        disabled={marking !== null}
                                        onClick={() =>
                                            mark(rows[0].id, 'present')
                                        }
                                    >
                                        {marking !== null
                                            ? 'Saving…'
                                            : rows[0].attendance?.status ===
                                                'present'
                                              ? 'Verify check-in'
                                              : 'Present'}
                                    </Button>
                                )}
                            </section>
                        )}

                        {/* Toolbar */}
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="relative w-full sm:max-w-xs">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                <Input
                                    value={localSearch}
                                    onChange={(e) =>
                                        setLocalSearch(e.target.value)
                                    }
                                    placeholder="Search name, email, phone..."
                                    className="pl-9"
                                    disabled={codeActive}
                                />
                            </div>

                            {can_mark && (
                                <Button
                                    type="button"
                                    onClick={() => {
                                        setMode('walk-in');
                                        setWalkInMode('quick');
                                    }}
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
                            disabled={codeActive}
                        />

                        {/* Table or empty states */}
                        {rows.length === 0 ? (
                            <EmptyState
                                hasSearch={localSearch !== ''}
                                hasFilter={filters.filter !== 'all'}
                                code={filters.code}
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="text-muted-foreground border-b border-white/5 text-left text-xs font-medium tracking-wider uppercase">
                                            <th className="pr-4 pb-3">
                                                Participant
                                            </th>
                                            <th className="pr-4 pb-3">
                                                Contact
                                            </th>
                                            <th className="pr-4 pb-3">
                                                Source
                                            </th>
                                            <th className="pr-4 pb-3">
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

                                            return (
                                                <tr
                                                    key={row.id}
                                                    className={`border-b border-white/5 last:border-b-0 ${
                                                        codeActive
                                                            ? 'bg-lime-brand/5'
                                                            : ''
                                                    }`}
                                                >
                                                    <td className="py-3 pr-4">
                                                        <div className="text-foreground font-medium">
                                                            {row.participant
                                                                .full_name ??
                                                                '—'}
                                                        </div>
                                                        {row.participant
                                                            .email && (
                                                            <div className="text-muted-foreground text-xs">
                                                                {
                                                                    row
                                                                        .participant
                                                                        .email
                                                                }
                                                            </div>
                                                        )}
                                                    </td>
                                                    <td className="text-muted-foreground py-3 pr-4">
                                                        {row.participant
                                                            .contact_number ??
                                                            '—'}
                                                    </td>
                                                    <td className="text-muted-foreground py-3 pr-4">
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
                                                            <span className="text-muted-foreground/60 text-xs italic">
                                                                Absent
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
                                                                            ? 'bg-green-500/20 text-green-700 dark:text-green-400'
                                                                            : 'hover:text-navy-900 hover:border-green-500 hover:bg-green-500'
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
        </>
    );
}

function EmptyState({
    hasSearch,
    hasFilter,
    code,
}: {
    hasSearch: boolean;
    hasFilter: boolean;
    code: string;
}) {
    if (code !== '') {
        return (
            <div className="flex flex-col items-center justify-center py-16 text-center">
                <Ticket className="text-muted-foreground/40 h-8 w-8" />
                <p className="text-foreground mt-3 text-sm">
                    No registration matches{' '}
                    <span className="font-mono font-medium">{code}</span>.
                </p>
                <p className="text-muted-foreground mt-1 text-xs">
                    Check the code, or clear it and search by name.
                </p>
            </div>
        );
    }

    if (hasSearch || hasFilter) {
        return (
            <div className="flex flex-col items-center justify-center py-16 text-center">
                <Search className="text-muted-foreground/40 h-8 w-8" />
                <p className="text-foreground mt-3 text-sm">
                    No submissions match this filter.
                </p>
                <p className="text-muted-foreground mt-1 text-xs">
                    Try a different search or filter.
                </p>
            </div>
        );
    }

    return (
        <div className="flex flex-col items-center justify-center py-16 text-center">
            <UserCheck className="text-muted-foreground/40 h-10 w-10" />
            <p className="text-foreground mt-3 text-sm">
                No registrations yet.
            </p>
            <p className="text-muted-foreground mt-1 text-xs">
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
