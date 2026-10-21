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
            className="glass-panel group hover:border-lime-brand/30 flex items-center gap-4 rounded-xl p-4 transition-colors"
        >
            <div className="flex flex-1 flex-col gap-1">
                <div className="flex items-center gap-3">
                    <h3 className="text-foreground text-sm font-semibold">
                        {event.event_name}
                    </h3>
                    <span className="bg-lime-brand/20 text-lime-brand inline-flex rounded-full px-2 py-0.5 text-xs font-medium">
                        {event.status_label}
                    </span>
                </div>

                <p className="text-muted-foreground text-xs">
                    {event.event_date ?? '—'}
                    {event.venue !== null && ` · ${event.venue}`}
                </p>

                <div className="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                    <span>
                        <span className="text-foreground font-medium">
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

            <ChevronRight className="text-muted-foreground group-hover:text-foreground h-4 w-4 shrink-0 transition-colors" />
        </Link>
    );
}

function EmptyState() {
    return (
        <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-12 text-center">
            <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-white/5">
                <Monitor className="text-muted-foreground h-6 w-6" />
            </div>
            <h2 className="text-foreground text-base font-semibold">
                No events with open registration right now.
            </h2>
            <p className="text-muted-foreground mt-2 max-w-sm text-sm">
                Open registration for an event to see live submissions here.
            </p>
            <Link
                href="/events"
                className="text-lime-brand mt-6 text-sm font-medium hover:underline"
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
                    <h1 className="text-foreground text-2xl font-semibold">
                        Registration Monitor
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
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
