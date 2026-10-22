import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { BarChart3, Calendar, MapPin, Search, User } from 'lucide-react';
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
    event_kind: string;
    event_name: string;
    event_type_label: string;
    church_category: string | null;
    event_date: string | null;
    venue: string | null;
    creator: string | null;
    registered_count: number | null;
    report_url: string;
};

type Props = {
    events: EventRow[];
    activeSide: 'running' | 'church';
    categories: { value: string; label: string }[];
};

const typeStyles: Record<string, string> = {
    community_run: 'bg-accent/15 text-teal-700 dark:text-accent',
    church: 'bg-lime-brand/15 text-lime-700 dark:text-lime-brand',
};

export default function ReportsIndex({
    events,
    activeSide,
    categories,
}: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedCategory, setSelectedCategory] = useState('');
    const isChurch = activeSide === 'church';
    const filteredEvents = events.filter((event) => {
        const matchesCategory =
            !isChurch || event.church_category === selectedCategory;
        const matchesSearch = [
            event.event_name,
            event.event_type_label,
            event.church_category,
            event.venue,
            event.creator,
        ].some((value) =>
            value
                ?.toLocaleLowerCase()
                .includes(searchQuery.trim().toLocaleLowerCase()),
        );
        return matchesCategory && matchesSearch;
    });
    const categorySelected = !isChurch || selectedCategory !== '';

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

                <EventKindToggle kind={activeSide} baseUrl="/reports" />
                <div className="flex flex-col gap-3 sm:flex-row">
                    <div className="relative max-w-lg">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                        <Input
                            aria-label="Search reports"
                            placeholder="Search event, category, venue, or organizer..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
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

                {!categorySelected ? (
                    <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-16 text-center">
                        <div className="bg-lime-brand/15 mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                            <BarChart3 className="dark:text-lime-brand h-8 w-8 text-lime-700" />
                        </div>
                        <h2 className="text-foreground mb-2 text-lg font-semibold">
                            Select a Church Event Type
                        </h2>
                        <p className="text-muted-foreground max-w-sm text-sm">
                            Choose a category to view its completed event
                            reports.
                        </p>
                    </div>
                ) : events.length === 0 ? (
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
                ) : filteredEvents.length === 0 ? (
                    <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-16 text-center">
                        <div className="bg-lime-brand/15 mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                            <Search className="dark:text-lime-brand h-8 w-8 text-lime-700" />
                        </div>
                        <h2 className="text-foreground mb-2 text-lg font-semibold">
                            No matching reports
                        </h2>
                        <p className="text-muted-foreground max-w-sm text-sm">
                            Try another event name, category, venue, or
                            organizer.
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
                                            Registered
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
                                    {filteredEvents.map((event) => (
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
                                                {event.registered_count ?? '—'}
                                            </td>
                                            <td className="text-muted-foreground px-6 py-4 text-sm">
                                                <span className="inline-flex items-center gap-2">
                                                    <User className="h-3.5 w-3.5" />
                                                    {event.creator ?? '—'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <Link
                                                    href={event.report_url}
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
