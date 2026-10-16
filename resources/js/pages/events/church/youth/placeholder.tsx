import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CalendarDays } from 'lucide-react';

type Props = {
    sub: string;
    subLabel: string;
};

export default function YouthPlaceholder({ subLabel }: Props) {
    return (
        <>
            <Head title={subLabel} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6">
                <Link
                    href="/events/church/youth"
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Youth
                </Link>

                <div className="pl-10">
                    <h1 className="text-foreground text-2xl font-semibold">
                        {subLabel}
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        This Youth sub-module is coming soon
                    </p>
                </div>

                <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-16 text-center">
                    <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-lime-brand/15">
                        <CalendarDays className="h-8 w-8 text-lime-700 dark:text-lime-brand" />
                    </div>
                    <h2 className="mb-2 text-lg font-semibold text-foreground">
                        Coming soon
                    </h2>
                    <p className="max-w-md text-sm text-muted-foreground">
                        {subLabel} is scoped and scheduled for a future
                        release. It will use the same event workflow as
                        other church categories: details, registration,
                        attendance, and reports.
                    </p>
                </div>
            </div>
        </>
    );
}

YouthPlaceholder.layout = {
    breadcrumbs: [
        { title: 'Events', href: '/events' },
        { title: 'Church', href: '/events?kind=church' },
        { title: 'Youth', href: '/events/church/youth' },
    ],
};
