import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Calendar, Church } from 'lucide-react';

export default function EventsCreateEntry() {
    return (
        <>
            <Head title="Create Event" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-6">
                <Link
                    href="/events"
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Events
                </Link>

                <div className="pl-10">
                    <h1 className="text-foreground text-2xl font-semibold">
                        Create Event
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Pick the kind of event you are organizing.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Link
                        href="/events/create/community-run"
                        className="glass-panel glass-panel-hover flex flex-col gap-3 rounded-xl p-6"
                    >
                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-lime-brand/15">
                            <Calendar className="h-6 w-6 text-lime-700 dark:text-lime-brand" />
                        </div>
                        <h2 className="text-lg font-semibold text-foreground">
                            Community Run
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Running events with registration, check-in, and
                            end-of-event reports.
                        </p>
                    </Link>

                    <Link
                        href="/events/create/church"
                        className="glass-panel glass-panel-hover flex flex-col gap-3 rounded-xl p-6"
                    >
                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-lime-brand/15">
                            <Church className="h-6 w-6 text-lime-700 dark:text-lime-brand" />
                        </div>
                        <h2 className="text-lg font-semibold text-foreground">
                            Church Event
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Services, Fellowship, Discipleship, Youth, Kids
                            Ministry, and other church activities.
                        </p>
                    </Link>
                </div>
            </div>
        </>
    );
}

EventsCreateEntry.layout = {
    breadcrumbs: [
        { title: 'Events', href: '/events' },
        { title: 'Create', href: '/events/create' },
    ],
};
