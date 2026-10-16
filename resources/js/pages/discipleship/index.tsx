import { Head, Link } from '@inertiajs/react';
import {
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
};

type Props = {
    programs: ProgramRow[];
};

/**
 * Per-program icons, keyed by slug. The three School of Leaders
 * levels share GraduationCap -- they are one track at three
 * depths, not three distinct tracks. Distinct icons for each
 * level would imply a categorical difference that does not
 * exist.
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
                <div className="pl-10">
                    <h1 className="text-2xl font-semibold text-foreground">
                        Discipleship
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Formation programs and their batches.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    {programs.map((program) => {
                        const Icon = ICONS[program.slug] ?? GraduationCap;

                        return (
                            <Link
                                key={program.id}
                                href={`/discipleship/programs/${program.slug}`}
                                className="glass-panel group flex gap-4 rounded-xl p-5 transition-colors hover:border-lime-brand/40"
                            >
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-lime-brand/15 text-lime-brand">
                                    <Icon className="h-5 w-5" />
                                </div>
                                <div className="flex flex-1 flex-col gap-1">
                                    <p className="text-sm font-semibold text-foreground">
                                        {program.name}
                                    </p>
                                    {program.description && (
                                        <p className="line-clamp-2 text-xs text-muted-foreground">
                                            {program.description}
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

DiscipleshipIndex.layout = {
    breadcrumbs: [{ title: 'Discipleship', href: '/discipleship' }],
};
