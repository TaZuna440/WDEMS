import AccessibilityGrid, {
    type AccessibilityField,
} from '@/components/accessibility-grid';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type EventTypeOption = {
    value: string;
    label: string;
};

type ChurchCategoryOption = {
    value: string;
    label: string;
};

type FormData = {
    event_type: string;
    church_category: string;
    event_name: string;
    description: string;
    coordinator?: string;
    church_activity_category?: string;
    emergency_contact_name?: string;
    emergency_contact_number?: string;
    medical_notes?: string;
    parental_consent?: boolean | null;
    child_name?: string;
    mother_name?: string;
    father_name?: string;
    officiating_pastor?: string;
    assigned_teacher?: string;
    program_day_session?: string;
} & Partial<Record<AccessibilityField, boolean>>;

type Props = {
    data: FormData;
    setData: (key: string, value: unknown) => void;
    errors: Record<string, string>;
    eventTypes: EventTypeOption[];
    churchCategories: ChurchCategoryOption[];
    specialistActivityOptions?: ChurchCategoryOption[];
    isEdit?: boolean;
    lockEventType?: boolean;
    lockChurchCategory?: boolean;
    lockSpecialistActivity?: boolean;
};

const ACCESSIBILITY_FIELDS: AccessibilityField[] = [
    'walkers_welcome',
    'all_paces_welcome',
    'all_ages_welcome',
    'stroller_friendly',
    'wheelchair_accessible',
    'sweeper_present',
    'service_animals_allowed',
    'leashed_pets_allowed',
];

export default function BasicsStep({
    data,
    setData,
    errors,
    eventTypes,
    churchCategories,
    specialistActivityOptions = [],
    isEdit = false,
    lockEventType = false,
    lockChurchCategory = false,
    lockSpecialistActivity = false,
}: Props) {
    const isChurch = data.event_type === 'church';
    const activity = data.church_activity_category?.toLocaleLowerCase() ?? '';
    const isDedication =
        data.church_category === 'Special Occasions' &&
        activity === 'dedication';
    const isYouthCamp = activity === 'youth camp';
    const isVacationBibleSchool =
        activity === 'vacation bible school' || activity.includes('vbs');
    const needsEmergencyDetails = isYouthCamp || isVacationBibleSchool;
    const needsTeacherDetails =
        data.church_category === 'Discipleship' || isVacationBibleSchool;

    const accessibilityValue: Partial<Record<AccessibilityField, boolean>> = {};
    for (const field of ACCESSIBILITY_FIELDS) {
        accessibilityValue[field] = data[field] === true;
    }

    return (
        <div className="flex flex-col gap-6">
            <div>
                <h2 className="text-foreground text-lg font-semibold">
                    Event Details
                </h2>
                <p className="text-muted-foreground mt-1 text-sm">
                    The core details of your event.
                </p>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="event_type">
                    Event Type <span className="text-destructive">*</span>
                </Label>
                <Select
                    value={data.event_type}
                    onValueChange={(value) => {
                        setData('event_type', value);
                        // Clear the church category when switching
                        // away from Church Event, so a stale value
                        // does not fail validation on submit.
                        if (value !== 'church') {
                            setData('church_category', '');
                        }
                    }}
                    disabled={isEdit || lockEventType}
                >
                    <SelectTrigger id="event_type">
                        <SelectValue placeholder="Select event type" />
                    </SelectTrigger>
                    <SelectContent>
                        {eventTypes.map((type) => (
                            <SelectItem key={type.value} value={type.value}>
                                {type.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.event_type} />
                <p className="text-muted-foreground text-xs">
                    Event type cannot be changed after creation.
                </p>
            </div>

            {isChurch && (
                <div className="grid gap-2">
                    <Label htmlFor="church_category">
                        Church Event Type{' '}
                        <span className="text-destructive">*</span>
                    </Label>
                    <Select
                        value={data.church_category}
                        onValueChange={(value) =>
                            setData('church_category', value)
                        }
                        disabled={lockChurchCategory}
                    >
                        <SelectTrigger id="church_category">
                            <SelectValue placeholder="Select a church event type" />
                        </SelectTrigger>
                        <SelectContent>
                            {churchCategories.map((category) => (
                                <SelectItem
                                    key={category.value}
                                    value={category.value}
                                >
                                    {category.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.church_category} />
                </div>
            )}

            {isChurch && specialistActivityOptions.length > 0 && (
                <div className="grid gap-2">
                    <Label htmlFor="church_activity_category">
                        Activity Category{' '}
                        <span className="text-destructive">*</span>
                    </Label>
                    <Select
                        value={data.church_activity_category ?? ''}
                        onValueChange={(value) =>
                            setData('church_activity_category', value)
                        }
                        disabled={lockSpecialistActivity}
                    >
                        <SelectTrigger id="church_activity_category">
                            <SelectValue placeholder="Select an activity category" />
                        </SelectTrigger>
                        <SelectContent>
                            {specialistActivityOptions.map((activity) => (
                                <SelectItem
                                    key={activity.value}
                                    value={activity.value}
                                >
                                    {activity.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.church_activity_category} />
                </div>
            )}

            {isDedication && (
                <section className="border-border flex flex-col gap-4 rounded-lg border p-4">
                    <div>
                        <h3 className="text-foreground text-sm font-semibold">
                            Dedication Details
                        </h3>
                        <p className="text-muted-foreground mt-1 text-xs">
                            Optional family and officiant information.
                        </p>
                    </div>
                    {(
                        [
                            ['child_name', 'Child name'],
                            ['mother_name', "Mother's name"],
                            ['father_name', "Father's name"],
                            ['officiating_pastor', 'Officiating pastor'],
                        ] as const
                    ).map(([field, label]) => (
                        <div key={field} className="grid gap-2">
                            <Label htmlFor={field}>{label}</Label>
                            <Input
                                id={field}
                                value={data[field] ?? ''}
                                onChange={(event) =>
                                    setData(field, event.target.value)
                                }
                            />
                            <InputError message={errors[field]} />
                        </div>
                    ))}
                </section>
            )}

            {needsEmergencyDetails && (
                <section className="border-border flex flex-col gap-4 rounded-lg border p-4">
                    <div>
                        <h3 className="text-foreground text-sm font-semibold">
                            Emergency and Medical Details
                        </h3>
                        <p className="text-muted-foreground mt-1 text-xs">
                            Optional details for this youth or children’s
                            activity.
                        </p>
                    </div>
                    {(
                        [
                            [
                                'emergency_contact_name',
                                'Emergency contact name',
                            ],
                            [
                                'emergency_contact_number',
                                'Emergency contact number',
                            ],
                        ] as const
                    ).map(([field, label]) => (
                        <div key={field} className="grid gap-2">
                            <Label htmlFor={field}>{label}</Label>
                            <Input
                                id={field}
                                value={data[field] ?? ''}
                                onChange={(event) =>
                                    setData(field, event.target.value)
                                }
                            />
                            <InputError message={errors[field]} />
                        </div>
                    ))}
                    <div className="grid gap-2">
                        <Label htmlFor="medical_notes">Medical notes</Label>
                        <textarea
                            id="medical_notes"
                            value={data.medical_notes ?? ''}
                            onChange={(event) =>
                                setData('medical_notes', event.target.value)
                            }
                            rows={3}
                            className="border-input bg-input placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-20 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                        />
                        <InputError message={errors.medical_notes} />
                    </div>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="parental_consent"
                            checked={data.parental_consent === true}
                            onCheckedChange={(checked) =>
                                setData('parental_consent', checked === true)
                            }
                        />
                        <Label htmlFor="parental_consent">
                            Parental consent received
                        </Label>
                        <InputError message={errors.parental_consent} />
                    </div>
                </section>
            )}

            {needsTeacherDetails && (
                <section className="border-border flex flex-col gap-4 rounded-lg border p-4">
                    <div>
                        <h3 className="text-foreground text-sm font-semibold">
                            Class Details
                        </h3>
                        <p className="text-muted-foreground mt-1 text-xs">
                            Optional teacher and schedule information.
                        </p>
                    </div>
                    {(
                        [
                            ['assigned_teacher', 'Assigned teacher'],
                            ['program_day_session', 'Program day / session'],
                        ] as const
                    ).map(([field, label]) => (
                        <div key={field} className="grid gap-2">
                            <Label htmlFor={field}>{label}</Label>
                            <Input
                                id={field}
                                value={data[field] ?? ''}
                                onChange={(event) =>
                                    setData(field, event.target.value)
                                }
                            />
                            <InputError message={errors[field]} />
                        </div>
                    ))}
                </section>
            )}

            <div className="grid gap-2">
                <Label htmlFor="event_name">
                    Event Name <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="event_name"
                    value={data.event_name}
                    onChange={(e) => setData('event_name', e.target.value)}
                    placeholder={
                        isChurch
                            ? 'e.g. Sunday Worship Gathering'
                            : 'e.g. Young Professionals Community Run #1'
                    }
                />
                <InputError message={errors.event_name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">Description</Label>
                <textarea
                    id="description"
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    rows={4}
                    className="border-input bg-input placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-24 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                    placeholder={
                        isChurch
                            ? 'Add the event purpose, theme, or notes for participants...'
                            : "Brief description of the event, what participants can expect, who it's for..."
                    }
                />
                <InputError message={errors.description} />
            </div>

            {isChurch && (
                <div className="grid gap-2">
                    <Label htmlFor="coordinator">Leader / Coordinator</Label>
                    <Input
                        id="coordinator"
                        value={data.coordinator ?? ''}
                        maxLength={255}
                        onChange={(e) => setData('coordinator', e.target.value)}
                        placeholder="Optional"
                    />
                    <InputError message={errors.coordinator} />
                </div>
            )}

            {/* Accessibility — participant-facing qualifications.
                Running events only. Church events do not set these
                flags; the section is hidden when event_type is church.
                See event-creation-wizard.md. */}
            {!isChurch && (
                <section className="flex flex-col gap-3 border-t border-white/5 pt-6">
                    <div>
                        <h3 className="text-muted-foreground text-sm font-semibold tracking-wider uppercase">
                            Accessibility
                        </h3>
                        <p className="text-muted-foreground mt-1 text-xs">
                            Everything is on by default. Uncheck any that do not
                            apply. These appear on the event page so
                            participants know what to expect and can decide if
                            the event suits them.
                        </p>
                    </div>

                    <AccessibilityGrid
                        value={accessibilityValue}
                        onChange={(field, checked) => setData(field, checked)}
                    />
                </section>
            )}
        </div>
    );
}
