import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Calendar, MapPin, User, CalendarDays } from 'lucide-react';
import ChurchCategoryCards from '@/components/church-category-cards';
import { Button } from '@/components/ui/button';

type EventRow = {
    id: number;
    event_kind: string;
    event_name: string;
    event_type: string;
    event_type_label: string;
    church_category: string | null;
    church_activity_category: string | null;
    event_date: string | null;
    venue: string | null;
    distance_label: string | null;
    status: string;
    status_label: string;
    creator: string | null;
};

type Filters = {
    kind: string | null;
    date?: string | null;
    month?: string | null;
    church_category?: string | null;
    church_activity?: string | null;
};

type Props = {
    events: EventRow[];
    filters: Filters;
};

const statusStyles: Record<string, string> = {
    draft: 'bg-secondary text-foreground',
    registration_open: 'bg-lime-brand/20 text-lime-700 dark:text-lime-brand',
    registration_closed:
        'bg-[#13253B] text-white dark:bg-yellow-500/15 dark:text-yellow-500',
    upcoming: 'bg-lime-brand/20 text-lime-700 dark:text-lime-brand',
    ongoing: 'bg-accent/20 text-teal-700 dark:text-accent',
    completed: 'bg-green-500/15 text-green-700 dark:text-green-500',
    cancelled: 'bg-destructive/15 text-destructive',
};

const typeStyles: Record<string, string> = {
    community_run: 'bg-accent/15 text-teal-700 dark:text-accent',
    church: 'bg-lime-brand/15 text-lime-700 dark:text-lime-brand',
};

const KIND_FILTERS = [
    { value: 'running', label: 'Running' },
    { value: 'church', label: 'Church' },
] as const;

const STATUS_FILTERS = [
    { value: 'all', label: 'All' },
    { value: 'draft', label: 'Draft' },
    { value: 'registration_open', label: 'Registration Open' },
    { value: 'registration_closed', label: 'Registration Closed' },
    { value: 'upcoming', label: 'Upcoming' },
    { value: 'ongoing', label: 'Ongoing' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
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

    const isChurchTab = filters.kind === 'church';
    const selectedChurchCategory = isChurchTab
        ? (filters.church_category ?? null)
        : null;
    const selectedChurchActivity = isChurchTab
        ? (filters.church_activity ?? null)
        : null;
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] =
        useState<(typeof STATUS_FILTERS)[number]['value']>('all');
    const visibleEvents = events.filter((event) => {
        const searchTerm = search.trim().toLowerCase();
        const searchableValues = [
            event.event_name,
            event.event_type_label,
            event.event_date,
            event.distance_label,
            event.venue,
            event.creator,
        ].map((value) => String(value ?? '').toLowerCase());

        return (
            searchableValues.some((value) => value.includes(searchTerm)) &&
            (statusFilter === 'all' || event.status === statusFilter)
        );
    });
    const visibleChurchEvents = visibleEvents.filter(
        (event) =>
            (!selectedChurchCategory ||
                event.church_category === selectedChurchCategory) &&
            (!selectedChurchActivity ||
                event.church_activity_category === selectedChurchActivity),
    );
    const churchCreateSlugs: Record<string, string> = {
        Services: 'services',
        Fellowship: 'fellowship',
        Discipleship: 'discipleship',
    };
    const activityCreateSlugs: Record<string, string> = {
        'Sunday Service': 'sunday-service',
        'Special Service': 'special-service',
        'Church Fellowship': 'church-fellowship',
        'Ministry Fellowship': 'ministry-fellowship',
        'Bible Study': 'bible-study',
        'Discipleship Class': 'discipleship-class',
        'Leadership Training': 'leadership-training',
    };
    const createEventHref = selectedChurchCategory
        ? churchCreateSlugs[selectedChurchCategory]
            ? `/events/create/church/${churchCreateSlugs[selectedChurchCategory]}${
                  selectedChurchActivity
                      ? `/${activityCreateSlugs[selectedChurchActivity]}`
                      : ''
              }`
            : '/events/create/church'
        : isChurchTab
          ? '/events/create/church'
          : '/events/create';

    return (
        <>
            <Head
                title={
                    selectedChurchCategory
                        ? `${selectedChurchCategory} Events`
                        : 'Events'
                }
            />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="pl-10">
                        <h1 className="text-foreground text-2xl font-semibold">
                            Events
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {selectedChurchCategory
                                ? selectedChurchActivity
                                    ? `${selectedChurchCategory} · ${selectedChurchActivity}`
                                    : selectedChurchCategory
                                : isChurchTab
                                  ? 'Pick a church event category to see its events'
                                  : 'Running events — registration, attendance, and reporting'}
                        </p>
                    </div>

                    <Button
                        asChild
                        className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                    >
                        <Link href={createEventHref}>
                            <Plus className="mr-2 h-4 w-4" />
                            {selectedChurchCategory
                                ? 'Add Event'
                                : 'Create Event'}
                        </Link>
                    </Button>
                </div>

                {(filters.date ||
                    filters.month ||
                    filters.church_category ||
                    filters.church_activity) && (
                    <p className="text-muted-foreground text-sm" role="status">
                        Showing {filters.church_category ?? 'all categories'}
                        {filters.church_activity
                            ? ` · ${filters.church_activity}`
                            : ''}
                        {filters.date ? ` on ${filters.date}` : ''}
                        {filters.month ? ` during ${filters.month}` : ''}.
                        <Link
                            href={
                                filters.kind === 'church'
                                    ? '/events?kind=church'
                                    : '/events?kind=running'
                            }
                            className="ml-2 underline"
                        >
                            Clear filters
                        </Link>
                    </p>
                )}

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
                                        ? 'border-lime-brand/40 bg-lime-brand/25 dark:bg-lime-brand/20 dark:text-lime-brand text-lime-700'
                                        : 'border-border bg-card/70 text-foreground hover:bg-card'
                                }`}
                            >
                                {filter.label}
                            </button>
                        );
                    })}
                </div>

                {/* Church tab: category cards */}
                {isChurchTab && !selectedChurchCategory && (
                    <ChurchCategoryCards />
                )}

                {isChurchTab && selectedChurchCategory && (
                    <section
                        className="space-y-4"
                        aria-label={`${selectedChurchCategory} events`}
                    >
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <Input
                                aria-label={`Search ${selectedChurchCategory} events`}
                                placeholder="Search by event name or venue…"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                            <Link
                                href="/events?kind=church"
                                className="text-muted-foreground hover:text-foreground text-sm underline"
                            >
                                All Church Categories
                            </Link>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {visibleChurchEvents.length} matching events
                        </p>
                        {visibleChurchEvents.length > 0 ? (
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {visibleChurchEvents.map((event) => (
                                    <Link
                                        key={event.id}
                                        href={`/events/${event.id}`}
                                        className="glass-panel glass-panel-hover rounded-xl p-5"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <h2 className="text-foreground font-semibold">
                                                {event.event_name}
                                            </h2>
                                            <span
                                                className={`inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-medium ${
                                                    statusStyles[
                                                        event.status
                                                    ] ??
                                                    'bg-secondary text-foreground'
                                                }`}
                                            >
                                                {event.status_label}
                                            </span>
                                        </div>
                                        <p className="text-muted-foreground mt-3 flex items-center gap-2 text-sm">
                                            <Calendar className="h-4 w-4" />
                                            {event.event_date ?? 'Date not set'}
                                        </p>
                                        <p className="text-muted-foreground mt-1 flex items-center gap-2 text-sm">
                                            <MapPin className="h-4 w-4" />
                                            {event.venue ?? 'Venue not set'}
                                        </p>
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-12 text-center">
                                <div className="bg-lime-brand/15 mb-4 flex h-14 w-14 items-center justify-center rounded-full">
                                    <CalendarDays className="dark:text-lime-brand h-7 w-7 text-lime-700" />
                                </div>
                                <h2 className="text-foreground font-semibold">
                                    No events found
                                </h2>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Add an event or adjust your search.
                                </p>
                            </div>
                        )}
                    </section>
                )}

                {isChurchTab &&
                    !selectedChurchCategory &&
                    (filters.date || filters.month) && (
                        <section
                            className="glass-panel overflow-hidden rounded-xl"
                            aria-label="Filtered church events"
                        >
                            <h2 className="px-5 pt-5 text-sm font-semibold">
                                Matching Church Events
                            </h2>
                            {events.length ? (
                                <ul className="divide-border divide-y px-5 pb-3">
                                    {events.map((event) => (
                                        <li
                                            key={event.id}
                                            className="flex flex-wrap items-center justify-between gap-3 py-3"
                                        >
                                            <Link
                                                href={`/events/${event.id}`}
                                                className="font-medium underline"
                                            >
                                                {event.event_name}
                                            </Link>
                                            <span className="text-muted-foreground text-sm">
                                                {event.church_category} ·{' '}
                                                {event.event_date ??
                                                    'Date not set'}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-muted-foreground px-5 py-4 text-sm">
                                    No Church Events match these filters.
                                </p>
                            )}
                        </section>
                    )}

                {!isChurchTab && (
                    <section
                        className="space-y-3"
                        aria-label="Filter running events"
                    >
                        <Input
                            aria-label="Search running events"
                            placeholder="Search running events…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                        <div
                            className="flex flex-wrap gap-2"
                            role="group"
                            aria-label="Filter events by status"
                        >
                            {STATUS_FILTERS.map((filter) => {
                                const active = statusFilter === filter.value;

                                return (
                                    <button
                                        key={filter.value}
                                        type="button"
                                        aria-pressed={active}
                                        onClick={() =>
                                            setStatusFilter(filter.value)
                                        }
                                        className={`inline-flex items-center rounded-full border px-3 py-1.5 text-sm font-medium transition-colors ${
                                            active
                                                ? 'border-lime-brand bg-lime-brand text-navy-900'
                                                : 'border-border bg-card text-foreground hover:bg-muted'
                                        }`}
                                    >
                                        {filter.label}
                                    </button>
                                );
                            })}
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {visibleEvents.length} matching events
                        </p>
                    </section>
                )}
                {!isChurchTab && visibleEvents.length === 0 && (
                    <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-16 text-center">
                        <div className="bg-lime-brand/15 mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                            <CalendarDays className="dark:text-lime-brand h-8 w-8 text-lime-700" />
                        </div>
                        <h2 className="text-foreground mb-2 text-lg font-semibold">
                            No events yet
                        </h2>
                        <p className="text-muted-foreground mb-6 max-w-sm text-sm">
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
                )}

                {!isChurchTab && visibleEvents.length > 0 && (
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
                                            Distance
                                        </th>
                                        <th className="text-muted-foreground px-6 py-3 text-xs font-semibold tracking-wider uppercase">
                                            Venue
                                        </th>
                                        <th className="text-muted-foreground px-6 py-3 text-xs font-semibold tracking-wider uppercase">
                                            Status
                                        </th>
                                        <th className="text-muted-foreground px-6 py-3 text-xs font-semibold tracking-wider uppercase">
                                            Created by
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {visibleEvents.map((event) => (
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
                                                            event.event_type
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
                                                {event.distance_label ?? '—'}
                                            </td>
                                            <td className="text-muted-foreground px-6 py-4 text-sm">
                                                <span className="inline-flex items-center gap-2">
                                                    <MapPin className="h-3.5 w-3.5" />
                                                    {event.venue ?? '—'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${
                                                        statusStyles[
                                                            event.status
                                                        ] ?? statusStyles.draft
                                                    }`}
                                                >
                                                    {event.status_label}
                                                </span>
                                            </td>
                                            <td className="text-muted-foreground px-6 py-4 text-sm">
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
    breadcrumbs: [{ title: 'Events', href: '/events' }],
};
