import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CalendarDays } from 'lucide-react';

type Props = {
    category: string;
    categoryLabel: string;
};

export default function ChurchCategoryPlaceholder({
    category,
    categoryLabel,
}: Props) {
    return (
        <>
            <Head title={categoryLabel} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6">
                <Link
                    href="/events?kind=church"
                    className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-2 text-sm transition-colors"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Church Events
                </Link>

                <div className="pl-10">
                    <h1 className="text-foreground text-2xl font-semibold">
                        {categoryLabel}
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        {categoryLabel} events will appear here
                    </p>
                </div>

                <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-16 text-center">
                    <div className="bg-lime-brand/15 mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                        <CalendarDays className="dark:text-lime-brand h-8 w-8 text-lime-700" />
                    </div>
                    <h2 className="text-foreground mb-2 text-lg font-semibold">
                        Coming soon
                    </h2>
                    <p className="text-muted-foreground max-w-md text-sm">
                        The {categoryLabel} module is scoped and scheduled for a
                        future release. It will use the same event workflow as
                        other church categories: details, registration,
                        attendance, and reports.
                    </p>
                </div>
            </div>
        </>
    );
}

ChurchCategoryPlaceholder.layout = {
    breadcrumbs: [
        { title: 'Events', href: '/events?kind=church' },
        { title: 'Church', href: '/events?kind=church' },
    ],
};
