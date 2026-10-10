import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Check,
    Link2,
    RefreshCw,
    UserPlus,
    UserRound,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useClipboard } from '@/hooks/use-clipboard';
import { usePolling } from '@/hooks/use-polling';

type EventData = {
    id: number;
    event_name: string;
    event_date: string | null;
    venue: string | null;
    status: string;
    status_label: string;
    registration_slug: string | null;
    registration_start: string | null;
    registration_end: string | null;
    is_open: boolean;
};

type Stats = {
    total: number;
    last_hour: number;
    last_15min: number;
};

type Participant = {
    id: number | null;
    full_name: string | null;
    email: string | null;
    contact_number: string | null;
};

type CustomField = {
    label: string | null;
    value: string | string[];
};

type RegistrationRow = {
    id: number;
    participant: Participant;
    created_at_iso: string | null;
    created_at_absolute: string | null;
    created_at_human: string | null;
    is_returning: boolean;
    other_events_count: number;
    has_shared_phone: boolean;
    custom_fields: CustomField[];
};

type Props = {
    event: EventData;
    stats: Stats;
    registrations: RegistrationRow[];
};

const POLL_KEYS = ['stats', 'registrations'] as const;
const POLL_INTERVAL_MS = 10_000;

type Filter = 'all' | 'new' | 'returning';

function secondsSince(date: Date, now: Date): number {
    return Math.max(0, Math.floor((now.getTime() - date.getTime()) / 1000));
}

function relativeSeconds(seconds: number): string {
    if (seconds < 5) return 'just now';
    if (seconds < 60) return `${seconds} seconds ago`;

    const minutes = Math.floor(seconds / 60);
    if (minutes === 1) return '1 minute ago';

    return `${minutes} minutes ago`;
}

/**
 * Copy the public registration URL to the clipboard.
 *
 * The URL is built client-side from window.location.origin so no
 * server round trip is needed. The button is only rendered when the
 * event is open and has a slug, so the origin is always available
 * when the component mounts.
 */
function PublicLinkButton({ slug }: { slug: string }) {
    const [copied, copy] = useClipboard();
    const url = `${window.location.origin}/r/${slug}`;
    const isCopied = copied === url;

    return (
        <button
            type="button"
            onClick={() => copy(url)}
            title={url}
            className="inline-flex items-center gap-1.5 rounded-md border border-lime-brand/40 bg-lime-brand/10 px-2.5 py-1 text-xs font-medium text-lime-brand transition-colors hover:bg-lime-brand/20"
        >
            {isCopied ? (
                <>
                    <Check className="h-3 w-3" />
                    Copied
                </>
            ) : (
                <>
                    <Link2 className="h-3 w-3" />
                    Copy public link
                </>
            )}
        </button>
    );
}

function StatsStrip({
    stats,
    isOpen,
}: {
    stats: Stats;
    isOpen: boolean;
}) {
    const showHour = isOpen && stats.last_hour > 0;
    const show15 = isOpen && stats.last_15min > 0;

    return (
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
            <span>
                <span className="font-semibold text-foreground">
                    {stats.total}
                </span>{' '}
                <span className="text-muted-foreground">registered</span>
            </span>
            {showHour && (
                <>
                    <span className="text-muted-foreground">·</span>
                    <span className="text-lime-brand">
                        +{stats.last_hour} in the last hour
                    </span>
                </>
            )}
            {show15 && (
                <>
                    <span className="text-muted-foreground">·</span>
                    <span className="text-lime-brand">
                        +{stats.last_15min} in the last 15 min
                    </span>
                </>
            )}
        </div>
    );
}

function Chip({
    active,
    onClick,
    label,
    count,
}: {
    active: boolean;
    onClick: () => void;
    label: string;
    count: number;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition-colors ${
                active
                    ? 'border-lime-brand/40 bg-lime-brand/15 text-lime-brand'
                    : 'border-white/10 text-muted-foreground hover:border-white/20 hover:text-foreground'
            }`}
        >
            {label}
            <span className={active ? 'text-lime-brand' : 'text-muted-foreground/60'}>
                ({count})
            </span>
        </button>
    );
}

/**
 * Render one custom field answer as a single-line string.
 *
 * Strings pass through. Arrays (checkbox responses) are joined with
 * ", ". Empty arrays render as an em-dash so the answer is never
 * blank.
 */
function renderFieldValue(value: string | string[]): string {
    if (Array.isArray(value)) {
        return value.length === 0 ? '—' : value.join(', ');
    }
    return value === '' ? '—' : value;
}

function RegistrationCard({ row }: { row: RegistrationRow }) {
    const name = row.participant.full_name ?? '—';
    const contact = row.participant.email ?? row.participant.contact_number ?? '—';
    const BadgeIcon = row.is_returning ? UserRound : UserPlus;

    return (
        <div className="glass-panel rounded-xl p-4">
            <div className="flex items-start justify-between gap-3">
                <div className="flex flex-col gap-0.5">
                    <span className="text-sm font-semibold text-foreground">
                        {name}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {contact}
                    </span>
                </div>

                <div className="flex shrink-0 items-center gap-1.5">
                    {row.has_shared_phone && (
                        <span
                            title="This phone number matches another registration."
                            className="inline-flex h-5 w-5 items-center justify-center rounded-full bg-amber-500/15"
                        >
                            <AlertTriangle className="h-3 w-3 text-amber-400" />
                        </span>
                    )}
                    <span
                        className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${
                            row.is_returning
                                ? 'bg-accent/20 text-accent'
                                : 'bg-white/5 text-muted-foreground'
                        }`}
                    >
                        <BadgeIcon className="h-3 w-3" />
                        {row.is_returning ? 'RETURNING' : 'NEW'}
                    </span>
                </div>
            </div>

            <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                {row.created_at_absolute !== null && (
                    <span>{row.created_at_absolute}</span>
                )}
                {row.created_at_human !== null && (
                    <span>· {row.created_at_human}</span>
                )}
            </div>

            {row.is_returning && row.other_events_count > 0 && (
                <p className="mt-2 text-xs text-muted-foreground">
                    Registered for {row.other_events_count}{' '}
                    {row.other_events_count === 1 ? 'previous event' : 'previous events'}
                </p>
            )}

            {row.custom_fields.length > 0 && (
                <p className="mt-2 line-clamp-2 text-xs text-muted-foreground">
                    {row.custom_fields.map((field, i) => (
                        <span key={`${field.label}-${i}`}>
                            {i > 0 && ' · '}
                            <span className="text-foreground/80">
                                {field.label}:
                            </span>{' '}
                            {renderFieldValue(field.value)}
                        </span>
                    ))}
                </p>
            )}
        </div>
    );
}

function EmptyFeed() {
    return (
        <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-12 text-center">
            <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-white/5">
                <UserPlus className="h-6 w-6 text-muted-foreground" />
            </div>
            <h2 className="text-base font-semibold text-foreground">
                No one has registered yet.
            </h2>
            <p className="mt-2 max-w-sm text-sm text-muted-foreground">
                Share the public link to get started.
            </p>
        </div>
    );
}

function FilteredEmpty({ filter }: { filter: Filter }) {
    return (
        <div className="rounded-xl border border-dashed border-white/10 p-8 text-center">
            <p className="text-sm text-muted-foreground">
                No submissions match this filter.
            </p>
            <p className="mt-1 text-xs text-muted-foreground">
                {filter === 'new' && 'Every submission so far is from a returning participant.'}
                {filter === 'returning' && 'Every submission so far is from a first-timer.'}
            </p>
        </div>
    );
}

export default function MonitorShow({ event, stats, registrations }: Props) {
    const [filter, setFilter] = useState<Filter>('all');
    const [now, setNow] = useState(() => new Date());

    const { lastRefreshed, isRefreshing, refresh } = usePolling({
        interval: POLL_INTERVAL_MS,
        only: [...POLL_KEYS],
        enabled: event.is_open,
    });

    // Tick "last updated" every second.
    useEffect(() => {
        const id = setInterval(() => setNow(new Date()), 1000);
        return () => clearInterval(id);
    }, []);

    const counts = useMemo(() => {
        let returning = 0;
        for (const row of registrations) {
            if (row.is_returning) returning += 1;
        }

        return {
            all: registrations.length,
            returning,
            new: registrations.length - returning,
        };
    }, [registrations]);

    const filtered = useMemo(() => {
        if (filter === 'all') return registrations;
        if (filter === 'returning') return registrations.filter((r) => r.is_returning);
        return registrations.filter((r) => !r.is_returning);
    }, [registrations, filter]);

    const secondsAgo = secondsSince(lastRefreshed, now);

    return (
        <>
            <Head title={`Monitor — ${event.event_name}`} />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-6">
                <Link
                    href="/registrations/monitor"
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    All monitors
                </Link>

                {/* Header */}
                <div className="flex flex-col gap-1">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-semibold text-foreground">
                            {event.event_name}
                        </h1>
                        <span
                            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${
                                event.is_open
                                    ? 'bg-lime-brand/20 text-lime-brand'
                                    : 'bg-yellow-500/15 text-yellow-500'
                            }`}
                        >
                            {event.is_open ? 'OPEN' : 'CLOSED'}
                        </span>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {event.event_date ?? '—'}
                        {event.venue !== null && ` · ${event.venue}`}
                    </p>
                </div>

                {/* Stats strip + actions */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <StatsStrip stats={stats} isOpen={event.is_open} />

                    <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                        {event.is_open && event.registration_slug !== null && (
                            <PublicLinkButton slug={event.registration_slug} />
                        )}
                        <span>Updated {relativeSeconds(secondsAgo)}</span>
                        <button
                            type="button"
                            onClick={refresh}
                            disabled={!event.is_open || isRefreshing}
                            className="inline-flex items-center gap-1.5 rounded-md border border-white/10 px-2 py-1 text-xs transition-colors hover:border-white/20 hover:text-foreground disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <RefreshCw
                                className={`h-3 w-3 ${isRefreshing ? 'animate-spin' : ''}`}
                            />
                            Refresh
                        </button>
                    </div>
                </div>

                {/* Filters */}
                {registrations.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2">
                        <Chip
                            active={filter === 'all'}
                            onClick={() => setFilter('all')}
                            label="All"
                            count={counts.all}
                        />
                        <Chip
                            active={filter === 'new'}
                            onClick={() => setFilter('new')}
                            label="New"
                            count={counts.new}
                        />
                        <Chip
                            active={filter === 'returning'}
                            onClick={() => setFilter('returning')}
                            label="Returning"
                            count={counts.returning}
                        />
                    </div>
                )}

                {/* Feed */}
                {registrations.length === 0 ? (
                    <EmptyFeed />
                ) : filtered.length === 0 ? (
                    <FilteredEmpty filter={filter} />
                ) : (
                    <div className="flex flex-col gap-2">
                        {filtered.map((row) => (
                            <RegistrationCard key={row.id} row={row} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

MonitorShow.layout = {
    breadcrumbs: [
        { title: 'Registration', href: '/registrations' },
        { title: 'Registration Monitor', href: '/registrations/monitor' },
    ],
};
