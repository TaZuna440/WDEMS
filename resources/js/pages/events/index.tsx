import { Head, Link, router } from '@inertiajs/react';
import { Plus, Calendar, MapPin, User, CalendarDays } from 'lucide-react';
import { Button } from '@/components/ui/button';

type EventRow = {
    id: number;
    event_kind: string;
    event_name: string;
    event_type: string;
    event_type_label: string;
    church_category: string | null;
    event_date: string | null;
    venue: string | null;
    distance_label: string | null;
    status: string;
    status_label: string;
    creator: string | null;
};

type Filters = {
    kind: string | null;
};

type Props = {
    events: EventRow[];
    filters: Filters;
};

const statusStyles: Record<string, string> = {
    // Running-event statuses
    draft: 'bg-secondary text-foreground',
    registration_open: 'bg-lime-brand/20 text-lime-700 dark:text-lime-brand',
    registration_closed:
        'bg-[#13253B] text-white dark:bg-yellow-500/15 dark:text-yellow-500',
    // Church-event statuses. 'ongoing', 'completed', 'cancelled' are
    // shared with the running-event vocabulary and already have
    // entries below.
    upcoming: 'bg-lime-brand/20 text-lime-700 dark:text-lime-brand',
    // Shared
    ongoing: 'bg-accent/20 text-teal-700 dark:text-accent',
    completed: 'bg-green-500/15 text-green-700 dark:text-green-500',
    cancelled: 'bg-destructive/15 text-destructive',
};

const typeStyles: Record<string, string> = {
    community_run: 'bg-accent/15 text-teal-700 dark:text-accent',
    church: 'bg-lime-brand/15 text-lime-700 dark:text-lime-brand',
};

const KIND_FILTERS = [
    { value: null, label: 'All' },
    { value: 'running', label: 'Running' },
    { value: 'church', label: 'Church' },
] as const;

export default function EventsIndex({ events, filters }: Props) {
    const applyKind = (kind: string | null) => {
        const query = kind ? { kind } : {};
        router.get('/events', query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title="Events" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="pl-10">
                        <h1 className="text-on-image text-2xl font-semibold">
                            Events
                        </h1>
                        <p className="text-on-image-muted mt-1 text-sm">
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

                {/* Kind filter */}
                <div className="flex flex-wrap items-center gap-2">
                    {KIND_FILTERS.map((filter) => {
                        const active = filters.kind === filter.value;
                        return (
                            <button
                                key={filter.label}
                                type="button"
                                onClick={() => applyKind(filter.value)}
                                className={`inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium transition-colors ${
                                    active
                                        ? 'border-lime-brand/40 bg-lime-brand/25 text-lime-700 dark:bg-lime-brand/20 dark:text-lime-brand'
                                        : 'border-border bg-card/70 text-foreground hover:bg-card'
                                }`}
                            >
                                {filter.label}
                            </button>
                        );
                    })}
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
                            {filters.kind
                                ? 'No events match this filter.'
                                : 'Create your first running or community event to begin configuring registration and attendance.'}
                        </p>
                        {!filters.kind && (
                            <Button
                                asChild
                                className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                            >
                                <Link href="/events/create">
                                    <Plus className="mr-2 h-4 w-4" />
                                    Create First Event
                                </Link>
                            </Button>
                        )}
                    </div>
                ) : (
                    /* Table */
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
                                                        typeStyles[event.event_type] ??
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
