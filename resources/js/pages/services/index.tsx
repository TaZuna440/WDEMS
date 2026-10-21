import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    BookOpen,
    Church,
    Sun,
    Users,
    type LucideIcon,
} from 'lucide-react';

type ServiceTypeRow = {
    value: string;
    label: string;
    occurrence_count: number;
    latest_date: string | null;
};

type Props = {
    service_types: ServiceTypeRow[];
};

const ICONS: Record<string, LucideIcon> = {
    morning: Sun,
    midweek: BookOpen,
    youth: Users,
    other: Church,
};

export default function ServicesIndex({ service_types }: Props) {
    return (
        <>
            <Head title="Services" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-6">
                <Link
                    href="/events?kind=church"
                    className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-2 text-sm transition-colors"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Church Events
                </Link>

                <div className="pl-10">
                    <h1 className="text-foreground text-2xl font-semibold">
                        Services
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Recurring services and their attendance records.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {service_types.map((type) => {
                        const Icon = ICONS[type.value] ?? Church;

                        return (
                            <Link
                                key={type.value}
                                href={`/events/church/services/${type.value}`}
                                className="glass-panel glass-panel-hover group flex flex-col gap-3 rounded-xl p-5"
                            >
                                <div className="bg-lime-brand/15 dark:text-lime-brand flex h-10 w-10 items-center justify-center rounded-full text-lime-700">
                                    <Icon className="h-5 w-5" />
                                </div>
                                <div>
                                    <p className="text-foreground text-sm font-semibold">
                                        {type.label}
                                    </p>
                                    <p className="text-muted-foreground mt-0.5 text-xs">
                                        {type.occurrence_count === 0
                                            ? 'No records yet'
                                            : `${type.occurrence_count} ${
                                                  type.occurrence_count === 1
                                                      ? 'occurrence'
                                                      : 'occurrences'
                                              }`}
                                    </p>
                                    {type.latest_date !== null && (
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            Latest: {type.latest_date}
                                        </p>
                                    )}
                                </div>
                            </Link>
                        );
                    })}
                </div>
            </div>
        </>
    );
}

ServicesIndex.layout = {
    breadcrumbs: [
        { title: 'Events', href: '/events' },
        { title: 'Church', href: '/events?kind=church' },
        { title: 'Services', href: '/events/church/services' },
    ],
};
