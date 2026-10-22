import { Head, Link } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronRight,
    ClipboardCheck,
    MapPin,
    Search,
} from 'lucide-react';
import { useState } from 'react';
import EventKindToggle from '@/components/event-kind-toggle';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type EventRow = {
    id: number;
    event_name: string;
    event_date: string | null;
    venue: string | null;
    status: string;
    status_label: string;
    registered_count: number;
    marked_count: number;
    church_category: string | null;
    action_url: string;
};

type Props = {
    groups: {
        needsAttention?: EventRow[] | Record<string, EventRow>;
        open?: EventRow[] | Record<string, EventRow>;
        closed?: EventRow[] | Record<string, EventRow>;
    };
    kind: 'running' | 'church';
    categories: { value: string; label: string }[];
};

const statusStyles: Record<string, string> = {
    registration_open: 'bg-lime-brand/20 text-lime-brand',
    registration_closed:
        'bg-[#13253B] text-white dark:bg-yellow-500/15 dark:text-yellow-500',
    ongoing: 'bg-accent/20 text-accent',
    upcoming: 'bg-lime-brand/20 text-lime-brand',
    completed:
        'bg-[#13253B] text-white dark:bg-yellow-500/15 dark:text-yellow-500',
};

function EventCard({ event }: { event: EventRow }) {
    return (
        <Link
            href={event.action_url}
            className="glass-panel group hover:border-lime-brand/30 flex items-center gap-4 rounded-xl p-4 transition-colors"
        >
            <div className="flex flex-1 flex-col gap-1">
                <div className="flex items-center gap-3">
                    <h3 className="text-foreground text-sm font-semibold">
                        {event.event_name}
                    </h3>
                    <span
                        className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${
                            statusStyles[event.status] ??
                            statusStyles.registration_closed
                        }`}
                    >
                        {event.status_label}
                    </span>
                </div>
                {event.church_category && (
                    <span className="text-muted-foreground text-xs">
                        {event.church_category}
                    </span>
                )}

                <div className="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
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

                <div className="text-muted-foreground flex items-center gap-4 text-xs">
                    <span>
                        <span className="text-foreground font-medium">
                            {event.registered_count}
                        </span>{' '}
                        registered
                    </span>
                    <span>
                        <span className="text-foreground font-medium">
                            {event.marked_count}
                        </span>{' '}
                        marked
                    </span>
                </div>
            </div>

            <span className="text-lime-brand flex shrink-0 items-center gap-2 text-xs font-medium">
                Attendance
                <ChevronRight className="h-4 w-4 transition-colors" />
            </span>
        </Link>
    );
}

function EmptyState({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-12 text-center">
            <div className="bg-secondary mb-4 flex h-14 w-14 items-center justify-center rounded-full">
                <ClipboardCheck className="text-muted-foreground h-6 w-6" />
            </div>
            <h2 className="text-foreground text-base font-semibold">{title}</h2>
            <p className="text-muted-foreground mt-2 max-w-sm text-sm">
                {description}
            </p>
        </div>
    );
}

export default function AttendanceIndex({ groups, kind, categories }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedCategory, setSelectedCategory] = useState('');
    const isChurch = kind === 'church';
    const filterEvents = (events: EventRow[]) =>
        events.filter((event) => {
            const searchable =
                `${event.event_name} ${event.venue ?? ''}`.toLocaleLowerCase();
            return (
                (!isChurch || event.church_category === selectedCategory) &&
                searchable.includes(searchQuery.trim().toLocaleLowerCase())
            );
        });
    const toEventRows = (
        rows: EventRow[] | Record<string, EventRow> | undefined,
    ) => (Array.isArray(rows) ? rows : Object.values(rows ?? {}));
    const filteredNeedsAttention = filterEvents(
        toEventRows(groups.needsAttention),
    );
    const filteredOpen = filterEvents(toEventRows(groups.open));
    const filteredClosed = filterEvents(toEventRows(groups.closed));
    const hasCategory = !isChurch || selectedCategory !== '';
    const isEmpty =
        filteredNeedsAttention.length === 0 &&
        filteredOpen.length === 0 &&
        filteredClosed.length === 0;
    const sections: { title: string; events: EventRow[] }[] = [
        { title: 'Needs Attention', events: filteredNeedsAttention },
        { title: 'Open / Ongoing', events: filteredOpen },
        { title: 'Closed / Completed', events: filteredClosed },
    ];

    return (
        <>
            <Head title="Attendance" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-6">
                <header>
                    <h1 className="text-foreground text-2xl font-semibold">
                        Attendance
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Events ready for attendance tracking and event check-in.
                    </p>
                </header>
                <EventKindToggle kind={kind} baseUrl="/attendance" />
                <div className="flex flex-col gap-3 sm:flex-row">
                    <div className="relative w-full max-w-lg">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                        <Input
                            aria-label="Search events"
                            placeholder="Search event name or venue..."
                            value={searchQuery}
                            onChange={(event) =>
                                setSearchQuery(event.target.value)
                            }
                            className="pl-9"
                        />
                    </div>
                    {isChurch && (
                        <Select
                            value={selectedCategory}
                            onValueChange={setSelectedCategory}
                        >
                            <SelectTrigger
                                aria-label="Church Event Type"
                                className="w-full sm:w-64"
                            >
                                <SelectValue placeholder="Select a Church Event Type" />
                            </SelectTrigger>
                            <SelectContent>
                                {categories.map((category) => (
                                    <SelectItem
                                        key={category.value}
                                        value={category.value}
                                    >
                                        {category.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </div>

                {!hasCategory ? (
                    <EmptyState
                        title="Select a Church Event Type"
                        description="Choose a category to view its attendance events."
                    />
                ) : isEmpty ? (
                    <EmptyState
                        title={
                            searchQuery
                                ? 'No matching events'
                                : 'No events to record attendance for.'
                        }
                        description={
                            searchQuery
                                ? 'Try another event name or venue.'
                                : 'Events will appear here when they are ready for attendance.'
                        }
                    />
                ) : (
                    <>
                        {sections.map(({ title, events }) => {
                            return (
                                <section
                                    key={title}
                                    className="glass-panel rounded-xl p-6"
                                >
                                    <div className="mb-4 flex items-center justify-between">
                                        <h2 className="text-muted-foreground text-sm font-semibold tracking-wider uppercase">
                                            {title}
                                        </h2>
                                        <span className="bg-secondary text-muted-foreground rounded-full px-2 py-0.5 text-xs font-medium">
                                            {events.length}
                                        </span>
                                    </div>
                                    {events.length === 0 ? (
                                        <div className="border-border flex flex-col items-center justify-center rounded-lg border border-dashed p-8 text-center">
                                            <CalendarDays className="text-muted-foreground/40 mb-3 h-6 w-6" />
                                            <p className="text-muted-foreground text-sm">
                                                No events in this section.
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="flex flex-col gap-2">
                                            {events.map((event) => (
                                                <EventCard
                                                    key={event.id}
                                                    event={event}
                                                />
                                            ))}
                                        </div>
                                    )}
                                </section>
                            );
                        })}
                    </>
                )}
            </div>
        </>
    );
}

AttendanceIndex.layout = {
    breadcrumbs: [{ title: 'Attendance', href: '/attendance' }],
};
