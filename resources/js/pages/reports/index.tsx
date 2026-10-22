import { Head, Link } from '@inertiajs/react';
import { BarChart3, Calendar, MapPin, User } from 'lucide-react';

type EventRow = {
    id: number;
    event_kind: string;
    event_name: string;
    event_type_label: string;
    church_category: string | null;
    event_date: string | null;
    venue: string | null;
    creator: string | null;
};

type Props = {
    events: EventRow[];
    activeSide: 'running' | 'church' | null;
};

const typeStyles: Record<string, string> = {
    community_run: 'bg-accent/15 text-teal-700 dark:text-accent',
    church: 'bg-lime-brand/15 text-lime-700 dark:text-lime-brand',
};

export default function ReportsIndex({ events, activeSide }: Props) {
    return (
        <>
            <Head title="Reports" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6">
                <div className="pl-10">
                    <h1 className="text-foreground text-2xl font-semibold">
                        Reports
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        End-of-event reports for every completed event
                    </p>
                    <nav
                        aria-label="Filter reports by side"
                        className="mt-4 flex gap-2"
                    >
                        <Link
                            className={`rounded-full border px-3 py-1 text-xs ${activeSide === 'running' ? 'border-lime-brand text-lime-brand' : 'border-border text-muted-foreground'}`}
                            href="/reports?kind=running"
                        >
                            Running
                        </Link>
                        <Link
                            className={`rounded-full border px-3 py-1 text-xs ${activeSide === 'church' ? 'border-lime-brand dark:text-lime-brand text-[#3f6212]' : 'border-border text-muted-foreground'}`}
                            href="/reports?kind=church"
                        >
                            Church
                        </Link>
                        <Link
                            className={`rounded-full border px-3 py-1 text-xs ${activeSide === null ? 'border-foreground text-foreground' : 'border-border text-muted-foreground'}`}
                            href="/reports"
                        >
                            All
                        </Link>
                    </nav>
                </div>

                {events.length === 0 ? (
                    <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-16 text-center">
                        <div className="bg-lime-brand/15 mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                            <BarChart3 className="dark:text-lime-brand h-8 w-8 text-lime-700" />
                        </div>
                        <h2 className="text-foreground mb-2 text-lg font-semibold">
                            No completed events yet
                        </h2>
                        <p className="text-muted-foreground max-w-sm text-sm">
                            Reports appear here once an event has been
                            finalized.
                        </p>
                    </div>
                ) : (
                    <div className="glass-panel overflow-hidden rounded-xl">
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-border border-b text-left">
                                        <th className="text-muted-foreground px-6 py-3 text-xs font-semibold tracking-wider uppercase">
                                            Event
                                        </th>
                                        <th className="text-muted-foreground px-6 py-3 text-xs font-semibold tracking-wider uppercase">
                                            Type
                                        </th>
                                        <th className="text-muted-foreground px-6 py-3 text-xs font-semibold tracking-wider uppercase">
                                            Date
                                        </th>
                                        <th className="text-muted-foreground px-6 py-3 text-xs font-semibold tracking-wider uppercase">
                                            Venue
                                        </th>
                                        <th className="text-muted-foreground px-6 py-3 text-xs font-semibold tracking-wider uppercase">
                                            Created by
                                        </th>
                                        <th className="text-muted-foreground px-6 py-3 text-xs font-semibold tracking-wider uppercase">
                                            Report
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {events.map((event) => (
                                        <tr
                                            key={event.id}
                                            className="border-border hover:bg-card/40 border-b transition-colors last:border-b-0"
                                        >
                                            <td className="px-6 py-4">
                                                <Link
                                                    href={`/events/${event.id}`}
                                                    className="text-foreground hover:text-lime-brand font-medium"
                                                >
                                                    {event.event_name}
                                                </Link>
                                                {event.church_category && (
                                                    <p className="text-muted-foreground mt-0.5 text-xs">
                                                        {event.church_category}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-6 py-4">
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${
                                                        typeStyles[
                                                            event.event_kind ===
                                                            'church'
                                                                ? 'church'
                                                                : 'community_run'
                                                        ] ??
                                                        'bg-secondary text-foreground'
                                                    }`}
                                                >
                                                    {event.event_type_label}
                                                </span>
                                            </td>
                                            <td className="text-muted-foreground px-6 py-4 text-sm">
                                                <span className="inline-flex items-center gap-2">
                                                    <Calendar className="h-3.5 w-3.5" />
                                                    {event.event_date ?? '—'}
                                                </span>
                                            </td>
                                            <td className="text-muted-foreground px-6 py-4 text-sm">
                                                <span className="inline-flex items-center gap-2">
                                                    <MapPin className="h-3.5 w-3.5" />
                                                    {event.venue ?? '—'}
                                                </span>
                                            </td>
                                            <td className="text-muted-foreground px-6 py-4 text-sm">
                                                <span className="inline-flex items-center gap-2">
                                                    <User className="h-3.5 w-3.5" />
                                                    {event.creator ?? '—'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <Link
                                                    href={
                                                        event.event_kind ===
                                                        'running'
                                                            ? `/events/${event.id}/report`
                                                            : `/events/${event.id}`
                                                    }
                                                    className="dark:text-lime-brand dark:hover:text-lime-brand/80 text-sm font-medium text-lime-700 hover:text-lime-800"
                                                >
                                                    {event.event_kind ===
                                                    'running'
                                                        ? 'View report →'
                                                        : 'View church event →'}
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Reports', href: '/reports' }],
};
