import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import Wizard, { type WizardStepConfig } from '@/components/wizard';
import BasicsStep from './step/BasicsStep';
import ExtrasStep from './step/ExtrasStep';
import ScheduleStep from './step/ScheduleStep';
import VenueStep from './step/VenueStep';

type EventData = {
    id: number;
    event_type: string;
    event_type_label: string;
    event_name: string;
    description: string | null;
    event_date: string | null;
    start_time: string | null;
    end_time: string | null;
    distance_value: number | null;
    distance_unit: string;
    course_url: string | null;
    venue: string | null;
    venue_address: string | null;
    venue_latitude: number | null;
    venue_longitude: number | null;
    rsvp_required: boolean;
    partners: { name: string; type: string }[] | null;
    faq: { question: string; answer: string }[] | null;
    walkers_welcome: boolean;
    all_paces_welcome: boolean;
    all_ages_welcome: boolean;
    stroller_friendly: boolean;
    wheelchair_accessible: boolean;
    sweeper_present: boolean;
    service_animals_allowed: boolean;
    leashed_pets_allowed: boolean;
    quiet_space_available: boolean;
    status: string;
    status_label: string;
};

type Props = {
    event: EventData;
};

const STEPS: WizardStepConfig[] = [
    {
        id: 'basics',
        label: 'Basics',
        fields: ['event_type', 'event_name', 'description'],
        requiredFields: ['event_name'],
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
    },
    {
        id: 'venue',
        label: 'Venue',
        fields: [
            'venue',
            'venue_address',
            'venue_latitude',
            'venue_longitude',
        ],
        requiredFields: [
            'venue',
            'venue_address',
            'venue_latitude',
            'venue_longitude',
        ],
    },
    {
        id: 'extras',
        label: 'Extras',
        fields: [
            'rsvp_required',
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
    },
];

function normalizeDistance(value: number | string | null): number | null {
    if (value === null || value === undefined || value === '') return null;
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return isNaN(num) ? null : num;
}

function normalizeUnit(unit: string): 'km' | 'mi' {
    return unit === 'mi' ? 'mi' : 'km';
}

export default function EventsEdit({ event }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        // Step 1 — Basics
        event_type: event.event_type,
        event_name: event.event_name,
        description: event.description ?? '',

        // Step 2 — Schedule
        event_date: event.event_date ?? '',
        start_time: event.start_time ?? '',
        end_time: event.end_time ?? '',
        distance_value: normalizeDistance(event.distance_value),
        distance_unit: normalizeUnit(event.distance_unit),
        course_url: event.course_url ?? '',

        // Step 3 — Venue
        venue: event.venue ?? '',
        venue_address: event.venue_address ?? '',
        venue_latitude: event.venue_latitude,
        venue_longitude: event.venue_longitude,

        // Step 4 — Extras
        rsvp_required: event.rsvp_required,
        partners: event.partners ?? [],
        walkers_welcome: event.walkers_welcome,
        all_paces_welcome: event.all_paces_welcome,
        all_ages_welcome: event.all_ages_welcome,
        stroller_friendly: event.stroller_friendly,
        wheelchair_accessible: event.wheelchair_accessible,
        sweeper_present: event.sweeper_present,
        service_animals_allowed: event.service_animals_allowed,
        leashed_pets_allowed: event.leashed_pets_allowed,
        quiet_space_available: event.quiet_space_available,
    });

    const submit = () => {
        put(`/events/${event.id}`);
    };

    return (
        <>
            <Head title={`Edit ${event.event_name}`} />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6">
                <Link
                    href={`/events/${event.id}`}
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Event
                </Link>

                <div>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Edit Event
                    </h1>
                    <div className="mt-2 flex items-center gap-3">
                        <span className="inline-flex rounded-full bg-secondary px-2.5 py-1 text-xs font-medium text-foreground">
                            {event.status_label}
                        </span>
                        <span className="text-sm text-muted-foreground">
                            {event.event_name}
                        </span>
                    </div>
                </div>

                <Wizard
                    steps={STEPS}
                    storageKey={`wdems-wizard-edit-${event.id}`}
                    data={data}
                    setData={setData}
                    errors={errors as Record<string, string>}
                    processing={processing}
                    onSubmit={submit}
                    submitLabel="Save Changes"
                >
                    {(stepId) => {
                        switch (stepId) {
                            case 'basics':
                                return (
                                    <BasicsStep
                                        data={data}
                                        setData={setData}
                                        errors={errors}
                                        eventTypes={[
                                            {
                                                value: event.event_type,
                                                label: event.event_type_label,
                                            },
                                        ]}
                                        isEdit
                                    />
                                );
                            case 'schedule':
                                return (
                                    <ScheduleStep
                                        data={data}
                                        setData={setData}
                                        errors={errors}
                                    />
                                );
                            case 'venue':
                                return (
                                    <VenueStep
                                        data={data}
                                        setData={setData}
                                        errors={errors}
                                    />
                                );
                            case 'extras':
                                return (
                                    <ExtrasStep
                                        data={data}
                                        setData={setData}
                                        errors={errors}
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

EventsEdit.layout = {
    breadcrumbs: [
        { title: 'Events', href: '#' },
        { title: 'Edit', href: '#' },
    ],
};
