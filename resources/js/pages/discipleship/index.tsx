import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    BookOpen,
    GraduationCap,
    Mountain,
    Users,
    type LucideIcon,
} from 'lucide-react';

type ProgramRow = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    batches_count: number;
};

type Props = {
    programs: ProgramRow[];
};

/**
 * Per-program icons, keyed by slug. The three School of Leaders
 * levels share GraduationCap -- they are one track at three
 * depths, not three distinct tracks.
 */
const ICONS: Record<string, LucideIcon> = {
    'life-class': BookOpen,
    'encounter-god-retreat': Mountain,
    'school-of-leaders-1': GraduationCap,
    'school-of-leaders-2': GraduationCap,
    'school-of-leaders-3': GraduationCap,
    'cell-convergence': Users,
};

export default function DiscipleshipIndex({ programs }: Props) {
    return (
        <>
            <Head title="Discipleship" />

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
                        Discipleship
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Formation programs and their batches.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    {programs.map((program) => {
                        const Icon = ICONS[program.slug] ?? GraduationCap;

                        return (
                            <Link
                                key={program.id}
                                href={`/events/church/discipleship/programs/${program.slug}`}
                                className="glass-panel group hover:border-lime-brand/40 flex gap-4 rounded-xl p-5 transition-colors"
                            >
                                <div className="bg-lime-brand/15 dark:text-lime-brand flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-lime-700">
                                    <Icon className="h-5 w-5" />
                                </div>
                                <div className="flex flex-1 flex-col gap-1">
                                    <p className="text-foreground text-sm font-semibold">
                                        {program.name}
                                    </p>
                                    {program.description && (
                                        <p className="text-muted-foreground line-clamp-2 text-xs">
                                            {program.description}
                                        </p>
                                    )}
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {program.batches_count === 0
                                            ? 'No batches yet'
                                            : `${program.batches_count} ${
                                                  program.batches_count === 1
                                                      ? 'batch'
                                                      : 'batches'
                                              }`}
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

DiscipleshipIndex.layout = {
    breadcrumbs: [
        { title: 'Events', href: '/events' },
        { title: 'Church', href: '/events?kind=church' },
        { title: 'Discipleship', href: '/events/church/discipleship' },
    ],
};
