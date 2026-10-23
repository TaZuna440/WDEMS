import { Link } from '@inertiajs/react';
import {
    Baby,
    Briefcase,
    Church,
    GraduationCap,
    HeartHandshake,
    Music,
    PartyPopper,
    UserRound,
    Users,
    type LucideIcon,
} from 'lucide-react';

type Category = {
    key: string;
    label: string;
    description: string;
    icon: LucideIcon;
    /** Categories with a dedicated module and landing page. */
    hasDedicatedPage: boolean;
    eventCategory: string;
};

/**
 * The nine church event categories. Confirmed by the client in
 * docs/church-events/02-product-scope-and-requirements.md CAT-01.
 *
 * `hasDedicatedPage` distinguishes categories with a dedicated ministry
 * landing page from those that create events directly in the church wizard.
 */
const CATEGORIES: Category[] = [
    {
        key: 'services',
        label: 'Services',
        description: 'Recurring worship services',
        icon: Church,
        hasDedicatedPage: true,
        eventCategory: 'Services',
    },
    {
        key: 'fellowship',
        label: 'Fellowship',
        description: 'Gatherings by group type',
        icon: HeartHandshake,
        hasDedicatedPage: true,
        eventCategory: 'Fellowship',
    },
    {
        key: 'discipleship',
        label: 'Discipleship',
        description: 'Structured formation programs',
        icon: GraduationCap,
        hasDedicatedPage: true,
        eventCategory: 'Discipleship',
    },
    {
        key: 'youth',
        label: 'Youth Ministry',
        description: 'Youth camps, services, ministry',
        icon: Users,
        hasDedicatedPage: true,
        eventCategory: 'Youth',
    },
    {
        key: 'kids',
        label: 'Kids Ministry',
        description: 'Kids activities and outreaches',
        icon: Baby,
        hasDedicatedPage: false,
        eventCategory: 'Kids Ministry',
    },
    {
        key: 'young-adult',
        label: 'Young Adult',
        description: 'Young adult services and seminars',
        icon: UserRound,
        hasDedicatedPage: false,
        eventCategory: 'Young Adult',
    },
    {
        key: 'worship-prayer',
        label: 'Worship & Prayer',
        description: 'Prayer meetings and worship nights',
        icon: Music,
        hasDedicatedPage: false,
        eventCategory: 'Worship & Prayer',
    },
    {
        key: 'special-occasions',
        label: 'Special Occasions',
        description: 'Church celebrations and events',
        icon: PartyPopper,
        hasDedicatedPage: false,
        eventCategory: 'Special Occasions',
    },
    {
        key: 'church-administration',
        label: 'Church Administration',
        description: 'Ministry meetings and planning',
        icon: Briefcase,
        hasDedicatedPage: false,
        eventCategory: 'Church Administration',
    },
];

type Props = {
    /** Browse modules or open their standard/specialist creation workflow. */
    mode?: 'browse' | 'create';
};

function hrefFor(category: Category, mode: 'browse' | 'create'): string {
    if (['services', 'fellowship', 'discipleship'].includes(category.key)) {
        return mode === 'browse'
            ? `/events/church/${category.key}`
            : `/events/create/church/${category.key}`;
    }

    if (mode === 'create' && !category.hasDedicatedPage) {
        return `/events/create/church/${category.key}`;
    }
    return `/events/church/${category.key}`;
}

export default function ChurchCategoryCards({ mode = 'browse' }: Props) {
    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {CATEGORIES.map((category) => {
                const Icon = category.icon;
                return (
                    <Link
                        key={category.key}
                        href={hrefFor(category, mode)}
                        className="glass-panel glass-panel-hover flex items-start gap-4 rounded-xl p-5"
                    >
                        <div className="bg-lime-brand/15 flex h-11 w-11 shrink-0 items-center justify-center rounded-full">
                            <Icon className="dark:text-lime-brand h-5 w-5 text-lime-700" />
                        </div>
                        <div className="min-w-0">
                            <h3 className="text-foreground font-semibold">
                                {category.label}
                            </h3>
                            <p className="text-muted-foreground mt-0.5 text-sm">
                                {category.description}
                            </p>
                        </div>
                    </Link>
                );
            })}
        </div>
    );
}
