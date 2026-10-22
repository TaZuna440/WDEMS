import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, CircleHelp } from 'lucide-react';

type Module = { name: string; workflow: string };
type Props = {
    modules: Module[];
    included: string[];
    limitations: string[];
};

export default function ChurchSystemScope({
    modules,
    included,
    limitations,
}: Props) {
    return (
        <>
            <Head title="Church System Scope" />
            <main className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-6">
                <Link
                    href="/dashboard?side=church"
                    className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-2 text-sm transition-colors"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Church Dashboard
                </Link>
                <header>
                    <p className="text-lime-brand text-sm font-medium">
                        WDEMS · Church
                    </p>
                    <h1 className="text-foreground mt-1 text-2xl font-semibold">
                        System Scope
                    </h1>
                    <p className="text-muted-foreground mt-2 max-w-3xl text-sm">
                        A concise guide to the modules and workflows currently
                        available in the Church system.
                    </p>
                </header>

                <section className="glass-panel rounded-xl p-6">
                    <h2 className="text-foreground font-semibold">
                        Church modules
                    </h2>
                    <ul className="mt-4 grid gap-3 sm:grid-cols-2">
                        {modules.map((module) => (
                            <li
                                key={module.name}
                                className="border-border rounded-lg border p-4"
                            >
                                <h3 className="text-foreground font-medium">
                                    {module.name}
                                </h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    {module.workflow}
                                </p>
                            </li>
                        ))}
                    </ul>
                </section>

                <div className="grid gap-6 md:grid-cols-2">
                    <section className="glass-panel rounded-xl p-6">
                        <h2 className="text-foreground flex items-center gap-2 font-semibold">
                            <CheckCircle2 className="text-lime-brand h-4 w-4" />
                            Included workflow
                        </h2>
                        <ul className="text-muted-foreground mt-3 list-disc space-y-2 pl-5 text-sm">
                            {included.map((item) => (
                                <li key={item}>{item}</li>
                            ))}
                        </ul>
                    </section>
                    <section className="glass-panel rounded-xl p-6">
                        <h2 className="text-foreground flex items-center gap-2 font-semibold">
                            <CircleHelp className="text-lime-brand h-4 w-4" />
                            Current limitations
                        </h2>
                        <ul className="text-muted-foreground mt-3 list-disc space-y-2 pl-5 text-sm">
                            {limitations.map((item) => (
                                <li key={item}>{item}</li>
                            ))}
                        </ul>
                    </section>
                </div>
            </main>
        </>
    );
}
