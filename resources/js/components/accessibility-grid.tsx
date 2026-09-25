import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

export type AccessibilityField =
    | 'walkers_welcome'
    | 'all_paces_welcome'
    | 'all_ages_welcome'
    | 'stroller_friendly'
    | 'wheelchair_accessible'
    | 'sweeper_present'
    | 'service_animals_allowed'
    | 'leashed_pets_allowed'
    | 'quiet_space_available';

type Feature = {
    field: AccessibilityField;
    label: string;
    hint?: string;
};

type Group = {
    title: string;
    features: Feature[];
};

const GROUPS: Group[] = [
    {
        title: 'Pace & Ability',
        features: [
            {
                field: 'walkers_welcome',
                label: 'Walkers welcome',
                hint: 'Not just runners — walkers can join',
            },
            {
                field: 'all_paces_welcome',
                label: 'All paces welcome',
                hint: 'No minimum speed or time cutoff',
            },
            {
                field: 'all_ages_welcome',
                label: 'All ages welcome',
                hint: 'Kids and seniors can join',
            },
            {
                field: 'sweeper_present',
                label: 'Sweeper present',
                hint: 'A volunteer stays at the back — nobody is left alone',
            },
        ],
    },
    {
        title: 'Physical Access',
        features: [
            {
                field: 'stroller_friendly',
                label: 'Stroller friendly',
                hint: 'Route is passable with a stroller',
            },
            {
                field: 'wheelchair_accessible',
                label: 'Wheelchair accessible',
                hint: 'Route is passable for wheelchairs',
            },
        ],
    },
    {
        title: 'Companion & Support',
        features: [
            {
                field: 'service_animals_allowed',
                label: 'Service animals allowed',
                hint: 'Trained service animals are permitted',
            },
            {
                field: 'leashed_pets_allowed',
                label: 'Leashed pets allowed',
                hint: 'Family pets on a leash are permitted',
            },
        ],
    },
    {
        title: 'Sensory',
        features: [
            {
                field: 'quiet_space_available',
                label: 'Quiet space available',
                hint: 'A low-noise area exists for breaks',
            },
        ],
    },
];

type Props = {
    value: Partial<Record<AccessibilityField, boolean>>;
    onChange: (field: AccessibilityField, checked: boolean) => void;
};

export default function AccessibilityGrid({ value, onChange }: Props) {
    return (
        <div className="flex flex-col gap-5">
            {GROUPS.map((group) => (
                <div key={group.title} className="flex flex-col gap-3">
                    <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        {group.title}
                    </h3>

                    <div className="grid gap-3 sm:grid-cols-2">
                        {group.features.map((feature) => {
                            const id = `accessibility-${feature.field}`;
                            return (
                                <div
                                    key={feature.field}
                                    className="flex items-start gap-3 rounded-lg border border-white/5 bg-white/[0.02] p-3"
                                >
                                    <Checkbox
                                        id={id}
                                        checked={value[feature.field] === true}
                                        onCheckedChange={(checked) =>
                                            onChange(
                                                feature.field,
                                                checked === true,
                                            )
                                        }
                                        className="mt-0.5"
                                    />
                                    <div className="flex flex-1 flex-col">
                                        <Label
                                            htmlFor={id}
                                            className="cursor-pointer text-sm font-medium leading-tight"
                                        >
                                            {feature.label}
                                        </Label>
                                        {feature.hint && (
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {feature.hint}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            ))}
        </div>
    );
}
