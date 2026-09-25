import { Head, Link } from '@inertiajs/react';
import { Plus, Calendar, MapPin, User, CalendarDays } from 'lucide-react';
import { Button } from '@/components/ui/button';

type EventRow = {
    id: number;
    event_name: string;
    event_type: string;
    event_type_label: string;
    event_date: string | null;
    venue: string | null;
    distance_label: string | null;
    status: string;
    status_label: string;
    creator: string | null;
};

type Props = {
    events: EventRow[];
};

const statusStyles: Record<string, string> = {
    draft: 'bg-secondary text-foreground',
    registration_open: 'bg-lime-brand/20 text-lime-brand',
    registration_closed: 'bg-yellow-500/15 text-yellow-500',
    ongoing: 'bg-accent/20 text-accent',
    completed: 'bg-green-500/15 text-green-500',
    cancelled: 'bg-destructive/15 text-destructive',
};

const typeStyles: Record<string, string> = {
    community_run: 'bg-accent/15 text-accent',
    fun_run: 'bg-secondary text-foreground',
};

export default function EventsIndex({ events }: Props) {
    return (
        <>
            <Head title="Events" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Events
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            All events across the WDEMS workflow
                        </p>
                    </div>

                    <Button
                        asChild
                        className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                    >
                        <Link href="/events/create">
                            <Plus className="mr-2 h-4 w-4" />
                            Create Event
                        </Link>
                    </Button>
                </div>

                {/* Empty state */}
                {events.length === 0 ? (
                    <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-16 text-center">
                        <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-lime-brand/15">
                            <CalendarDays className="h-8 w-8 text-lime-brand" />
                        </div>
                        <h2 className="mb-2 text-lg font-semibold text-foreground">
                            No events yet
                        </h2>
                        <p className="mb-6 max-w-sm text-sm text-muted-foreground">
                            Create your first running or community event to
                            begin configuring registration and attendance.
                        </p>
                        <Button
                            asChild
                            className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                        >
                            <Link href="/events/create">
                                <Plus className="mr-2 h-4 w-4" />
                                Create First Event
                            </Link>
                        </Button>
                    </div>
                ) : (
                    /* Table */
                    <div className="glass-panel overflow-hidden rounded-xl">
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-white/10 text-left">
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
                                            Distance
                                        </th>
                                        <th className="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                            Venue
                                        </th>
                                        <th className="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                            Status
                                        </th>
                                        <th className="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                            Created by
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {events.map((event) => (
                                        <tr
                                            key={event.id}
                                            className="border-b border-white/5 transition-colors last:border-b-0 hover:bg-white/5"
                                        >
                                            <td className="px-6 py-4">
                                                <Link
                                                    href={`/events/${event.id}`}
                                                    className="font-medium text-foreground hover:text-lime-brand"
                                                >
                                                    {event.event_name}
                                                </Link>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${
                                                        typeStyles[event.event_type] ??
                                                        typeStyles.fun_run
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
                                                {event.distance_label ?? '—'}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-muted-foreground">
                                                <span className="inline-flex items-center gap-2">
                                                    <MapPin className="h-3.5 w-3.5" />
                                                    {event.venue ?? '—'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${
                                                        statusStyles[event.status] ??
                                                        statusStyles.draft
                                                    }`}
                                                >
                                                    {event.status_label}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-sm text-muted-foreground">
                                                <span className="inline-flex items-center gap-2">
                                                    <User className="h-3.5 w-3.5" />
                                                    {event.creator ?? '—'}
                                                </span>
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

EventsIndex.layout = {
    breadcrumbs: [
        { title: 'Events', href: '/events' },
    ],
};
