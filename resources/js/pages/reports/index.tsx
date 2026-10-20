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
};

const typeStyles: Record<string, string> = {
    community_run: 'bg-accent/15 text-teal-700 dark:text-accent',
    church: 'bg-lime-brand/15 text-lime-700 dark:text-lime-brand',
};

export default function ReportsIndex({ events }: Props) {
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
                </div>

                {events.length === 0 ? (
                    <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-16 text-center">
                        <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-lime-brand/15">
                            <BarChart3 className="h-8 w-8 text-lime-700 dark:text-lime-brand" />
                        </div>
                        <h2 className="mb-2 text-lg font-semibold text-foreground">
                            No completed events yet
                        </h2>
                        <p className="max-w-sm text-sm text-muted-foreground">
                            Reports appear here once an event has been
                            finalized.
                        </p>
                    </div>
                ) : (
                    <div className="glass-panel overflow-hidden rounded-xl">
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-border text-left">
                                        <th className="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                            Event
                                        </th>
                                        <th className="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                            Type
                                        </th>
                                        <th className="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                            Date
                                        </th>
                                        <th className="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                            Venue
                                        </th>
                                        <th className="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                            Created by
                                        </th>
                                        <th className="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                            Report
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {events.map((event) => (
                                        <tr
                                            key={event.id}
                                            className="border-b border-border transition-colors last:border-b-0 hover:bg-card/40"
                                        >
                                            <td className="px-6 py-4">
                                                <Link
                                                    href={`/events/${event.id}`}
                                                    className="font-medium text-foreground hover:text-lime-brand"
                                                >
                                                    {event.event_name}
                                                </Link>
                                                {event.church_category && (
                                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                                        {event.church_category}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-6 py-4">
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${
                                                        typeStyles[
                                                            event.event_kind === 'church'
                                                                ? 'church'
                                                                : 'community_run'
                                                        ] ??
                                                        'bg-secondary text-foreground'
                                                    }`}
                                                >
                                                    {event.event_type_label}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-sm text-muted-foreground">
                                                <span className="inline-flex items-center gap-2">
                                                    <Calendar className="h-3.5 w-3.5" />
                                                    {event.event_date ?? '—'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-sm text-muted-foreground">
                                                <span className="inline-flex items-center gap-2">
                                                    <MapPin className="h-3.5 w-3.5" />
                                                    {event.venue ?? '—'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-sm text-muted-foreground">
                                                <span className="inline-flex items-center gap-2">
                                                    <User className="h-3.5 w-3.5" />
                                                    {event.creator ?? '—'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <Link
                                                    href={event.event_kind === 'running' ? `/events/${event.id}/report` : `/events/${event.id}`}
                                                    className="text-sm font-medium text-lime-700 hover:text-lime-800 dark:text-lime-brand dark:hover:text-lime-brand/80"
                                                >
                                                    {event.event_kind === 'running' ? 'View report →' : 'View church event →'}
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
