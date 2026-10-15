import AccessibilityGrid, {
    type AccessibilityField,
} from '@/components/accessibility-grid';
import InputError from '@/components/input-error';
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

type FormData = {
    event_type: string;
    event_name: string;
    description: string;
} & Partial<Record<AccessibilityField, boolean>>;

type Props = {
    data: FormData;
    setData: (key: string, value: unknown) => void;
    errors: Record<string, string>;
    eventTypes: EventTypeOption[];
    isEdit?: boolean;
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
    isEdit = false,
}: Props) {
    const accessibilityValue: Partial<Record<AccessibilityField, boolean>> = {};
    for (const field of ACCESSIBILITY_FIELDS) {
        accessibilityValue[field] = data[field] === true;
    }

    return (
        <div className="flex flex-col gap-6">
            <div>
                <h2 className="text-lg font-semibold text-foreground">
                    Event Details
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    The core details of your event.
                </p>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="event_type">
                    Event Type <span className="text-destructive">*</span>
                </Label>
                <Select
                    value={data.event_type}
                    onValueChange={(value) => setData('event_type', value)}
                    disabled={isEdit}
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
                <p className="text-xs text-muted-foreground">
                    Event type cannot be changed after creation.
                </p>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="event_name">
                    Event Name <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="event_name"
                    value={data.event_name}
                    onChange={(e) => setData('event_name', e.target.value)}
                    placeholder="e.g. Young Professionals Community Run #1"
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
                    placeholder="Brief description of the event, what participants can expect, who it's for..."
                />
                <InputError message={errors.description} />
            </div>

            {/* Accessibility — participant-facing qualifications. Not
                "extras": participants use these to decide whether the
                event suits them. See event-creation-wizard.md. */}
            <section className="flex flex-col gap-3 border-t border-white/5 pt-6">
                <div>
                    <h3 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                        Accessibility
                    </h3>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Everything is on by default. Uncheck any that do not
                        apply. These appear on the event page so participants
                        know what to expect and can decide if the event suits
                        them.
                    </p>
                </div>

                <AccessibilityGrid
                    value={accessibilityValue}
                    onChange={(field, checked) => setData(field, checked)}
                />
            </section>
        </div>
    );
}
