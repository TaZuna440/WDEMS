import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, Plus, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Activity = {
    slug: string;
    label: string;
    event_count: number;
    registration_count: number;
    href: string;
};

type Props = {
    ministry: {
        slug: string;
        label: string;
        event_count: number;
        registration_count: number;
    };
    activities: Activity[];
    add_event_url: string;
};

export default function SpecialistMinistry({
    ministry,
    activities,
    add_event_url,
}: Props) {
    return (
        <>
            <Head title={ministry.label} />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6">
                <Link
                    href="/events?kind=church"
                    className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-2 text-sm transition-colors"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Church Events
                </Link>

                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold">
                            {ministry.label}
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {ministry.event_count} events ·{' '}
                            {ministry.registration_count} registrations
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

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {activities.map((activity) => (
                        <Link
                            key={activity.slug}
                            href={activity.href}
                            className="glass-panel glass-panel-hover rounded-xl p-5"
                        >
                            <div className="bg-lime-brand/15 mb-4 flex h-11 w-11 items-center justify-center rounded-full">
                                <CalendarDays className="dark:text-lime-brand h-5 w-5 text-lime-700" />
                            </div>
                            <h2 className="text-foreground font-semibold">
                                {activity.label}
                            </h2>
                            <div className="text-muted-foreground mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                <span>{activity.event_count} events</span>
                                <span className="inline-flex items-center gap-1">
                                    <Users className="h-3.5 w-3.5" />
                                    {activity.registration_count} registrations
                                </span>
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        </>
    );
}
