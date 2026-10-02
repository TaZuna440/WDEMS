import { Head } from '@inertiajs/react';

type EventData = {
    id: number;
    event_name: string;
};

type Stats = {
    total: number;
    last_hour: number;
    last_15min: number;
};

type Props = {
    event: EventData;
    stats: Stats;
    registrations: unknown[];
};

export default function MonitorShow({ event, stats, registrations }: Props) {
    return (
        <>
            <Head title={`Monitor — ${event.event_name}`} />

            <div className="p-6">
                <p className="text-sm text-muted-foreground">
                    {stats.total} registered · {registrations.length} in feed.
                    Detailed view lands in Phase 7b.
                </p>
            </div>
        </>
    );
}

MonitorShow.layout = {
    breadcrumbs: [
        { title: 'Registration', href: '/registrations' },
        { title: 'Monitor', href: '/registrations/monitor' },
    ],
};
