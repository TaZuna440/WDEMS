import { Head, Link } from '@inertiajs/react';
import { CalendarDays, CircleCheck, ClipboardList } from 'lucide-react';

type EventRow = {
    id: number;
    event_name: string;
    event_date: string | null;
    venue: string | null;
    status: string;
    status_label: string;
    creator: string | null;
};

type Props = {
    needsForm: EventRow[];
    open: EventRow[];
    closed: EventRow[];
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
                href={`/events/${event.id}`}
                className="flex min-w-0 flex-1 flex-col"
            >
                <span className="text-foreground truncate text-sm font-medium">
                    {event.event_name}
                </span>
                <span className="text-muted-foreground mt-0.5 truncate text-xs">
                    {event.event_date ?? '—'} · {event.venue ?? 'No venue'}
                </span>
            </Link>

            <div className="flex shrink-0 items-center gap-3">
                <span className="text-muted-foreground text-xs">
                    {event.status_label}
                </span>

                {showMonitor && (
                    <Link
                        href={`/registrations/monitor/${event.id}`}
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

export default function RegistrationsIndex({ needsForm, open, closed }: Props) {
    return (
        <>
            <Head title="Registration" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-6">
                <div className="pl-10">
                    <h1 className="text-foreground text-2xl font-semibold">
                        Registration
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Build registration forms, publish them, and manage
                        incoming submissions.
                    </p>
                </div>

                <Section
                    title="Needs Registration Form"
                    count={needsForm.length}
                >
                    {needsForm.length === 0 ? (
                        <EmptySection
                            icon={CircleCheck}
                            title="All events have a form."
                            description="Nothing to do here. Create a new event to start the registration workflow."
                        />
                    ) : (
                        <div className="flex flex-col gap-2">
                            {needsForm.map((event) => (
                                <EventRowItem key={event.id} event={event} />
                            ))}
                        </div>
                    )}
                </Section>

                <Section title="Open" count={open.length}>
                    {open.length === 0 ? (
                        <EmptySection
                            icon={ClipboardList}
                            title="No open registrations."
                            description="Once an event's form is ready, open registration to publish the public link."
                        />
                    ) : (
                        <div className="flex flex-col gap-2">
                            {open.map((event) => (
                                <EventRowItem
                                    key={event.id}
                                    event={event}
                                    showMonitor
                                />
                            ))}
                        </div>
                    )}
                </Section>

                <Section title="Closed" count={closed.length}>
                    {closed.length === 0 ? (
                        <EmptySection
                            icon={CalendarDays}
                            title="No closed registrations."
                            description="Events with finished registration appear here, ready for attendance."
                        />
                    ) : (
                        <div className="flex flex-col gap-2">
                            {closed.map((event) => (
                                <EventRowItem key={event.id} event={event} />
                            ))}
                        </div>
                    )}
                </Section>
            </div>
        </>
    );
}

RegistrationsIndex.layout = {
    breadcrumbs: [{ title: 'Registration', href: '/registrations' }],
};
