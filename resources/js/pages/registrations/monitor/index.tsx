import { Head } from '@inertiajs/react';

type EventRow = {
    id: number;
    event_name: string;
    total: number;
};

type Props = {
    events: EventRow[];
};

export default function MonitorIndex({ events }: Props) {
    return (
        <>
            <Head title="Registration Monitor" />

            <div className="p-6">
                <p className="text-sm text-muted-foreground">
                    {events.length} event{events.length === 1 ? '' : 's'} with
                    open registration. Detailed view lands in Phase 7b.
                </p>
            </div>
        </>
    );
}

MonitorIndex.layout = {
    breadcrumbs: [
        { title: 'Registration', href: '/registrations' },
        { title: 'Monitor', href: '/registrations/monitor' },
    ],
};
