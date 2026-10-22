import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, History } from 'lucide-react';

type HistoryEvent = {
    event_name: string;
    event_type: string;
    event_date: string | null;
    attended_at: string | null;
};

type Props = {
    participant: { id: number; full_name: string };
    attendance_count: number;
    event_types: string[];
    events: HistoryEvent[];
};

function formatDate(value: string | null): string {
    if (!value) return 'Date not recorded';
    return new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString(
        undefined,
        { year: 'numeric', month: 'short', day: 'numeric' },
    );
}

export default function ChurchParticipantHistory({
    participant,
    attendance_count,
    event_types,
    events,
}: Props) {
    return (
        <>
            <Head title={`${participant.full_name} — Church History`} />
            <main className="mx-auto w-full max-w-4xl space-y-6 p-6">
                <Link
                    href="/events?kind=church"
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-2 text-sm"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Church events
                </Link>

                <header className="glass-panel rounded-xl p-6">
                    <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                        Church participant history
                    </p>
                    <h1 className="mt-2 text-2xl font-semibold">
                        {participant.full_name}
                    </h1>
                    <div className="mt-4 flex flex-wrap gap-3">
                        <div className="bg-card rounded-lg border px-4 py-3">
                            <p className="text-muted-foreground text-xs">
                                Attendances
                            </p>
                            <p className="text-2xl font-semibold">
                                {attendance_count}
                            </p>
                        </div>
                        <div className="bg-card min-w-48 flex-1 rounded-lg border px-4 py-3">
                            <p className="text-muted-foreground text-xs">
                                Event types attended
                            </p>
                            <p className="mt-1 text-sm font-medium">
                                {event_types.length > 0
                                    ? event_types.join(', ')
                                    : 'None recorded yet'}
                            </p>
                        </div>
                    </div>
                </header>

                <section className="glass-panel rounded-xl p-6">
                    <div className="mb-4 flex items-center gap-2">
                        <History className="h-4 w-4 text-violet-400" />
                        <h2 className="text-sm font-semibold tracking-wider uppercase">
                            Attendance history
                        </h2>
                    </div>
                    {events.length === 0 ? (
                        <p className="text-muted-foreground py-6 text-center text-sm">
                            No attended church events have been recorded yet.
                        </p>
                    ) : (
                        <ol className="divide-border divide-y">
                            {events.map((event, index) => (
                                <li
                                    key={`${event.event_type}-${event.event_date}-${event.event_name}-${index}`}
                                    className="flex flex-col gap-2 py-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {event.event_name}
                                        </p>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            {event.event_type}
                                        </p>
                                    </div>
                                    <span className="text-muted-foreground inline-flex items-center gap-1.5 text-sm">
                                        <CalendarDays className="h-3.5 w-3.5" />
                                        {formatDate(event.event_date)}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
            </main>
        </>
    );
}

ChurchParticipantHistory.layout = {
    breadcrumbs: [{ title: 'Church participant history', href: '#' }],
};
