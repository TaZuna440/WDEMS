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
    href: string;
    icon: LucideIcon;
};

/**
 * The nine church event categories. Confirmed by the client in
 * docs/church-events/02-product-scope-and-requirements.md CAT-01.
 *
 * Services, Fellowship, and Discipleship have dedicated landing
 * pages (shipped in earlier phases). The other six render a
 * placeholder page until Phase 4 fills them in.
 */
const CATEGORIES: Category[] = [
    {
        key: 'services',
        label: 'Services',
        description: 'Recurring worship services',
        href: '/events/church/services',
        icon: Church,
    },
    {
        key: 'fellowship',
        label: 'Fellowship',
        description: 'Gatherings by group type',
        href: '/events/church/fellowship',
        icon: HeartHandshake,
    },
    {
        key: 'discipleship',
        label: 'Discipleship',
        description: 'Structured formation programs',
        href: '/events/church/discipleship',
        icon: GraduationCap,
    },
    {
        key: 'youth',
        label: 'Youth',
        description: 'Youth camps, services, ministry',
        href: '/events/church/youth',
        icon: Users,
    },
    {
        key: 'kids',
        label: 'Kids Ministry',
        description: 'Kids activities and outreaches',
        href: '/events/church/kids',
        icon: Baby,
    },
    {
        key: 'young-adult',
        label: 'Young Adult',
        description: 'Young adult services and seminars',
        href: '/events/church/young-adult',
        icon: UserRound,
    },
    {
        key: 'worship-prayer',
        label: 'Worship & Prayer',
        description: 'Prayer meetings and worship nights',
        href: '/events/church/worship-prayer',
        icon: Music,
    },
    {
        key: 'special-occasions',
        label: 'Special Occasions',
        description: 'Church celebrations and events',
        href: '/events/church/special-occasions',
        icon: PartyPopper,
    },
    {
        key: 'church-administration',
        label: 'Church Administration',
        description: 'Ministry meetings and planning',
        href: '/events/church/church-administration',
        icon: Briefcase,
    },
];

export default function ChurchCategoryCards() {
    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {CATEGORIES.map((category) => {
                const Icon = category.icon;
                return (
                    <Link
                        key={category.key}
                        href={category.href}
                        className="glass-panel glass-panel-hover flex items-start gap-4 rounded-xl p-5"
                    >
                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-lime-brand/15">
                            <Icon className="h-5 w-5 text-lime-700 dark:text-lime-brand" />
                        </div>
                        <div className="min-w-0">
                            <h3 className="font-semibold text-foreground">
                                {category.label}
                            </h3>
                            <p className="mt-0.5 text-sm text-muted-foreground">
                                {category.description}
                            </p>
                        </div>
                    </Link>
                );
            })}
        </div>
    );
}
