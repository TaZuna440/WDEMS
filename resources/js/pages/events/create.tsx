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

type EventTypeOption = {
    value: string;
    label: string;
};

type ChurchCategoryOption = {
    value: string;
    label: string;
};

type Props = {
    event_types: EventTypeOption[];
    church_categories: ChurchCategoryOption[];
};

/**
 * Steps for running events (Community Run). Four steps with the
 * running-only fields (distance on Schedule, partners and
 * accessibility on Extras and Basics respectively).
 */
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

/**
 * Steps for church events. Three steps — no Extras, because church
 * events do not have partners. Distance on Schedule is hidden in the
 * step component when the event is a church event. Accessibility
 * flags in Basics are hidden for church events too.
 */
const CHURCH_STEPS: WizardStepConfig[] = [
    {
        id: 'basics',
        label: 'Details',
        fields: [
            'event_type',
            'church_category',
            'event_name',
            'description',
        ],
        requiredFields: ['event_type', 'church_category', 'event_name'],
        validate: validateBasics,
    },
    {
        id: 'schedule',
        label: 'Schedule',
        fields: [
            'event_date',
            'start_time',
            'end_time',
        ],
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
}: Props) {
    const {
        data,
        setData: inertiaSetData,
        post,
        processing,
        errors,
        clearErrors,
    } = useForm({
        // Step 1 — Details
        event_type: '',
        church_category: '',
        event_name: '',
        description: '',

        // Step 2 — Schedule
        event_date: '',
        start_time: '',
        end_time: '',
        distance_value: null as number | null,
        distance_unit: 'km' as 'km' | 'mi',

        // Step 3 — Venue
        venue: '',
        venue_address: '',
        venue_map_url: '',
        venue_latitude: null as number | null,
        venue_longitude: null as number | null,

        // Step 4 — Extras (running-only)
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

    /**
     * Wrap Inertia's setData so that editing a field also clears any
     * stale server error for that field (or its subkeys, e.g.
     * `partners.0.name`).
     *
     * useForm keeps the last server error bag until the next
     * submission. Without this, a stale error like "you already have
     * an active event with this name" persists after the user fixes
     * the field — and the wizard's error-jump effect keeps bouncing
     * them back to the step that owns that field.
     */
    const setData = (key: string, value: unknown) => {
        (inertiaSetData as unknown as (key: string, value: unknown) => void)(key, value);

        const matching = Object.keys(errors).filter(
            (k) => k === key || k.startsWith(`${key}.`),
        );

        if (matching.length > 0) {
            (clearErrors as unknown as (...fields: string[]) => void)(...matching);
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
                    href="/events"
                    className="inline-flex w-fit items-center gap-2 text-sm text-on-image-muted transition-colors hover:text-on-image"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Events
                </Link>

                <div>
                    <h1 className="text-on-image text-2xl font-semibold">
                        Create Event
                    </h1>
                    <p className="text-on-image-muted mt-1 text-sm">
                        Set up a new event. Your progress is saved
                        automatically — you can leave and come back.
                    </p>
                </div>

                <Wizard
                    steps={steps}
                    storageKey="wdems-wizard-create"
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
