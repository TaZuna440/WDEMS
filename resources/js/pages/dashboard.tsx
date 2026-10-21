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

type Stat = { label: string; value: number | string; subtext: string };
type ActionItem = {
    id: number;
    event_name: string;
    event_date: string | null;
    days_until: number | null;
    status: string;
    status_label: string;
    urgency: 'overdue' | 'urgent' | 'action' | 'waiting';
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
};
type ActivityItem = {
    id: number;
    type: string;
    description: string;
    time: string;
};
type Category = { label: string; count: number };

type Props = {
    activeSide: 'running' | 'church';
    stats: Stat[];
    actionItems: ActionItem[];
    upcoming: UpcomingEvent[];
    recentActivity: ActivityItem[];
    churchCategories: Category[];
};

const URGENCY: Record<
    string,
    { label: string; dot: string; border: string; text: string; btn: string }
> = {
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
        border: 'border-white/10',
        text: 'text-muted-foreground',
        btn: 'border-white/20 text-foreground hover:bg-white/5',
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
}: Props) {
    let lastUrgency: string | null = null;
    const [currentMonth, setCurrentMonth] = useState(new Date());

    const changeSide = (side: 'running' | 'church') => {
        router.get(
            '/dashboard',
            { side },
            { preserveState: true, preserveScroll: true },
        );
    };

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
    const blanks = Array(firstDay).fill(null);
    const days = Array.from({ length: daysInMonth }, (_, i) => i + 1);
    const calendarCells = [...blanks, ...days];

    return (
        <>
            <Head title="Dashboard" />
            <div className="mx-auto flex h-full w-full max-w-[1600px] flex-1 flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col justify-between gap-4 pl-10 md:flex-row md:items-end">
                    <div>
                        <h1 className="text-2xl font-semibold text-white [text-shadow:0_1px_2px_rgba(0,0,0,0.55)]">
                            Dashboard
                        </h1>
                        <p className="mt-1 text-sm text-white/90">
                            What needs your attention right now.
                        </p>
                    </div>

                    {/* Switcher */}
                    <div className="flex items-center rounded-lg border border-white/10 bg-white/5 p-1">
                        <button
                            onClick={() => changeSide('running')}
                            className={`rounded-md px-4 py-1.5 text-sm font-medium transition-all ${activeSide === 'running' ? 'bg-lime-brand text-navy-900 shadow' : 'text-muted-foreground hover:text-white'}`}
                        >
                            Running Events
                        </button>
                        <button
                            onClick={() => changeSide('church')}
                            className={`rounded-md px-4 py-1.5 text-sm font-medium transition-all ${activeSide === 'church' ? 'bg-violet-500 text-white shadow' : 'text-muted-foreground hover:text-white'}`}
                        >
                            Church Events
                        </button>
                    </div>
                </div>

                {/* Stat Cards */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {stats.map((stat, i) => (
                        <div
                            key={i}
                            className="glass-panel flex flex-col justify-between rounded-xl p-5"
                        >
                            <p className="text-muted-foreground text-sm font-medium">
                                {stat.label}
                            </p>
                            <div className="mt-2">
                                <p className="text-3xl font-semibold text-white">
                                    {stat.value}
                                </p>
                                <p
                                    className={`mt-1 text-xs ${activeSide === 'running' ? 'text-lime-brand' : 'text-violet-400'}`}
                                >
                                    {stat.subtext}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Main Grid */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    {/* Left Column (7/12) */}
                    <div className="flex flex-col gap-6 lg:col-span-7">
                        {/* Needs Attention */}
                        <div className="glass-panel rounded-xl p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h2 className="text-muted-foreground text-sm font-semibold tracking-wider uppercase">
                                    Needs Attention
                                </h2>
                                <span className="text-muted-foreground rounded-full bg-white/5 px-2 py-0.5 text-xs font-medium">
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
                                                    className={`rounded-lg border ${u.border} bg-white/[0.02] p-3 transition-colors hover:bg-white/[0.04]`}
                                                >
                                                    <div className="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                                                        <div>
                                                            <Link
                                                                href={`/events/${item.id}`}
                                                                className="text-foreground text-sm font-medium hover:underline"
                                                            >
                                                                {
                                                                    item.event_name
                                                                }
                                                            </Link>
                                                            <p className="text-muted-foreground mt-0.5 text-xs">
                                                                {item.reason}
                                                            </p>
                                                        </div>
                                                        <Link
                                                            href={
                                                                item.action.href
                                                            }
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
                        <div className="glass-panel rounded-xl p-6">
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
                                            <div className="mt-0.5 rounded-full bg-white/10 p-1.5">
                                                <Activity className="h-3 w-3 text-white/70" />
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
                    </div>

                    {/* Right Column (5/12) */}
                    <div className="flex flex-col gap-6 lg:col-span-5">
                        {/* Coming Up */}
                        <div className="glass-panel rounded-xl p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h2 className="text-muted-foreground text-sm font-semibold tracking-wider uppercase">
                                    Coming Up
                                </h2>
                                <Link
                                    href="/events"
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
                                            className="border-b border-white/5 last:border-b-0"
                                        >
                                            <Link
                                                href={`/events/${e.id}`}
                                                className="flex items-center justify-between gap-3 py-3 transition-colors hover:bg-white/[0.02]"
                                            >
                                                <div className="flex-1">
                                                    <p className="text-foreground text-sm font-medium">
                                                        {e.event_name}
                                                    </p>
                                                    <p className="text-muted-foreground mt-0.5 flex items-center gap-2 text-xs">
                                                        <Calendar className="h-3 w-3" />{' '}
                                                        {e.event_date}
                                                        <span
                                                            className={
                                                                activeSide ===
                                                                'running'
                                                                    ? 'text-lime-brand'
                                                                    : 'text-violet-400'
                                                            }
                                                        >
                                                            {daysText(
                                                                e.days_until,
                                                            )}
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
                        <div className="glass-panel rounded-xl p-6">
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
                                        className="rounded p-1 hover:bg-white/10"
                                    >
                                        <ChevronLeft className="h-4 w-4" />
                                    </button>
                                    <span className="text-xs font-medium text-white">
                                        {currentMonth.toLocaleString(
                                            'default',
                                            { month: 'short', year: 'numeric' },
                                        )}
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
                                        className="rounded p-1 hover:bg-white/10"
                                    >
                                        <ChevronRight className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                            <div className="mb-2 grid grid-cols-7 gap-1 text-center">
                                {['S', 'M', 'T', 'W', 'T', 'F', 'S'].map(
                                    (d, i) => (
                                        <span
                                            key={i}
                                            className="text-muted-foreground text-[10px]"
                                        >
                                            {d}
                                        </span>
                                    ),
                                )}
                            </div>
                            <div className="grid grid-cols-7 gap-1 text-center">
                                {calendarCells.map((d, i) => (
                                    <div
                                        key={i}
                                        className={`flex h-7 items-center justify-center rounded-md text-xs ${d === new Date().getDate() && currentMonth.getMonth() === new Date().getMonth() ? (activeSide === 'running' ? 'bg-lime-brand text-navy-900 font-semibold' : 'bg-violet-500 font-semibold text-white') : 'text-white/70 hover:bg-white/5'}`}
                                    >
                                        {d}
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Church Categories (Church Only) */}
                    {activeSide === 'church' && (
                        <div className="glass-panel rounded-xl p-6 lg:col-span-12">
                            <h2 className="text-muted-foreground mb-4 text-sm font-semibold tracking-wider uppercase">
                                Church Categories
                            </h2>
                            <div className="flex flex-wrap gap-2">
                                {churchCategories.map((cat, i) => (
                                    <div
                                        key={i}
                                        className="flex items-center gap-2 rounded-full border border-violet-500/20 bg-violet-500/10 px-3 py-1.5 text-xs font-medium text-violet-300"
                                    >
                                        {cat.label}
                                        <span className="rounded-full bg-violet-500/20 px-1.5 py-0.5 text-[10px] text-violet-200">
                                            {cat.count}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
};
