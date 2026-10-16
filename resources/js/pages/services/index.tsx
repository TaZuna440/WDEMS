import { Head, Link } from '@inertiajs/react';
import { BookOpen, Church, Sun, Users, type LucideIcon } from 'lucide-react';

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
                <div className="pl-10">
                    <h1 className="text-on-image text-2xl font-semibold">
                        Services
                    </h1>
                    <p className="text-on-image-muted mt-1 text-sm">
                        Recurring services and their attendance records.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {service_types.map((type) => {
                        const Icon = ICONS[type.value] ?? Church;

                        return (
                            <Link
                                key={type.value}
                                href={`/services/${type.value}`}
                                className="glass-panel glass-panel-hover group flex flex-col gap-3 rounded-xl p-5"
                            >
                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-lime-brand/15 text-lime-brand">
                                    <Icon className="h-5 w-5" />
                                </div>
                                <div>
                                    <p className="text-sm font-semibold text-foreground">
                                        {type.label}
                                    </p>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {type.occurrence_count === 0
                                            ? 'No records yet'
                                            : `${type.occurrence_count} ${
                                                  type.occurrence_count === 1
                                                      ? 'occurrence'
                                                      : 'occurrences'
                                              }`}
                                    </p>
                                    {type.latest_date !== null && (
                                        <p className="mt-1 text-xs text-muted-foreground">
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
    breadcrumbs: [{ title: 'Services', href: '/services' }],
};
