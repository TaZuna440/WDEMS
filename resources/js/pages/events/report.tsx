import { Head, Link } from '@inertiajs/react';
import { Fragment, useMemo, useState } from 'react';
import {
    ArrowLeft,
    ChevronDown,
    ChevronRight,
    Download,
    Printer,
} from 'lucide-react';
import { Button } from '@/components/ui/button';

type EventData = {
    id: number;
    event_name: string;
    event_type: string;
    event_type_label: string;
    event_date: string | null;
    venue: string | null;
    status: string;
    status_label: string;
};

type Summary = {
    registered_form: number;
    attended_verified: number;
    attended_override: number;
    attended_form_total: number;
    walk_ins_encoded: number;
    total_attendance: number;
    show_rate: number | null;
    verification_rate: number | null;
    no_shows: number;
    status_breakdown: {
        present: number;
        late: number;
        excused: number;
        absent: number;
        unmarked: number;
    };
};

type CrossEventRow = {
    id: number;
    event_name: string;
    event_date: string;
    registered: number;
    attended: number;
    show_rate: number | null;
    delta: number | null;
    is_current: boolean;
};

type CrossEvent = {
    type_label: string;
    total_events: number;
    average_show_rate: number | null;
    best: { event_name: string; show_rate: number } | null;
    worst: { event_name: string; show_rate: number } | null;
    rows: CrossEventRow[];
};

type HistoryEntry = {
    event_name: string;
    event_date: string;
    status: string;
};

type ParticipantRow = {
    registration_id: number;
    participant_id: number;
    full_name: string;
    contact: string | null;
    source: string;
    attendance_status: string | null;
    total_appearances: number;
    last_appearance: string | null;
    history: HistoryEntry[];
};

type Props = {
    event: EventData;
    summary: Summary;
    cross_event: CrossEvent;
    participants: ParticipantRow[];
};

const STATUS_STYLES: Record<string, string> = {
    present: 'bg-green-500/15 text-green-400 border-green-500/30',
    late: 'bg-[#13253B] text-white border border-[#13253B] dark:bg-yellow-500/15 dark:text-yellow-500 dark:border-yellow-500/30',
    excused: 'bg-blue-500/15 text-blue-400 border-blue-500/30',
    absent: 'bg-destructive/15 text-destructive border-destructive/30',
};

const STATUS_LABELS: Record<string, string> = {
    present: 'Present',
    late: 'Late',
    excused: 'Excused',
    absent: 'Absent',
};

const SOURCE_LABELS: Record<string, string> = {
    form: 'Form',
    walk_in: 'Walk-in',
    paper: 'Paper',
};

/**
 * Sort priority for participant status. Present first, then Late,
 * Excused, Absent, and finally unmarked rows (null status).
 */
function statusPriority(status: string | null): number {
    switch (status) {
        case 'present':
            return 0;
        case 'late':
            return 1;
        case 'excused':
            return 2;
        case 'absent':
            return 3;
        default:
            return 4;
    }
}

function formatRate(value: number | null): string {
    return value === null ? '—' : `${value.toFixed(1)}%`;
}

function formatDelta(value: number | null): string {
    if (value === null) return '—';
    return value >= 0 ? `+${value.toFixed(1)}` : value.toFixed(1);
}

function SummaryCard({
    label,
    value,
    hint,
    accent,
}: {
    label: string;
    value: string | number;
    hint?: string;
    accent?: 'lime' | 'amber' | 'default';
}) {
    const accentClass =
        accent === 'lime'
            ? 'text-lime-brand'
            : accent === 'amber'
              ? 'text-[#13253B] dark:text-amber-400'
              : 'text-foreground';

    return (
        <div className="glass-panel rounded-xl p-5">
            <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                {label}
            </p>
            <p className={`mt-2 text-2xl font-semibold ${accentClass}`}>
                {value}
            </p>
            {hint && (
                <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
            )}
        </div>
    );
}

function SummarySection({ summary }: { summary: Summary }) {
    const breakdown = summary.status_breakdown;

    return (
        <section className="flex flex-col gap-4">
            <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                This event
            </h2>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <SummaryCard
                    label="Registered (form)"
                    value={summary.registered_form}
                />
                <SummaryCard
                    label="Attended — verified"
                    value={summary.attended_verified}
                    hint="Code presented at the table"
                    accent="lime"
                />
                <SummaryCard
                    label="Attended — override"
                    value={summary.attended_override}
                    hint="Staff marked without a code"
                    accent="amber"
                />
                <SummaryCard
                    label="Walk-ins encoded"
                    value={summary.walk_ins_encoded}
                />
                <SummaryCard
                    label="Total physical attendance"
                    value={summary.total_attendance}
                />
                <SummaryCard
                    label="No-shows"
                    value={summary.no_shows}
                    hint="Registered but did not attend"
                />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <SummaryCard
                    label="Show rate (form)"
                    value={formatRate(summary.show_rate)}
                    hint={`${summary.attended_form_total} of ${summary.registered_form} registered`}
                />
                <SummaryCard
                    label="Verification rate"
                    value={formatRate(summary.verification_rate)}
                    hint="Of those who attended, how many carried a code"
                    accent="lime"
                />
            </div>

            <div className="glass-panel rounded-xl p-5">
                <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                    Attendance breakdown
                </p>
                <div className="mt-3 flex flex-wrap gap-4">
                    {(
                        [
                            'present',
                            'late',
                            'excused',
                            'absent',
                            'unmarked',
                        ] as const
                    ).map((key) => (
                        <div
                            key={key}
                            className="flex items-baseline gap-2 text-sm"
                        >
                            <span className="text-muted-foreground">
                                {key === 'unmarked'
                                    ? 'Unmarked'
                                    : STATUS_LABELS[key]}
                            </span>
                            <span className="font-medium text-foreground">
                                {breakdown[key]}
                            </span>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

type CrossSortKey =
    | 'event_date'
    | 'event_name'
    | 'registered'
    | 'attended'
    | 'show_rate'
    | 'delta';

const DATE_COLUMNS: CrossSortKey[] = ['event_date'];

function CrossEventSection({ crossEvent }: { crossEvent: CrossEvent }) {
    const [sort, setSort] = useState<{ key: CrossSortKey; dir: 'asc' | 'desc' }>(
        { key: 'event_date', dir: 'desc' },
    );

    const sorted = useMemo(() => {
        const copy = crossEvent.rows.slice();
        const { key, dir } = sort;
        const mult = dir === 'asc' ? 1 : -1;

        copy.sort((a, b) => {
            const av = a[key];
            const bv = b[key];

            if (av === null && bv === null) return 0;
            if (av === null) return 1;
            if (bv === null) return -1;

            if (typeof av === 'number' && typeof bv === 'number') {
                return (av - bv) * mult;
            }

            return String(av).localeCompare(String(bv)) * mult;
        });

        return copy;
    }, [crossEvent.rows, sort]);

    const toggleSort = (key: CrossSortKey) => {
        setSort((prev) => {
            if (prev.key === key) {
                return { key, dir: prev.dir === 'asc' ? 'desc' : 'asc' };
            }

            const dir = DATE_COLUMNS.includes(key) ? 'desc' : 'asc';
            return { key, dir };
        });
    };

    const header = (key: CrossSortKey, label: string) => (
        <th
            className="cursor-pointer select-none pb-3 pr-4 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground hover:text-foreground print:cursor-auto"
            onClick={() => toggleSort(key)}
        >
            {label}
            {sort.key === key && (
                <span className="ml-1 text-[10px]">
                    {sort.dir === 'asc' ? '▲' : '▼'}
                </span>
            )}
        </th>
    );

    return (
        <section className="flex flex-col gap-4">
            <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                Cross-event by type
            </h2>

            <p className="text-sm text-muted-foreground">
                {crossEvent.type_label}: {crossEvent.total_events}{' '}
                {crossEvent.total_events === 1 ? 'event' : 'events'}
                {crossEvent.average_show_rate !== null && (
                    <>
                        {' · '}Avg show rate:{' '}
                        {crossEvent.average_show_rate.toFixed(1)}%
                    </>
                )}
                {crossEvent.best && (
                    <>
                        {' · '}Best: {crossEvent.best.event_name} (
                        {crossEvent.best.show_rate.toFixed(1)}%)
                    </>
                )}
                {crossEvent.worst && (
                    <>
                        {' · '}Worst: {crossEvent.worst.event_name} (
                        {crossEvent.worst.show_rate.toFixed(1)}%)
                    </>
                )}
            </p>

            {sorted.length === 0 ? (
                <div className="glass-panel rounded-xl p-8 text-center text-sm text-muted-foreground">
                    No other events of this type yet.
                </div>
            ) : (
                <div className="glass-panel overflow-hidden rounded-xl">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-white/5">
                                    {header('event_name', 'Event')}
                                    {header('event_date', 'Date')}
                                    {header('registered', 'Registered')}
                                    {header('attended', 'Attended')}
                                    {header('show_rate', 'Show rate')}
                                    {header('delta', 'Δ vs avg')}
                                </tr>
                            </thead>
                            <tbody>
                                {sorted.map((row) => (
                                    <tr
                                        key={row.id}
                                        className={`border-b border-white/5 last:border-b-0 ${
                                            row.is_current
                                                ? 'border-l-2 border-l-[#13253B]/60 bg-[#13253B]/5 dark:border-l-amber-500/60 dark:bg-amber-500/5'
                                                : ''
                                        }`}
                                    >
                                        <td className="py-3 pr-4 font-medium text-foreground">
                                            {row.event_name}
                                            {row.is_current && (
                                                <span className="ml-2 text-xs text-[#13253B] dark:text-amber-400">
                                                    (this event)
                                                </span>
                                            )}
                                        </td>
                                        <td className="py-3 pr-4 text-muted-foreground">
                                            {row.event_date}
                                        </td>
                                        <td className="py-3 pr-4 tabular-nums text-foreground">
                                            {row.registered}
                                        </td>
                                        <td className="py-3 pr-4 tabular-nums text-foreground">
                                            {row.attended}
                                        </td>
                                        <td className="py-3 pr-4 tabular-nums text-foreground">
                                            {formatRate(row.show_rate)}
                                        </td>
                                        <td
                                            className={`py-3 tabular-nums ${
                                                row.delta === null
                                                    ? 'text-muted-foreground'
                                                    : row.delta >= 0
                                                      ? 'text-green-400'
                                                      : 'text-destructive'
                                            }`}
                                        >
                                            {formatDelta(row.delta)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </section>
    );
}

type ParticipantSortKey =
    | 'full_name'
    | 'contact'
    | 'source'
    | 'attendance_status'
    | 'total_appearances'
    | 'last_appearance';

function ParticipantsSection({
    participants,
}: {
    participants: ParticipantRow[];
}) {
    const [sort, setSort] = useState<{
        key: ParticipantSortKey | null;
        dir: 'asc' | 'desc';
    }>({ key: null, dir: 'asc' });

    const [expanded, setExpanded] = useState<Set<number>>(new Set());

    const sorted = useMemo(() => {
        const copy = participants.slice();

        if (sort.key === null) {
            copy.sort((a, b) => {
                const pa = statusPriority(a.attendance_status);
                const pb = statusPriority(b.attendance_status);
                if (pa !== pb) return pa - pb;

                return a.full_name.localeCompare(b.full_name);
            });

            return copy;
        }

        const { key, dir } = sort;
        const mult = dir === 'asc' ? 1 : -1;

        copy.sort((a, b) => {
            if (key === 'attendance_status') {
                return (
                    (statusPriority(a.attendance_status) -
                        statusPriority(b.attendance_status)) *
                    mult
                );
            }

            const av = a[key];
            const bv = b[key];

            if (av === null && bv === null) return 0;
            if (av === null) return 1;
            if (bv === null) return -1;

            if (typeof av === 'number' && typeof bv === 'number') {
                return (av - bv) * mult;
            }

            return String(av).localeCompare(String(bv)) * mult;
        });

        return copy;
    }, [participants, sort]);

    const toggleSort = (key: ParticipantSortKey) => {
        setSort((prev) => {
            if (prev.key === key) {
                return { key, dir: prev.dir === 'asc' ? 'desc' : 'asc' };
            }

            const dir =
                key === 'last_appearance' || key === 'total_appearances'
                    ? 'desc'
                    : 'asc';

            return { key, dir };
        });
    };

    const toggleExpand = (participantId: number) => {
        setExpanded((prev) => {
            const next = new Set(prev);
            if (next.has(participantId)) {
                next.delete(participantId);
            } else {
                next.add(participantId);
            }
            return next;
        });
    };

    const header = (key: ParticipantSortKey, label: string) => (
        <th
            className="cursor-pointer select-none pb-3 pr-4 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground hover:text-foreground print:cursor-auto"
            onClick={() => toggleSort(key)}
        >
            {label}
            {sort.key === key && (
                <span className="ml-1 text-[10px]">
                    {sort.dir === 'asc' ? '▲' : '▼'}
                </span>
            )}
        </th>
    );

    return (
        <section className="flex flex-col gap-4">
            <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                Participants
            </h2>

            {sorted.length === 0 ? (
                <div className="glass-panel rounded-xl p-8 text-center text-sm text-muted-foreground">
                    No one is registered for this event yet.
                </div>
            ) : (
                <div className="glass-panel overflow-hidden rounded-xl">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-white/5">
                                    <th className="w-8 pb-3 print:hidden" />
                                    {header('full_name', 'Name')}
                                    {header('contact', 'Contact')}
                                    {header('source', 'Source')}
                                    {header('attendance_status', 'Attendance')}
                                    {header(
                                        'total_appearances',
                                        'Total appearances',
                                    )}
                                    {header('last_appearance', 'Last appearance')}
                                </tr>
                            </thead>
                            <tbody>
                                {sorted.map((row) => {
                                    const isExpanded = expanded.has(
                                        row.participant_id,
                                    );
                                    const hasHistory =
                                        row.history.length > 0;

                                    return (
                                        <Fragment key={row.registration_id}>
                                            <tr
                                                className="cursor-pointer border-b border-white/5 hover:bg-white/[0.02] print:cursor-auto"
                                                onClick={() =>
                                                    hasHistory &&
                                                    toggleExpand(
                                                        row.participant_id,
                                                    )
                                                }
                                            >
                                                <td className="py-3 pl-4 print:hidden">
                                                    {hasHistory ? (
                                                        isExpanded ? (
                                                            <ChevronDown className="h-3.5 w-3.5 text-muted-foreground" />
                                                        ) : (
                                                            <ChevronRight className="h-3.5 w-3.5 text-muted-foreground" />
                                                        )
                                                    ) : (
                                                        <span className="block h-3.5 w-3.5" />
                                                    )}
                                                </td>
                                                <td className="py-3 pr-4 font-medium text-foreground">
                                                    {row.full_name}
                                                </td>
                                                <td className="py-3 pr-4 text-muted-foreground">
                                                    {row.contact ?? '—'}
                                                </td>
                                                <td className="py-3 pr-4 text-muted-foreground">
                                                    {SOURCE_LABELS[row.source] ??
                                                        row.source}
                                                </td>
                                                <td className="py-3 pr-4">
                                                    {row.attendance_status ? (
                                                        <span
                                                            className={`inline-flex rounded-full border px-2 py-0.5 text-xs font-medium ${
                                                                STATUS_STYLES[
                                                                    row
                                                                        .attendance_status
                                                                ] ?? ''
                                                            }`}
                                                        >
                                                            {STATUS_LABELS[
                                                                row
                                                                    .attendance_status
                                                            ] ??
                                                                row.attendance_status}
                                                        </span>
                                                    ) : (
                                                        <span className="text-xs italic text-muted-foreground/60">
                                                            Not marked
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="py-3 pr-4 tabular-nums text-foreground">
                                                    {row.total_appearances}
                                                </td>
                                                <td className="py-3 pr-4 text-muted-foreground">
                                                    {row.last_appearance ?? '—'}
                                                </td>
                                            </tr>
                                            {isExpanded && hasHistory && (
                                                <tr className="border-b border-white/5 bg-white/[0.01]">
                                                    <td
                                                        colSpan={7}
                                                        className="px-4 py-3"
                                                    >
                                                        <p className="mb-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                                            Prior appearances in{' '}
                                                            {row.history.length}{' '}
                                                            {row.history.length ===
                                                            1
                                                                ? 'event'
                                                                : 'events'}
                                                        </p>
                                                        <ul className="flex flex-col gap-1.5 pl-4">
                                                            {row.history.map(
                                                                (h, i) => (
                                                                    <li
                                                                        key={i}
                                                                        className="flex items-center gap-3 text-xs text-muted-foreground"
                                                                    >
                                                                        <span className="w-24 shrink-0 tabular-nums">
                                                                            {
                                                                                h.event_date
                                                                            }
                                                                        </span>
                                                                        <span className="text-foreground">
                                                                            {
                                                                                h.event_name
                                                                            }
                                                                        </span>
                                                                        <span className="text-muted-foreground">
                                                                            ·{' '}
                                                                            {STATUS_LABELS[
                                                                                h
                                                                                    .status
                                                                            ] ??
                                                                                h.status}
                                                                        </span>
                                                                    </li>
                                                                ),
                                                            )}
                                                        </ul>
                                                    </td>
                                                </tr>
                                            )}
                                        </Fragment>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </section>
    );
}

export default function ReportShow({
    event,
    summary,
    cross_event,
    participants,
}: Props) {
    const isLive = event.status === 'ongoing';

    return (
        <>
            <Head title={`Report — ${event.event_name}`} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6">
                <div className="flex items-center justify-between print:hidden">
                    <Link
                        href={`/events/${event.id}`}
                        className="inline-flex w-fit items-center gap-2 text-sm text-on-image-muted transition-colors hover:text-on-image"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back to Event
                    </Link>
                    <div className="flex items-center gap-2">
                        <a
                            href={`/events/${event.id}/report/export`}
                            className="inline-flex h-8 items-center rounded-md border border-border bg-card/80 px-3 text-xs font-medium text-foreground shadow-xs backdrop-blur-sm transition-colors hover:bg-card"
                        >
                            <Download className="mr-2 h-3.5 w-3.5" />
                            Download CSV
                        </a>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => window.print()}
                        >
                            <Printer className="mr-2 h-3.5 w-3.5" />
                            Print summary
                        </Button>
                    </div>
                </div>

                <div className="flex flex-col gap-2">
                    <h1 className="text-on-image text-2xl font-semibold">
                        {event.event_name}
                    </h1>
                    <div className="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                        <span className="inline-flex rounded-full bg-secondary px-2.5 py-1 text-xs font-medium text-foreground">
                            {event.status_label}
                        </span>
                        {event.event_date && <span>{event.event_date}</span>}
                        {event.venue && <span>· {event.venue}</span>}
                        <span>· {event.event_type_label}</span>
                    </div>
                </div>

                {isLive && (
                    <div className="flex items-start gap-3 rounded-xl border border-[#13253B]/30 bg-[#13253B]/5 p-4 print:hidden dark:border-amber-500/30 dark:bg-amber-500/5">
                        <p className="text-sm text-[#13253B] dark:text-amber-200">
                            Live — numbers will change until the event is
                            finalized.
                        </p>
                    </div>
                )}

                <SummarySection summary={summary} />

                <CrossEventSection crossEvent={cross_event} />

                <ParticipantsSection participants={participants} />
            </div>
        </>
    );
}

ReportShow.layout = {
    breadcrumbs: [
        { title: 'Events', href: '/events' },
        { title: 'Report', href: '#' },
    ],
};
