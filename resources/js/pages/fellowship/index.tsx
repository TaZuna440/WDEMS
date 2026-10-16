import { Head, Link } from '@inertiajs/react';
import {
    Award,
    BookOpen,
    Church,
    Home,
    UserRound,
    type LucideIcon,
} from 'lucide-react';

type FellowshipTypeRow = {
    value: string;
    label: string;
    event_count: number;
    latest_date: string | null;
};

type Props = {
    fellowship_types: FellowshipTypeRow[];
};

/**
 * Per-type icons. Womens and Mens deliberately share UserRound --
 * distinct icons for a gender pair would imply a ranking that the
 * data does not carry. Family / Church / Pastors / Leaders are
 * distinct because they are different kinds of gathering, not a
 * pair.
 */
const ICONS: Record<string, LucideIcon> = {
    family: Home,
    church: Church,
    pastors: BookOpen,
    leaders: Award,
    womens: UserRound,
    mens: UserRound,
};

export default function FellowshipIndex({ fellowship_types }: Props) {
    return (
        <>
            <Head title="Fellowship" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-6">
                <div className="pl-10">
                    <h1 className="text-foreground text-2xl font-semibold">
                        Fellowship
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Fellowship gatherings and their attendance records.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {fellowship_types.map((type) => {
                        const Icon = ICONS[type.value] ?? Church;

                        return (
                            <Link
                                key={type.value}
                                href={`/fellowship/${type.value}`}
                                className="glass-panel glass-panel-hover group flex flex-col gap-3 rounded-xl p-5"
                            >
                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-lime-brand/15 text-lime-700 dark:text-lime-brand">
                                    <Icon className="h-5 w-5" />
                                </div>
                                <div>
                                    <p className="text-sm font-semibold text-foreground">
                                        {type.label}
                                    </p>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {type.event_count === 0
                                            ? 'No gatherings yet'
                                            : `${type.event_count} ${
                                                  type.event_count === 1
                                                      ? 'gathering'
                                                      : 'gatherings'
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

FellowshipIndex.layout = {
    breadcrumbs: [{ title: 'Fellowship', href: '/events/church/fellowship' }],
};
