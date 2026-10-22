import { Head, Link } from '@inertiajs/react';
import { CalendarDays, CircleCheck, ClipboardList, Search } from 'lucide-react';
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
    creator: string | null;
    church_category: string | null;
    action_url: string;
};

type Props = {
    needsForm: EventRow[];
    open: EventRow[];
    closed: EventRow[];
    kind: 'running' | 'church';
    categories: { value: string; label: string }[];
};

function EmptySection({
    icon: Icon,
    title,
    description,
}: {
    icon: React.ComponentType<{ className?: string }>;
    title: string;
    description: string;
}) {
    return (
        <div className="border-border flex flex-col items-center justify-center rounded-lg border border-dashed p-8 text-center">
            <Icon className="text-muted-foreground/40 mb-3 h-6 w-6" />
            <p className="text-foreground text-sm">{title}</p>
            <p className="text-muted-foreground mt-1 max-w-xs text-xs">
                {description}
            </p>
        </div>
    );
}

function EventRowItem({
    event,
    showMonitor = false,
}: {
    event: EventRow;
    showMonitor?: boolean;
}) {
    return (
        <div className="border-border bg-card/40 hover:bg-card/70 flex items-center justify-between gap-4 rounded-lg border px-4 py-3 transition-colors">
            <Link
                href={event.action_url}
                className="flex min-w-0 flex-1 flex-col"
            >
                <span className="text-foreground truncate text-sm font-medium">
                    {event.event_name}
                </span>
                <span className="text-muted-foreground mt-0.5 truncate text-xs">
                    {event.event_date ?? '—'} · {event.venue ?? 'No venue'}
                </span>
                {event.church_category && (
                    <span className="text-muted-foreground mt-1 text-xs">
                        {event.church_category}
                    </span>
                )}
            </Link>

            <div className="flex shrink-0 items-center gap-3">
                <span className="text-muted-foreground text-xs">
                    {event.status_label}
                </span>

                {showMonitor && (
                    <Link
                        href={event.action_url}
                        className="text-lime-brand inline-flex items-center gap-1 text-xs font-medium hover:underline"
                    >
                        Monitor
                    </Link>
                )}
            </div>
        </div>
    );
}

function Section({
    title,
    count,
    children,
}: {
    title: string;
    count: number;
    children: React.ReactNode;
}) {
    return (
        <div className="glass-panel rounded-xl p-6">
            <div className="mb-4 flex items-center justify-between">
                <h2 className="text-muted-foreground text-sm font-semibold tracking-wider uppercase">
                    {title}
                </h2>
                <span className="bg-secondary text-muted-foreground rounded-full px-2 py-0.5 text-xs font-medium">
                    {count}
                </span>
            </div>
            {children}
        </div>
    );
}

export default function RegistrationsIndex({
    needsForm,
    open,
    closed,
    kind,
    categories,
}: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedCategory, setSelectedCategory] = useState('');
    const isChurch = kind === 'church';
    const filterEvents = (events: EventRow[]) =>
        events.filter((event) => {
            const matchesCategory =
                !isChurch || event.church_category === selectedCategory;
            const searchable =
                `${event.event_name} ${event.venue ?? ''}`.toLocaleLowerCase();
            return (
                matchesCategory &&
                searchable.includes(searchQuery.trim().toLocaleLowerCase())
            );
        });
    const filteredNeedsForm = filterEvents(needsForm);
    const filteredOpen = filterEvents(open);
    const filteredClosed = filterEvents(closed);
    const hasCategory = !isChurch || selectedCategory !== '';

    return (
        <>
            <Head title="Registration" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-6">
                <header className="pl-10">
                    <h1 className="text-foreground text-2xl font-semibold">
                        Registration
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Build registration forms, publish them, and manage
                        incoming submissions.
                    </p>
                </header>
                <EventKindToggle kind={kind} baseUrl="/registrations" />
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
                    <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-12 text-center">
                        <div className="bg-lime-brand/15 mb-4 flex h-14 w-14 items-center justify-center rounded-full">
                            <CalendarDays className="text-lime-brand h-7 w-7" />
                        </div>
                        <h2 className="text-foreground text-lg font-semibold">
                            Select a Church Event Type
                        </h2>
                        <p className="text-muted-foreground mt-1 max-w-sm text-sm">
                            Choose a category to view its registration workflow.
                        </p>
                    </div>
                ) : (
                    <>
                        <Section
                            title="Needs Form"
                            count={filteredNeedsForm.length}
                        >
                            {filteredNeedsForm.length === 0 ? (
                                <EmptySection
                                    icon={CircleCheck}
                                    title="All events have a form."
                                    description="Nothing to do here. Create a new event to start the registration workflow."
                                />
                            ) : (
                                <div className="flex flex-col gap-2">
                                    {filteredNeedsForm.map((event) => (
                                        <EventRowItem
                                            key={event.id}
                                            event={event}
                                        />
                                    ))}
                                </div>
                            )}
                        </Section>

                        <Section
                            title="Open / Ongoing"
                            count={filteredOpen.length}
                        >
                            {filteredOpen.length === 0 ? (
                                <EmptySection
                                    icon={ClipboardList}
                                    title="No open registrations."
                                    description="Once an event's form is ready, open registration to publish the public link."
                                />
                            ) : (
                                <div className="flex flex-col gap-2">
                                    {filteredOpen.map((event) => (
                                        <EventRowItem
                                            key={event.id}
                                            event={event}
                                            showMonitor
                                        />
                                    ))}
                                </div>
                            )}
                        </Section>

                        <Section
                            title="Closed / Completed"
                            count={filteredClosed.length}
                        >
                            {filteredClosed.length === 0 ? (
                                <EmptySection
                                    icon={CalendarDays}
                                    title="No closed registrations."
                                    description="Events with finished registration appear here, ready for attendance."
                                />
                            ) : (
                                <div className="flex flex-col gap-2">
                                    {filteredClosed.map((event) => (
                                        <EventRowItem
                                            key={event.id}
                                            event={event}
                                        />
                                    ))}
                                </div>
                            )}
                        </Section>
                    </>
                )}
            </div>
        </>
    );
}

RegistrationsIndex.layout = {
    breadcrumbs: [{ title: 'Registration', href: '/registrations' }],
};
