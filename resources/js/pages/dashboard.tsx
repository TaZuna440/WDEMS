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

const URGENCY: Record<string, { label: string; dot: string; border: string; text: string; btn: string }> = {
    overdue: {
        label: 'OVERDUE',
        dot: 'bg-destructive',
        border: 'border-destructive/30',
        text: 'text-destructive',
        btn: 'border-destructive/40 text-destructive hover:border-destructive hover:bg-destructive hover:text-white',
    },
    urgent: {
        label: 'URGENT',
        dot: 'bg-yellow-500',
        border: 'border-yellow-500/30',
        text: 'text-yellow-500',
        btn: 'border-yellow-500/40 text-yellow-500 hover:border-yellow-500 hover:bg-yellow-500 hover:text-navy-900',
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
                <div>
                    <h1 className="text-2xl font-semibold text-foreground">Dashboard</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        What needs your attention right now.
                    </p>
                </div>

                {/* Action Feed */}
                <div className="glass-panel rounded-xl p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                            Needs Attention
                        </h2>
                        <span className="rounded-full bg-white/5 px-2 py-0.5 text-xs font-medium text-muted-foreground">
                            {actionItems.length}
                        </span>
                    </div>

                    {actionItems.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <Inbox className="h-10 w-10 text-muted-foreground/40" />
                            <p className="mt-3 text-sm text-foreground">All clear.</p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Nothing requires your attention right now.
                            </p>
                        </div>
                    ) : (
                        <ul className="flex flex-col gap-3">
                            {actionItems.map((item) => {
                                const u = URGENCY[item.urgency] ?? URGENCY.waiting;
                                const showHeader = item.urgency !== lastUrgency;
                                lastUrgency = item.urgency;

                                return (
                                    <li key={item.id}>
                                        {showHeader && (
                                            <div className="mb-2 mt-2 flex items-center gap-2 first:mt-0">
                                                <div className={`h-1.5 w-1.5 rounded-full ${u.dot}`} />
                                                <span className={`text-xs font-semibold tracking-wider ${u.text}`}>
                                                    {u.label}
                                                </span>
                                            </div>
                                        )}

                                        <div className={`rounded-lg border ${u.border} bg-white/[0.02] p-3 transition-colors hover:bg-white/[0.04]`}>
                                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <div className="flex-1">
                                                    <Link
                                                        href={`/events/${item.id}`}
                                                        className="text-sm font-medium text-foreground hover:underline"
                                                    >
                                                        {item.event_name}
                                                    </Link>
                                                    <p className="mt-0.5 text-xs text-muted-foreground">
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
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                            Coming Up
                        </h2>
                        <Link
                            href="/events"
                            className="text-xs text-muted-foreground transition-colors hover:text-foreground"
                        >
                            View all →
                        </Link>
                    </div>

                    {upcoming.length === 0 ? (
                        <p className="py-6 text-center text-xs text-muted-foreground">
                            No upcoming events.
                        </p>
                    ) : (
                        <ul className="flex flex-col">
                            {upcoming.map((e) => (
                                <li key={e.id} className="border-b border-white/5 last:border-b-0">
                                    <Link
                                        href={`/events/${e.id}`}
                                        className="flex items-center justify-between gap-3 py-2.5 transition-colors hover:bg-white/[0.02]"
                                    >
                                        <div className="flex-1">
                                            <p className="text-sm text-foreground">{e.event_name}</p>
                                            <p className="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                                                <Calendar className="h-3 w-3" />
                                                {e.event_date}
                                                <span className="text-lime-brand">{daysText(e.days_until)}</span>
                                            </p>
                                        </div>
                                        <span className="shrink-0 text-xs text-muted-foreground">
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
