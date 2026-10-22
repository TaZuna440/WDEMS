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
};

/**
 * The nine church event categories. Confirmed by the client in
 * docs/church-events/02-product-scope-and-requirements.md CAT-01.
 *
 * `hasDedicatedPage` distinguishes the three that ship with their
 * own landing page (Services, Fellowship, Discipleship) from the
 * six that use the unified church event wizard until Phase 4.
 */
const CATEGORIES: Category[] = [
    {
        key: 'services',
        label: 'Services',
        description: 'Recurring worship services',
        icon: Church,
        hasDedicatedPage: true,
    },
    {
        key: 'fellowship',
        label: 'Fellowship',
        description: 'Gatherings by group type',
        icon: HeartHandshake,
        hasDedicatedPage: true,
    },
    {
        key: 'discipleship',
        label: 'Discipleship',
        description: 'Structured formation programs',
        icon: GraduationCap,
        hasDedicatedPage: true,
    },
    {
        key: 'youth',
        label: 'Youth',
        description: 'Youth camps, services, ministry',
        icon: Users,
        hasDedicatedPage: true,
    },
    {
        key: 'kids',
        label: 'Kids Ministry',
        description: 'Kids activities and outreaches',
        icon: Baby,
        hasDedicatedPage: false,
    },
    {
        key: 'young-adult',
        label: 'Young Adult',
        description: 'Young adult services and seminars',
        icon: UserRound,
        hasDedicatedPage: false,
    },
    {
        key: 'worship-prayer',
        label: 'Worship & Prayer',
        description: 'Prayer meetings and worship nights',
        icon: Music,
        hasDedicatedPage: false,
    },
    {
        key: 'special-occasions',
        label: 'Special Occasions',
        description: 'Church celebrations and events',
        icon: PartyPopper,
        hasDedicatedPage: false,
    },
    {
        key: 'church-administration',
        label: 'Church Administration',
        description: 'Ministry meetings and planning',
        icon: Briefcase,
        hasDedicatedPage: false,
    },
];

type Props = {
    /**
     * `browse` — every card routes to the category's browse page
     * (dedicated landing for the three built modules, placeholder
     * for the six unbuilt ones).
     *
     * `create` — the three built modules route to their landing
     * page (where the user creates from the module's own UI), and
     * the six unbuilt route to the wizard with the category preset.
     */
    mode?: 'browse' | 'create';
};

function hrefFor(category: Category, mode: 'browse' | 'create'): string {
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
