import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import Wizard, { type WizardStepConfig } from '@/components/wizard';
import {
    validateBasics,
    validateExtras,
    validateSchedule,
    validateVenue,
} from '@/lib/event-validation';
import BasicsStep from './step/BasicsStep';
import ExtrasStep from './step/ExtrasStep';
import ScheduleStep from './step/ScheduleStep';
import VenueStep from './step/VenueStep';

type EventTypeOption = { value: string; label: string };
type ChurchCategoryOption = { value: string; label: string };

type Props = {
    event_types: EventTypeOption[];
    church_categories: ChurchCategoryOption[];
    /** Pre-fills event_type in the create flow. */
    preset_event_type?: string | null;
    /** Pre-fills church_category in the create flow. */
    preset_church_category?: string | null;
    /** Locks the event type dropdown. */
    lock_type?: boolean;
    /** Unique localStorage key for this create route. */
    storage_key?: string | null;
};

const RUNNING_STEPS: WizardStepConfig[] = [
    {
        id: 'basics',
        label: 'Details',
        fields: [
            'event_type',
            'event_name',
            'description',
            'walkers_welcome',
            'all_paces_welcome',
            'all_ages_welcome',
            'stroller_friendly',
            'wheelchair_accessible',
            'sweeper_present',
            'service_animals_allowed',
            'leashed_pets_allowed',
        ],
        requiredFields: ['event_type', 'event_name'],
        validate: validateBasics,
    },
    {
        id: 'schedule',
        label: 'Schedule',
        fields: [
            'event_date',
            'start_time',
            'end_time',
            'distance_value',
            'distance_unit',
        ],
        requiredFields: [
            'event_date',
            'start_time',
            'distance_value',
            'distance_unit',
        ],
        validate: validateSchedule,
    },
    {
        id: 'venue',
        label: 'Venue',
        fields: ['venue', 'venue_address', 'venue_map_url'],
        requiredFields: ['venue', 'venue_address'],
        validate: validateVenue,
    },
    {
        id: 'extras',
        label: 'Extras',
        fields: ['partners'],
        validate: validateExtras,
    },
];

const CHURCH_STEPS: WizardStepConfig[] = [
    {
        id: 'basics',
        label: 'Details',
        fields: ['event_type', 'church_category', 'event_name', 'description'],
        requiredFields: ['event_type', 'church_category', 'event_name'],
        validate: validateBasics,
    },
    {
        id: 'schedule',
        label: 'Schedule',
        fields: ['event_date', 'start_time', 'end_time'],
        requiredFields: ['event_date', 'start_time'],
        validate: validateSchedule,
    },
    {
        id: 'venue',
        label: 'Venue',
        fields: ['venue', 'venue_address', 'venue_map_url'],
        requiredFields: ['venue', 'venue_address'],
        validate: validateVenue,
    },
];

export default function EventsCreate({
    event_types,
    church_categories,
    preset_event_type = null,
    preset_church_category = null,
    lock_type = false,
    storage_key = null,
}: Props) {
    const {
        data,
        setData: inertiaSetData,
        post,
        processing,
        errors,
        clearErrors,
    } = useForm({
        event_type: preset_event_type ?? '',
        church_category: preset_church_category ?? '',
        event_name: '',
        description: '',
        event_date: '',
        start_time: '',
        end_time: '',
        distance_value: null as number | null,
        distance_unit: 'km' as 'km' | 'mi',
        venue: '',
        venue_address: '',
        venue_map_url: '',
        venue_latitude: null as number | null,
        venue_longitude: null as number | null,
        partners: [] as { name: string; type: string }[],
        walkers_welcome: true,
        all_paces_welcome: true,
        all_ages_welcome: true,
        stroller_friendly: true,
        wheelchair_accessible: true,
        sweeper_present: true,
        service_animals_allowed: true,
        leashed_pets_allowed: true,
    });

    const isChurch = data.event_type === 'church';
    const steps = isChurch ? CHURCH_STEPS : RUNNING_STEPS;

    const setData = (key: string, value: unknown) => {
        (inertiaSetData as unknown as (key: string, value: unknown) => void)(
            key,
            value,
        );
        const matching = Object.keys(errors).filter(
            (k) => k === key || k.startsWith(`${key}.`),
        );
        if (matching.length > 0) {
            (clearErrors as unknown as (...fields: string[]) => void)(
                ...matching,
            );
        }
    };

    const submit = () => {
        post('/events');
    };

    return (
        <>
            <Head title="Create Event" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6">
                <Link
                    href="/events/create"
                    className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-2 text-sm transition-colors"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back
                </Link>

                <div className="pl-10">
                    <h1 className="text-foreground text-2xl font-semibold">
                        Create Event
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Your progress is saved automatically — you can leave and
                        come back.
                    </p>
                </div>

                <Wizard
                    steps={steps}
                    storageKey={storage_key ?? 'wdems-wizard-create'}
                    data={data}
                    setData={setData}
                    errors={errors as Record<string, string>}
                    processing={processing}
                    onSubmit={submit}
                    submitLabel="Create Event"
                >
                    {(stepId, stepErrors) => {
                        switch (stepId) {
                            case 'basics':
                                return (
                                    <BasicsStep
                                        data={data}
                                        setData={setData}
                                        errors={stepErrors}
                                        eventTypes={event_types}
                                        churchCategories={church_categories}
                                        lockEventType={lock_type}
                                    />
                                );
                            case 'schedule':
                                return (
                                    <ScheduleStep
                                        data={data}
                                        setData={setData}
                                        errors={stepErrors}
                                    />
                                );
                            case 'venue':
                                return (
                                    <VenueStep
                                        data={data}
                                        setData={setData}
                                        errors={stepErrors}
                                    />
                                );
                            case 'extras':
                                return (
                                    <ExtrasStep
                                        data={data}
                                        setData={setData}
                                        errors={stepErrors}
                                    />
                                );
                            default:
                                return null;
                        }
                    }}
                </Wizard>
            </div>
        </>
    );
}

EventsCreate.layout = {
    breadcrumbs: [
        { title: 'Events', href: '/events' },
        { title: 'Create', href: '/events/create' },
    ],
};
