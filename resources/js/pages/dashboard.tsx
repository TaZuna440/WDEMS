import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    Calendar,
    Inbox,
    Activity,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import { useState } from 'react';

type Stat = {
    label: string;
    value: number | string;
    subtext: string;
    href: string;
};
type ActionItem = {
    id: number | string;
    event_name: string;
    event_date: string | null;
    days_until: number | null;
    status: string;
    status_label: string;
    urgency: 'needs_review' | 'overdue' | 'urgent' | 'action' | 'waiting';
    reason: string;
    action: { label: string; href: string };
};
type UpcomingEvent = {
    id: number;
    event_name: string;
    event_date: string;
    days_until: number;
    status: string;
    status_label: string;
    church_category: string | null;
};
type ActivityItem = {
    id: string;
    type: string;
    description: string;
    time: string;
};
type Category = { label: string; count: number; href: string };
type CalendarEvent = {
    id: number;
    event_name: string;
    event_date: string;
    church_category: string | null;
};
type ChurchAttendee = {
    id: number;
    full_name: string;
    attendance_count: number;
    event_types: string[];
    history_url: string;
};
type EventStatistics = {
    recent_runs: {
        id: number;
        event_name: string;
        event_date: string | null;
        registrations: number;
        show_rate: number;
    }[];
    average_show_rate: number;
    paper_encoded_walk_ins: number;
    first_time_runners_this_month: number;
};

type Props = {
    activeSide: 'running' | 'church';
    stats: Stat[];
    actionItems: ActionItem[];
    upcoming: UpcomingEvent[];
    recentActivity: ActivityItem[];
    churchCategories: Category[];
    churchParticipantHistory: ChurchAttendee[];
    calendarEvents: CalendarEvent[];
    eventStatistics: EventStatistics | null;
};

const URGENCY: Record<
    string,
    { label: string; dot: string; border: string; text: string; btn: string }
> = {
    needs_review: {
        label: 'NEEDS REVIEW',
        dot: 'bg-lime-brand',
        border: 'border-lime-brand/30',
        text: 'text-[#3f6212] dark:text-lime-brand',
        btn: 'border-lime-brand/40 text-[#3f6212] hover:bg-lime-brand/15 dark:text-lime-brand',
    },
    overdue: {
        label: 'OVERDUE',
        dot: 'bg-destructive',
        border: 'border-destructive/30',
        text: 'text-destructive',
        btn: 'border-destructive/40 text-destructive hover:bg-destructive hover:text-white',
    },
    urgent: {
        label: 'URGENT',
        dot: 'bg-[#13253B] dark:bg-yellow-500',
        border: 'border-[#13253B]/25 dark:border-yellow-500/30',
        text: 'text-[#13253B] dark:text-yellow-500',
        btn: 'bg-[#13253B] text-white border border-[#13253B] dark:bg-transparent dark:text-yellow-500',
    },
    action: {
        label: 'ACTION NEEDED',
        dot: 'bg-lime-brand',
        border: 'border-lime-brand/30',
        text: 'text-lime-brand',
        btn: 'border-lime-brand/40 text-lime-brand hover:bg-lime-brand hover:text-navy-900',
    },
    waiting: {
        label: 'WAITING',
        dot: 'bg-muted-foreground',
        border: 'border-border',
        text: 'text-muted-foreground',
        btn: 'border-border text-foreground hover:bg-accent',
    },
};

function daysText(days: number | null): string {
    if (days === null) return '';
    if (days === 0) return 'today';
    if (days === 1) return 'in 1 day';
    if (days > 0) return `in ${days} days`;
    if (days === -1) return 'yesterday';
    return `${Math.abs(days)} days ago`;
}

export default function Dashboard({
    activeSide,
    stats,
    actionItems,
    upcoming,
    recentActivity,
    churchCategories,
    churchParticipantHistory,
    calendarEvents,
    eventStatistics,
}: Props) {
    let lastUrgency: string | null = null;
    const [currentMonth, setCurrentMonth] = useState(new Date());
    const [selectedCalendarDate, setSelectedCalendarDate] = useState<
        string | null
    >(null);
    const [hoveredCalendarDate, setHoveredCalendarDate] = useState<
        string | null
    >(null);

    const changeSide = (side: 'running' | 'church') => {
        router.post('/dashboard/side', { side }, { preserveScroll: true });
    };

    const eventsHref =
        activeSide === 'church'
            ? '/events?kind=church'
            : '/events?kind=running';
    const calendarDetailDate = selectedCalendarDate ?? hoveredCalendarDate;

    // Basic Calendar generation
    const daysInMonth = new Date(
        currentMonth.getFullYear(),
        currentMonth.getMonth() + 1,
        0,
    ).getDate();
    const firstDay = new Date(
        currentMonth.getFullYear(),
        currentMonth.getMonth(),
        1,
    ).getDay();
    const blanks = Array.from({ length: firstDay }, () => null);
    const days = Array.from({ length: daysInMonth }, (_, i) => i + 1);
    const calendarCells: Array<number | null> = [...blanks, ...days];
    const eventsByDate = new Map<string, CalendarEvent[]>();
    for (const event of calendarEvents) {
        const date = new Date(`${event.event_date}T00:00:00`);
        if (
            date.getFullYear() !== currentMonth.getFullYear() ||
            date.getMonth() !== currentMonth.getMonth()
        )
            continue;
        eventsByDate.set(event.event_date, [
            ...(eventsByDate.get(event.event_date) ?? []),
            event,
        ]);
    }

    return (
        <>
            <Head title="Dashboard" />
            <div className="mx-auto flex h-full w-full max-w-[1600px] flex-1 flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col justify-between gap-4 pl-10 md:flex-row md:items-end">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold">
                            Dashboard
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            What needs your attention right now.
                        </p>
                    </div>

                    {/* Switcher */}
                    <div
                        role="tablist"
                        aria-label="Dashboard side"
                        className="border-border bg-card/60 flex items-center rounded-lg border p-1"
                    >
                        <button
                            role="tab"
                            aria-selected={activeSide === 'running'}
                            onClick={() => changeSide('running')}
                            className={`rounded-md px-4 py-1.5 text-sm font-medium transition-all ${activeSide === 'running' ? 'bg-lime-brand text-navy-900 shadow' : 'text-muted-foreground hover:text-foreground'}`}
                        >
                            Running Events
                        </button>
                        <button
                            role="tab"
                            aria-selected={activeSide === 'church'}
                            onClick={() => changeSide('church')}
                            className={`rounded-md px-4 py-1.5 text-sm font-medium transition-all ${activeSide === 'church' ? 'bg-lime-brand text-navy-900 shadow' : 'text-muted-foreground hover:text-foreground'}`}
                        >
                            Church Events
                        </button>
                    </div>
                </div>

                <nav
                    aria-label="Dashboard quick actions"
                    className="flex flex-wrap gap-2 pl-10"
                >
                    <Link
                        className="border-border bg-card/60 text-foreground hover:bg-accent rounded-md border px-3 py-2 text-sm"
                        href={
                            activeSide === 'church'
                                ? '/events/create/church'
                                : '/events/create/community-run'
                        }
                    >
                        + Create{' '}
                        {activeSide === 'church' ? 'Church' : 'Running'} Event
                    </Link>
                    <Link
                        className="border-border bg-card/60 text-foreground hover:bg-accent rounded-md border px-3 py-2 text-sm"
                        href={
                            activeSide === 'church'
                                ? eventsHref
                                : '/registrations/monitor'
                        }
                    >
                        {activeSide === 'church'
                            ? 'Open Church Registrations'
                            : 'Open Registration'}
                    </Link>
                    <Link
                        className="border-border bg-card/60 text-foreground hover:bg-accent rounded-md border px-3 py-2 text-sm"
                        href={
                            activeSide === 'church' ? eventsHref : '/attendance'
                        }
                    >
                        Record Attendance
                    </Link>
                    <Link
                        className="border-border bg-card/60 text-foreground hover:bg-accent rounded-md border px-3 py-2 text-sm"
                        href={`/reports?kind=${activeSide}`}
                    >
                        Reports
                    </Link>
                </nav>

                {/* Stat Cards */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {stats.map((stat, i) => (
                        <Link
                            key={i}
                            href={stat.href}
                            className="glass-panel flex flex-col justify-between rounded-xl p-5"
                        >
                            <p className="text-muted-foreground text-sm font-medium">
                                {stat.label}
                            </p>
                            <div className="mt-2">
                                <p className="text-foreground text-3xl font-semibold">
                                    {stat.value}
                                </p>
                                <p className="dark:text-lime-brand mt-1 text-xs text-[#3f6212]">
                                    {stat.subtext}
                                </p>
                            </div>
                        </Link>
                    ))}
                </div>

                {/* Main Grid */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    {/* Needs Attention */}
                    <div className="glass-panel rounded-xl p-6 lg:col-span-7">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-muted-foreground text-sm font-semibold tracking-wider uppercase">
                                Needs Attention
                            </h2>
                            <span className="text-muted-foreground bg-muted rounded-full px-2 py-0.5 text-xs font-medium">
                                {actionItems.length}
                            </span>
                        </div>
                        {actionItems.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-8 text-center">
                                <Inbox className="text-muted-foreground/40 h-8 w-8" />
                                <p className="text-foreground mt-2 text-sm">
                                    All clear.
                                </p>
                            </div>
                        ) : (
                            <ul className="flex flex-col gap-3">
                                {actionItems.map((item) => {
                                    const u =
                                        URGENCY[item.urgency] ??
                                        URGENCY.waiting;
                                    const showHeader =
                                        item.urgency !== lastUrgency;
                                    lastUrgency = item.urgency;
                                    return (
                                        <li key={item.id}>
                                            {showHeader && (
                                                <div className="mt-2 mb-2 flex items-center gap-2 first:mt-0">
                                                    <div
                                                        className={`h-1.5 w-1.5 rounded-full ${u.dot}`}
                                                    />
                                                    <span
                                                        className={`text-xs font-semibold tracking-wider ${u.text}`}
                                                    >
                                                        {u.label}
                                                    </span>
                                                </div>
                                            )}
                                            <div
                                                className={`rounded-lg border ${u.border} bg-card/40 hover:bg-accent/40 p-3 transition-colors`}
                                            >
                                                <div className="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                                                    <div>
                                                        <Link
                                                            href={
                                                                item.urgency ===
                                                                'needs_review'
                                                                    ? item
                                                                          .action
                                                                          .href
                                                                    : `/events/${item.id}`
                                                            }
                                                            className="text-foreground text-sm font-medium hover:underline"
                                                        >
                                                            {item.event_name}
                                                        </Link>
                                                        <p className="text-muted-foreground mt-0.5 text-xs">
                                                            {item.reason}
                                                        </p>
                                                    </div>
                                                    <Link
                                                        href={item.action.href}
                                                        className={`inline-flex w-fit shrink-0 items-center gap-1 rounded-md border px-3 py-1.5 text-xs font-medium transition-colors ${u.btn}`}
                                                    >
                                                        {item.action.label}{' '}
                                                        <ArrowRight className="h-3 w-3" />
                                                    </Link>
                                                </div>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </div>

                    {/* Recent Activity */}
                    <div className="glass-panel rounded-xl p-6 lg:col-span-7">
                        <h2 className="text-muted-foreground mb-4 text-sm font-semibold tracking-wider uppercase">
                            Recent Activity
                        </h2>
                        {recentActivity.length === 0 ? (
                            <p className="text-muted-foreground py-4 text-sm">
                                No activity yet.
                            </p>
                        ) : (
                            <ul className="space-y-4">
                                {recentActivity.map((act) => (
                                    <li
                                        key={act.id}
                                        className="flex items-start gap-3"
                                    >
                                        <div className="bg-muted mt-0.5 rounded-full p-1.5">
                                            <Activity className="text-muted-foreground h-3 w-3" />
                                        </div>
                                        <div>
                                            <p className="text-foreground text-sm">
                                                {act.description}
                                            </p>
                                            <p className="text-muted-foreground mt-0.5 text-xs">
                                                {act.time}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    {/* Coming Up */}
                    <div className="glass-panel rounded-xl p-6 lg:col-span-5 lg:col-start-8 lg:row-start-1">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-muted-foreground text-sm font-semibold tracking-wider uppercase">
                                Coming Up
                            </h2>
                            <Link
                                href={eventsHref}
                                className="text-muted-foreground hover:text-foreground text-xs transition-colors"
                            >
                                View all →
                            </Link>
                        </div>
                        {upcoming.length === 0 ? (
                            <p className="text-muted-foreground py-4 text-center text-sm">
                                No upcoming events.
                            </p>
                        ) : (
                            <ul className="flex flex-col">
                                {upcoming.map((e) => (
                                    <li
                                        key={e.id}
                                        className="border-border/60 border-b last:border-b-0"
                                    >
                                        <Link
                                            href={`/events/${e.id}`}
                                            className="hover:bg-accent/40 flex items-center justify-between gap-3 py-3 transition-colors"
                                        >
                                            <div className="flex-1">
                                                <p className="text-foreground text-sm font-medium">
                                                    {e.event_name}
                                                </p>
                                                <p className="text-muted-foreground mt-0.5 flex items-center gap-2 text-xs">
                                                    <Calendar className="h-3 w-3" />{' '}
                                                    {e.event_date}
                                                    {activeSide === 'church' &&
                                                        e.church_category && (
                                                            <span>
                                                                ·{' '}
                                                                {
                                                                    e.church_category
                                                                }
                                                            </span>
                                                        )}
                                                    <span className="dark:text-lime-brand text-[#3f6212]">
                                                        {daysText(e.days_until)}
                                                    </span>
                                                </p>
                                            </div>
                                            <span className="text-muted-foreground shrink-0 text-xs">
                                                {e.status_label}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    {/* Calendar */}
                    <div className="glass-panel rounded-xl p-6 lg:col-span-5 lg:col-start-8 lg:row-start-2">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-muted-foreground text-sm font-semibold tracking-wider uppercase">
                                Calendar
                            </h2>
                            <div className="flex items-center gap-2">
                                <button
                                    onClick={() =>
                                        setCurrentMonth(
                                            new Date(
                                                currentMonth.getFullYear(),
                                                currentMonth.getMonth() - 1,
                                                1,
                                            ),
                                        )
                                    }
                                    className="hover:bg-accent rounded p-1"
                                >
                                    <ChevronLeft className="h-4 w-4" />
                                </button>
                                <span className="text-foreground text-xs font-medium">
                                    {currentMonth.toLocaleString('default', {
                                        month: 'short',
                                        year: 'numeric',
                                    })}
                                </span>
                                <button
                                    onClick={() =>
                                        setCurrentMonth(
                                            new Date(
                                                currentMonth.getFullYear(),
                                                currentMonth.getMonth() + 1,
                                                1,
                                            ),
                                        )
                                    }
                                    className="hover:bg-accent rounded p-1"
                                >
                                    <ChevronRight className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <div className="mb-2 grid grid-cols-7 gap-1 text-center">
                            {['S', 'M', 'T', 'W', 'T', 'F', 'S'].map((d, i) => (
                                <span
                                    key={i}
                                    className="text-muted-foreground text-[10px]"
                                >
                                    {d}
                                </span>
                            ))}
                        </div>
                        <div className="grid grid-cols-7 gap-1 text-center">
                            {calendarCells.map((day, index) => {
                                if (day === null)
                                    return <span key={`blank-${index}`} />;
                                const date = new Date(
                                    currentMonth.getFullYear(),
                                    currentMonth.getMonth(),
                                    day,
                                );
                                const dateKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                                const events = eventsByDate.get(dateKey) ?? [];
                                const today =
                                    dateKey ===
                                    new Date().toLocaleDateString('en-CA');
                                const isSelected =
                                    selectedCalendarDate === dateKey;
                                return (
                                    <button
                                        key={dateKey}
                                        type="button"
                                        title={
                                            events
                                                .map(
                                                    (event) => event.event_name,
                                                )
                                                .join('\n') || undefined
                                        }
                                        aria-label={`${date.toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' })}${events.length ? `: ${events.map((event) => event.event_name).join(', ')}` : ''}`}
                                        aria-expanded={isSelected}
                                        onClick={() =>
                                            setSelectedCalendarDate(
                                                isSelected ? null : dateKey,
                                            )
                                        }
                                        onMouseEnter={() =>
                                            setHoveredCalendarDate(dateKey)
                                        }
                                        onMouseLeave={() =>
                                            setHoveredCalendarDate(null)
                                        }
                                        onFocus={() =>
                                            setHoveredCalendarDate(dateKey)
                                        }
                                        onBlur={() =>
                                            setHoveredCalendarDate(null)
                                        }
                                        className={`relative flex h-9 flex-col items-center justify-center rounded-md text-xs ${today || isSelected ? 'bg-lime-brand text-navy-900 font-semibold' : 'text-foreground/75 hover:bg-accent'}`}
                                    >
                                        {day}
                                        {events.length > 0 && (
                                            <span
                                                aria-hidden="true"
                                                className="absolute bottom-1 flex items-center gap-0.5"
                                            >
                                                {events.map((event) => (
                                                    <span
                                                        key={event.id}
                                                        className="bg-lime-brand h-1 w-1 rounded-full"
                                                    />
                                                ))}
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                        {calendarDetailDate &&
                            (selectedCalendarDate === calendarDetailDate ||
                                (eventsByDate.get(calendarDetailDate) ?? [])
                                    .length > 0) && (
                                <div
                                    className="border-border bg-card/70 mt-4 rounded-lg border p-3"
                                    role="region"
                                    aria-label={`Events on ${calendarDetailDate}`}
                                >
                                    <p className="text-foreground text-xs font-semibold">
                                        {new Date(
                                            `${calendarDetailDate}T00:00:00`,
                                        ).toLocaleDateString(undefined, {
                                            dateStyle: 'full',
                                        })}
                                    </p>
                                    {(
                                        eventsByDate.get(calendarDetailDate) ??
                                        []
                                    ).length ? (
                                        <ul className="mt-2 space-y-1">
                                            {(
                                                eventsByDate.get(
                                                    calendarDetailDate,
                                                ) ?? []
                                            ).map((event) => (
                                                <li key={event.id}>
                                                    <Link
                                                        className="dark:text-lime-brand text-[#3f6212] underline"
                                                        href={`/events/${event.id}`}
                                                    >
                                                        {event.event_name}
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <p className="text-muted-foreground mt-2 text-xs">
                                            No events scheduled.
                                        </p>
                                    )}
                                    <Link
                                        className="mt-2 inline-block text-xs underline"
                                        href={`${eventsHref}&date=${calendarDetailDate}`}
                                    >
                                        View this day’s events
                                    </Link>
                                </div>
                            )}
                    </div>

                    {/* Church Categories (Church Only) */}
                    {activeSide === 'church' && (
                        <>
                            <div className="glass-panel rounded-xl p-6 lg:col-span-7">
                                <h2 className="text-muted-foreground mb-4 text-sm font-semibold tracking-wider uppercase">
                                    Church Categories
                                </h2>
                                <Link
                                    href="/events/church/system-scope"
                                    className="text-lime-brand mb-3 inline-flex text-sm font-medium underline"
                                >
                                    System Scope
                                </Link>
                                <div className="flex flex-wrap gap-2">
                                    {churchCategories.map((cat, i) => (
                                        <Link
                                            key={i}
                                            href={cat.href}
                                            className="border-lime-brand/30 bg-lime-brand/10 dark:text-lime-brand flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium text-[#3f6212]"
                                        >
                                            {cat.label}
                                            <span className="bg-lime-brand/20 dark:text-lime-brand rounded-full px-1.5 py-0.5 text-[10px] text-[#3f6212]">
                                                {cat.count}
                                            </span>
                                        </Link>
                                    ))}
                                </div>
                            </div>

                            <section
                                className="glass-panel rounded-xl p-6 lg:col-span-5"
                                aria-labelledby="church-participant-history-heading"
                            >
                                <div className="mb-4 flex items-start justify-between gap-4">
                                    <div>
                                        <h2
                                            id="church-participant-history-heading"
                                            className="text-muted-foreground text-sm font-semibold tracking-wider uppercase"
                                        >
                                            Participant History
                                        </h2>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            Top attendees in the church
                                            participant pool
                                        </p>
                                    </div>
                                    <span className="bg-lime-brand/10 dark:text-lime-brand rounded-full px-2.5 py-1 text-xs font-medium text-[#3f6212]">
                                        Top 5
                                    </span>
                                </div>
                                {churchParticipantHistory.length === 0 ? (
                                    <p className="text-muted-foreground py-5 text-center text-sm">
                                        No attended church events have been
                                        recorded yet.
                                    </p>
                                ) : (
                                    <ol className="divide-border divide-y">
                                        {churchParticipantHistory.map(
                                            (person, index) => (
                                                <li key={person.id}>
                                                    <Link
                                                        href={
                                                            person.history_url
                                                        }
                                                        className="hover:bg-accent/40 flex flex-col gap-1 py-3 transition-colors sm:flex-row sm:items-center sm:justify-between"
                                                    >
                                                        <span className="text-foreground text-sm font-medium">
                                                            <span className="text-muted-foreground mr-2">
                                                                {index + 1}.
                                                            </span>
                                                            {person.full_name}
                                                        </span>
                                                        <span className="text-muted-foreground text-xs sm:text-right">
                                                            {
                                                                person.attendance_count
                                                            }{' '}
                                                            {person.attendance_count ===
                                                            1
                                                                ? 'attendance'
                                                                : 'attendances'}
                                                            {person.event_types
                                                                .length > 0 &&
                                                                ` · ${person.event_types.join(', ')}`}
                                                        </span>
                                                    </Link>
                                                </li>
                                            ),
                                        )}
                                    </ol>
                                )}
                            </section>
                        </>
                    )}

                    {activeSide === 'running' && eventStatistics && (
                        <section
                            className="glass-panel space-y-5 rounded-xl p-6 lg:col-span-12"
                            aria-labelledby="running-statistics-heading"
                        >
                            <div>
                                <h2
                                    id="running-statistics-heading"
                                    className="text-muted-foreground text-sm font-semibold tracking-wider uppercase"
                                >
                                    Event Statistics
                                </h2>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Recent completed Community Runs
                                </p>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-3">
                                <div className="border-border rounded-lg border p-3">
                                    <p className="text-muted-foreground text-xs">
                                        Average show rate · last{' '}
                                        {eventStatistics.recent_runs.length ||
                                            4}{' '}
                                        runs
                                    </p>
                                    <p className="mt-1 text-2xl font-semibold">
                                        {eventStatistics.average_show_rate}%
                                    </p>
                                </div>
                                <div className="border-border rounded-lg border p-3">
                                    <p className="text-muted-foreground text-xs">
                                        Paper-encoded walk-ins
                                    </p>
                                    <p className="mt-1 text-2xl font-semibold">
                                        {eventStatistics.paper_encoded_walk_ins}
                                    </p>
                                </div>
                                <div className="border-border rounded-lg border p-3">
                                    <p className="text-muted-foreground text-xs">
                                        First-time runners this month
                                    </p>
                                    <p className="mt-1 text-2xl font-semibold">
                                        {
                                            eventStatistics.first_time_runners_this_month
                                        }
                                    </p>
                                </div>
                            </div>
                            {eventStatistics.recent_runs.length > 0 ? (
                                <ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                    {eventStatistics.recent_runs.map((run) => (
                                        <li
                                            key={run.id}
                                            className="border-border rounded-lg border p-3"
                                        >
                                            <Link
                                                href={`/events/${run.id}`}
                                                className="text-sm font-medium underline"
                                            >
                                                {run.event_name}
                                            </Link>
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                {run.event_date ??
                                                    'Date not recorded'}{' '}
                                                · {run.registrations}{' '}
                                                registrations · {run.show_rate}%
                                                show rate
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-muted-foreground text-sm">
                                    No completed Community Runs yet.
                                </p>
                            )}
                        </section>
                    )}
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
};
