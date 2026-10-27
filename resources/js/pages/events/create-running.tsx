import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Calendar, PartyPopper } from 'lucide-react';

export default function EventsCreateRunning() {
    return (
        <>
            <Head title="Create Running Event" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-6">
                <Link
                    href="/events/create"
                    className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-2 text-sm transition-colors"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back
                </Link>

                <div className="pl-10">
                    <h1 className="text-foreground text-2xl font-semibold">
                        Running Event
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Choose the type of running event to create.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Link
                        href="/events/create/community-run"
                        className="glass-panel glass-panel-hover flex flex-col gap-3 rounded-xl p-6"
                    >
                        <div className="bg-lime-brand/15 flex h-12 w-12 items-center justify-center rounded-full">
                            <Calendar className="dark:text-lime-brand h-6 w-6 text-lime-700" />
                        </div>
                        <h2 className="text-foreground text-lg font-semibold">
                            Community Run
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            Create a community run with registration,
                            check-in, and event reports.
                        </p>
                    </Link>

                    <div
                        aria-disabled="true"
                        className="glass-panel flex cursor-not-allowed flex-col gap-3 rounded-xl p-6 opacity-60"
                    >
                        <div className="bg-muted flex h-12 w-12 items-center justify-center rounded-full">
                            <PartyPopper className="text-muted-foreground h-6 w-6" />
                        </div>
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-foreground text-lg font-semibold">
                                Fun Run
                            </h2>
                            <span className="bg-muted text-muted-foreground rounded-full px-3 py-1 text-xs font-medium">
                                Coming Soon
                            </span>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            Fun Run event creation is not available yet.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}

EventsCreateRunning.layout = {
    breadcrumbs: [
        { title: 'Events', href: '/events' },
        { title: 'Create', href: '/events/create' },
        { title: 'Running', href: '/events/create/running' },
    ],
};
