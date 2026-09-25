import { Head, Link } from '@inertiajs/react';
import {
    CalendarDays,
    CircleCheck,
    ClipboardList,
} from 'lucide-react';

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
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-white/10 p-8 text-center">
            <Icon className="mb-3 h-6 w-6 text-muted-foreground/40" />
            <p className="text-sm text-muted-foreground">{title}</p>
            <p className="mt-1 max-w-xs text-xs text-muted-foreground">
                {description}
            </p>
        </div>
    );
}

function EventRowItem({ event }: { event: EventRow }) {
    return (
        <Link
            href={`/events/${event.id}`}
            className="flex items-center justify-between gap-4 rounded-lg border border-white/5 bg-white/[0.02] px-4 py-3 transition-colors hover:bg-white/[0.04]"
        >
            <div className="flex flex-col">
                <span className="text-sm font-medium text-foreground">
                    {event.event_name}
                </span>
                <span className="mt-0.5 text-xs text-muted-foreground">
                    {event.event_date ?? '—'} · {event.venue ?? 'No venue'}
                </span>
            </div>
            <span className="shrink-0 text-xs text-muted-foreground">
                {event.status_label}
            </span>
        </Link>
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
        <section className="flex flex-col gap-3">
            <div className="flex items-baseline justify-between">
                <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                    {title}
                </h2>
                <span className="text-xs text-muted-foreground">{count}</span>
            </div>
            {children}
        </section>
    );
}

export default function RegistrationsIndex({
    needsForm,
    open,
    closed,
}: Props) {
    return (
        <>
            <Head title="Registration" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-8 p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Registration
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
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
                                <EventRowItem key={event.id} event={event} />
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
    breadcrumbs: [
        { title: 'Registration', href: '/registrations' },
    ],
};
