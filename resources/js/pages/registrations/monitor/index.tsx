import { Head, Link } from '@inertiajs/react';
import { ChevronRight, Monitor } from 'lucide-react';

type EventRow = {
    id: number;
    event_name: string;
    event_date: string | null;
    venue: string | null;
    status: string;
    status_label: string;
    total: number;
    last_hour: number;
    most_recent_iso: string | null;
    most_recent_human: string | null;
};

type Props = {
    events: EventRow[];
};

function EventCard({ event }: { event: EventRow }) {
    return (
        <Link
            href={`/registrations/monitor/${event.id}`}
            className="glass-panel group flex items-center gap-4 rounded-xl p-4 transition-colors hover:border-lime-brand/30"
        >
            <div className="flex flex-1 flex-col gap-1">
                <div className="flex items-center gap-3">
                    <h3 className="text-sm font-semibold text-foreground">
                        {event.event_name}
                    </h3>
                    <span className="inline-flex rounded-full bg-lime-brand/20 px-2 py-0.5 text-xs font-medium text-lime-brand">
                        {event.status_label}
                    </span>
                </div>

                <p className="text-xs text-muted-foreground">
                    {event.event_date ?? '—'}
                    {event.venue !== null && ` · ${event.venue}`}
                </p>

                <div className="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                    <span>
                        <span className="font-medium text-foreground">
                            {event.total}
                        </span>{' '}
                        registered
                    </span>
                    {event.last_hour > 0 && (
                        <span className="text-lime-brand">
                            +{event.last_hour} in the last hour
                        </span>
                    )}
                    {event.most_recent_human !== null && (
                        <span>Last submission: {event.most_recent_human}</span>
                    )}
                </div>
            </div>

            <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground transition-colors group-hover:text-foreground" />
        </Link>
    );
}

function EmptyState() {
    return (
        <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-12 text-center">
            <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-white/5">
                <Monitor className="h-6 w-6 text-muted-foreground" />
            </div>
            <h2 className="text-base font-semibold text-foreground">
                No events with open registration right now.
            </h2>
            <p className="mt-2 max-w-sm text-sm text-muted-foreground">
                Open registration for an event to see live submissions here.
            </p>
            <Link
                href="/events"
                className="mt-6 text-sm font-medium text-lime-brand hover:underline"
            >
                → Go to events
            </Link>
        </div>
    );
}

export default function MonitorIndex({ events }: Props) {
    return (
        <>
            <Head title="Registration Monitor" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Registration Monitor
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Events currently accepting registrations.
                    </p>
                </div>

                {events.length === 0 ? (
                    <EmptyState />
                ) : (
                    <div className="flex flex-col gap-2">
                        {events.map((event) => (
                            <EventCard key={event.id} event={event} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

MonitorIndex.layout = {
    breadcrumbs: [
        { title: 'Registration', href: '/registrations' },
        { title: 'Monitor', href: '/registrations/monitor' },
    ],
};
