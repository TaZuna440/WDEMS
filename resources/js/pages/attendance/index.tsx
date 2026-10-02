import { Head, Link } from '@inertiajs/react';
import { CalendarDays, ChevronRight, ClipboardCheck, MapPin } from 'lucide-react';

type EventRow = {
    id: number;
    event_name: string;
    event_date: string | null;
    venue: string | null;
    status: string;
    status_label: string;
    registered_count: number;
    marked_count: number;
};

type Props = {
    today: EventRow[];
    recent: EventRow[];
};

const statusStyles: Record<string, string> = {
    registration_open: 'bg-lime-brand/20 text-lime-brand',
    registration_closed: 'bg-yellow-500/15 text-yellow-500',
    ongoing: 'bg-accent/20 text-accent',
};

function EventCard({ event }: { event: EventRow }) {
    return (
        <Link
            href={`/events/${event.id}/attendance`}
            className="glass-panel group flex items-center gap-4 rounded-xl p-4 transition-colors hover:border-lime-brand/30"
        >
            <div className="flex-1 flex flex-col gap-1">
                <div className="flex items-center gap-3">
                    <h3 className="text-sm font-semibold text-foreground">
                        {event.event_name}
                    </h3>
                    <span
                        className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${
                            statusStyles[event.status] ?? statusStyles.registration_closed
                        }`}
                    >
                        {event.status_label}
                    </span>
                </div>

                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                    {event.event_date !== null && (
                        <span className="flex items-center gap-1.5">
                            <CalendarDays className="h-3.5 w-3.5" />
                            {event.event_date}
                        </span>
                    )}
                    {event.venue !== null && (
                        <span className="flex items-center gap-1.5">
                            <MapPin className="h-3.5 w-3.5" />
                            {event.venue}
                        </span>
                    )}
                </div>

                <div className="flex items-center gap-4 text-xs text-muted-foreground">
                    <span>
                        <span className="font-medium text-foreground">
                            {event.registered_count}
                        </span>{' '}
                        registered
                    </span>
                    <span>
                        <span className="font-medium text-foreground">
                            {event.marked_count}
                        </span>{' '}
                        marked
                    </span>
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
                <ClipboardCheck className="h-6 w-6 text-muted-foreground" />
            </div>
            <h2 className="text-base font-semibold text-foreground">
                No events to record attendance for.
            </h2>
            <p className="mt-2 max-w-sm text-sm text-muted-foreground">
                Events appear here on the day they are scheduled. Come back
                when an event is happening.
            </p>
        </div>
    );
}

export default function AttendanceIndex({ today, recent }: Props) {
    const isEmpty = today.length === 0 && recent.length === 0;

    return (
        <>
            <Head title="Attendance" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Attendance
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Events scheduled for today or recently, ready for
                        attendance recording.
                    </p>
                </div>

                {isEmpty ? (
                    <EmptyState />
                ) : (
                    <>
                        {today.length > 0 && (
                            <section className="flex flex-col gap-3">
                                <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                                    Today
                                </h2>
                                <div className="flex flex-col gap-2">
                                    {today.map((event) => (
                                        <EventCard key={event.id} event={event} />
                                    ))}
                                </div>
                            </section>
                        )}

                        {recent.length > 0 && (
                            <section className="flex flex-col gap-3">
                                <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                                    Recent
                                </h2>
                                <div className="flex flex-col gap-2">
                                    {recent.map((event) => (
                                        <EventCard key={event.id} event={event} />
                                    ))}
                                </div>
                            </section>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

AttendanceIndex.layout = {
    breadcrumbs: [
        { title: 'Attendance', href: '/attendance' },
    ],
};
