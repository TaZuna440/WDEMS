import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    BookOpen,
    Calendar,
    GraduationCap,
    Home,
    Users,
    type LucideIcon,
} from 'lucide-react';

type SubModule = {
    key: string;
    label: string;
    description: string;
    icon: LucideIcon;
    href: string;
    live: boolean;
};

/**
 * The five Youth sub-modules, per client Q3
 * (docs/church-events/10-client-decision-log.md).
 *
 * Only Youth Service is live — it reuses the Services module's
 * per-type view. The other four route to their own placeholders
 * until their builds ship in Phase 4.
 */
const SUB_MODULES: SubModule[] = [
    {
        key: 'service',
        label: 'Youth Service',
        description: 'Recurring youth services and attendance',
        icon: Calendar,
        href: '/events/church/services/youth',
        live: true,
    },
    {
        key: 'camp',
        label: 'Youth Camp',
        description: 'Multi-day camps with registration and payments',
        icon: Users,
        href: '/events/church/youth/camp',
        live: false,
    },
    {
        key: 'huddle',
        label: 'Huddle',
        description: 'Small group gatherings with leaders',
        icon: Home,
        href: '/events/church/youth/huddle',
        live: false,
    },
    {
        key: 'campus-ministry',
        label: 'Campus Ministry',
        description: 'Grade-based campus outreach and tracking',
        icon: GraduationCap,
        href: '/events/church/youth/campus-ministry',
        live: false,
    },
    {
        key: 'timothy-class',
        label: 'Timothy Class',
        description: 'Formation class with progress tracking',
        icon: BookOpen,
        href: '/events/church/youth/timothy-class',
        live: false,
    },
];

export default function YouthIndex() {
    return (
        <>
            <Head title="Youth" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6">
                <Link
                    href="/events?kind=church"
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Church Events
                </Link>

                <div className="pl-10">
                    <h1 className="text-foreground text-2xl font-semibold">
                        Youth
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Pick a Youth sub-module to see its events.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {SUB_MODULES.map((sub) => {
                        const Icon = sub.icon;
                        return (
                            <Link
                                key={sub.key}
                                href={sub.href}
                                className="glass-panel glass-panel-hover flex items-start gap-4 rounded-xl p-5"
                            >
                                <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-lime-brand/15">
                                    <Icon className="h-5 w-5 text-lime-700 dark:text-lime-brand" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <h3 className="font-semibold text-foreground">
                                            {sub.label}
                                        </h3>
                                        {!sub.live && (
                                            <span className="rounded-full bg-secondary px-2 py-0.5 text-[10px] font-medium uppercase tracking-wider text-muted-foreground">
                                                Soon
                                            </span>
                                        )}
                                    </div>
                                    <p className="mt-0.5 text-sm text-muted-foreground">
                                        {sub.description}
                                    </p>
                                </div>
                            </Link>
                        );
                    })}
                </div>
            </div>
        </>
    );
}

YouthIndex.layout = {
    breadcrumbs: [
        { title: 'Events', href: '/events' },
        { title: 'Church', href: '/events?kind=church' },
        { title: 'Youth', href: '/events/church/youth' },
    ],
};
