import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';

type EventRow = {
    id: number;
    name: string;
    date: string | null;
    venue: string | null;
    registration_count: number;
    status: string;
    href: string;
};

type Props = {
    ministry: { slug: string; label: string; href: string };
    activity: {
        slug: string;
        label: string;
        event_count: number;
        registration_count: number;
    };
    events: EventRow[];
    add_event_url: string;
};

export default function SpecialistCategory({
    ministry,
    activity,
    events,
    add_event_url,
}: Props) {
    return (
        <>
            <Head title={activity.label} />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6">
                <Link
                    href={ministry.href}
                    className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-2 text-sm transition-colors"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to {ministry.label}
                </Link>

                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p className="text-lime-brand text-sm font-medium">
                            {ministry.label}
                        </p>
                        <h1 className="text-foreground mt-1 text-2xl font-semibold">
                            {activity.label}
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {activity.event_count} events ·{' '}
                            {activity.registration_count} registrations
                        </p>
                    </div>
                    <Button
                        asChild
                        className="bg-lime-brand hover:bg-lime-brand/90 text-[#0B1F3A]"
                    >
                        <Link href={add_event_url}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Event
                        </Link>
                    </Button>
                </div>

                {events.length > 0 ? (
                    <div className="grid gap-3">
                        {events.map((event) => (
                            <Link
                                key={event.id}
                                href={event.href}
                                className="glass-panel glass-panel-hover flex flex-col gap-3 rounded-xl p-5 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <h2 className="text-foreground font-semibold">
                                        {event.name}
                                    </h2>
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        {event.date ?? 'Date not set'} ·{' '}
                                        {event.venue ?? 'Venue not set'}
                                    </p>
                                </div>
                                <div className="text-muted-foreground flex gap-4 text-xs">
                                    <span>{event.status}</span>
                                    <span>
                                        {event.registration_count} registrations
                                    </span>
                                </div>
                            </Link>
                        ))}
                    </div>
                ) : (
                    <div className="glass-panel flex flex-col items-center rounded-xl p-12 text-center">
                        <div className="bg-lime-brand/15 mb-4 flex h-14 w-14 items-center justify-center rounded-full">
                            <CalendarDays className="dark:text-lime-brand h-7 w-7 text-lime-700" />
                        </div>
                        <h2 className="text-foreground font-semibold">
                            No events yet
                        </h2>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Add an event to start managing registration and
                            attendance.
                        </p>
                    </div>
                )}
            </div>
        </>
    );
}
