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

type Props = {
    event_types: EventTypeOption[];
};

const STEPS: WizardStepConfig[] = [
    {
        id: 'basics',
        label: 'Details',
        fields: ['event_type', 'event_name', 'description'],
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
            'course_url',
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
        fields: [
            'venue',
            'venue_address',
            'venue_map_url',
        ],
        requiredFields: [
            'venue',
            'venue_address',
        ],
        validate: validateVenue,
    },
    {
        id: 'extras',
        label: 'Extras',
        fields: [
            'partners',
            'walkers_welcome',
            'all_paces_welcome',
            'all_ages_welcome',
            'stroller_friendly',
            'wheelchair_accessible',
            'sweeper_present',
            'service_animals_allowed',
            'leashed_pets_allowed',
            'quiet_space_available',
        ],
        validate: validateExtras,
    },
];

export default function EventsCreate({ event_types }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        // Step 1 — Details
        event_type: '',
        event_name: '',
        description: '',

        // Step 2 — Schedule
        event_date: '',
        start_time: '',
        end_time: '',
        distance_value: null as number | null,
        distance_unit: 'km' as 'km' | 'mi',
        course_url: '',

        // Step 3 — Venue
        venue: '',
        venue_address: '',
        venue_map_url: '',
        venue_latitude: null as number | null,
        venue_longitude: null as number | null,

        // Step 4 — Extras
        partners: [] as { name: string; type: string }[],
        walkers_welcome: false,
        all_paces_welcome: false,
        all_ages_welcome: false,
        stroller_friendly: false,
        wheelchair_accessible: false,
        sweeper_present: false,
        service_animals_allowed: false,
        leashed_pets_allowed: false,
        quiet_space_available: false,
    });

    const submit = () => {
        post('/events');
    };

    return (
        <>
            <Head title="Create Event" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6">
                <Link
                    href="/events"
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Events
                </Link>

                <div>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Create Event
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Set up a new running event. Your progress is saved
                        automatically — you can leave and come back.
                    </p>
                </div>

                <Wizard
                    steps={STEPS}
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
