import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Calendar, Inbox } from 'lucide-react';

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

type Props = {
    actionItems: ActionItem[];
    upcoming: UpcomingEvent[];
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
        btn: 'border-destructive/40 text-destructive hover:border-destructive hover:bg-destructive hover:text-white',
    },
    urgent: {
        label: 'URGENT',
        dot: 'bg-[#13253B] dark:bg-yellow-500',
        border: 'border-[#13253B]/25 dark:border-yellow-500/30',
        text: 'text-[#13253B] dark:text-yellow-500',
        btn: 'bg-[#13253B] text-white border border-[#13253B] hover:bg-[#13253B]/90 dark:bg-transparent dark:text-yellow-500 dark:border-yellow-500/40 dark:hover:bg-yellow-500 dark:hover:border-yellow-500 dark:hover:text-navy-900',
    },
    action: {
        label: 'ACTION NEEDED',
        dot: 'bg-lime-brand',
        border: 'border-lime-brand/30',
        text: 'text-lime-brand',
        btn: 'border-lime-brand/40 text-lime-brand hover:border-lime-brand hover:bg-lime-brand hover:text-navy-900',
    },
    waiting: {
        label: 'WAITING',
        dot: 'bg-muted-foreground',
        border: 'border-white/10',
        text: 'text-muted-foreground',
        btn: 'border-white/20 text-foreground hover:border-white/40 hover:bg-white/5',
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

export default function Dashboard({ actionItems, upcoming }: Props) {
    let lastUrgency: string | null = null;

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="pl-10">
                    <h1 className="text-2xl font-semibold text-white [text-shadow:0_1px_2px_rgba(0,0,0,0.55),0_0_24px_rgba(0,0,0,0.35)]">
                        Dashboard
                    </h1>
                    <p className="mt-1 text-sm text-white/90 [text-shadow:0_1px_2px_rgba(0,0,0,0.55),0_0_24px_rgba(0,0,0,0.35)]">
                        What needs your attention right now.
                    </p>
                </div>

                {/* Action Feed */}
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
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <Inbox className="text-muted-foreground/40 h-10 w-10" />
                            <p className="text-foreground mt-3 text-sm">
                                All clear.
                            </p>
                            <p className="text-muted-foreground mt-1 text-xs">
                                Nothing requires your attention right now.
                            </p>
                        </div>
                    ) : (
                        <ul className="flex flex-col gap-3">
                            {actionItems.map((item) => {
                                const u =
                                    URGENCY[item.urgency] ?? URGENCY.waiting;
                                const showHeader = item.urgency !== lastUrgency;
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
                                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <div className="flex-1">
                                                    <Link
                                                        href={`/events/${item.id}`}
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
                                                    {item.action.label}
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
                        <p className="text-muted-foreground py-6 text-center text-xs">
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
                                        className="flex items-center justify-between gap-3 py-2.5 transition-colors hover:bg-white/[0.02]"
                                    >
                                        <div className="flex-1">
                                            <p className="text-foreground text-sm">
                                                {e.event_name}
                                            </p>
                                            <p className="text-muted-foreground mt-0.5 flex items-center gap-2 text-xs">
                                                <Calendar className="h-3 w-3" />
                                                {e.event_date}
                                                <span className="text-lime-brand">
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
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
};
