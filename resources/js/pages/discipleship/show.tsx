import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, GraduationCap } from 'lucide-react';

type ProgramData = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
};

type Props = {
    program: ProgramData;
};

/**
 * Per-program view.
 *
 * Phase 5A renders the header and an empty batches section. Phase
 * 5B adds the batches relation to the payload and replaces the
 * empty state with a batch list plus a create-batch dialog.
 *
 * The empty state is a real state at 5A: no batches exist yet
 * because batches are not built. It is not a placeholder for a
 * loading screen.
 */
export default function DiscipleshipShow({ program }: Props) {
    return (
        <>
            <Head title={`${program.name} — Discipleship`} />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-6">
                <Link
                    href="/discipleship"
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    All Discipleship
                </Link>

                <div className="glass-panel rounded-xl p-6">
                    <div className="pl-10">
                        <h1 className="text-xl font-semibold text-foreground">
                            {program.name}
                        </h1>
                        {program.description && (
                            <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                                {program.description}
                            </p>
                        )}
                    </div>
                </div>

                <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-16 text-center">
                    <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-lime-brand/15">
                        <GraduationCap className="h-8 w-8 text-lime-brand" />
                    </div>
                    <h2 className="mb-2 text-lg font-semibold text-foreground">
                        No batches yet
                    </h2>
                    <p className="max-w-sm text-sm text-muted-foreground">
                        Batches of this program will appear here once they
                        are created.
                    </p>
                </div>
            </div>
        </>
    );
}

DiscipleshipShow.layout = {
    breadcrumbs: [
        { title: 'Discipleship', href: '/discipleship' },
        { title: 'Program', href: '#' },
    ],
};
