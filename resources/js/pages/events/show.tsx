import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowLeft,
    Calendar,
    ChevronDown,
    ClipboardCheck,
    Clock,
    ExternalLink,
    FileText,
    MapPin,
    Pencil,
    Tag,
    Trash2,
    UserPlus,
    UserMinus,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import EventDeletionOtpDialog from '@/components/event-deletion-otp-dialog';
import { useConfirmDialog } from '@/hooks/use-confirm-dialog';

type Partner = {
    name: string;
    type: string;
};

type FaqEntry = {
    question: string;
    answer: string;
};

type EventData = {
    id: number;
    event_name: string;
    event_type: string;
    event_type_label: string;
    description: string | null;
    event_date: string | null;
    start_time: string | null;
    end_time: string | null;
    venue: string | null;
    venue_address: string | null;
    venue_latitude: number | null;
    venue_longitude: number | null;
    distance_label: string | null;
    course_url: string | null;
    partners: Partner[] | null;
    faq: FaqEntry[] | null;
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
    registration_start: string | null;
    registration_end: string | null;
    creator: string | null;
    created_at: string | null;
    can_edit: boolean;
    can_open_registration: boolean;
    can_close_registration: boolean;
};

type RelatedCounts = {
    registration_fields: number;
    registrations: number;
    attendances: number;
};

type Props = {
    event: EventData;
    related: RelatedCounts;
    has_related_records: boolean;
    requires_otp: boolean;
    can_record_attendance: boolean;
};

const statusStyles: Record<string, string> = {
    draft: 'bg-secondary text-foreground',
    registration_open: 'bg-lime-brand/20 text-lime-brand',
    registration_closed: 'bg-yellow-500/15 text-yellow-500',
    ongoing: 'bg-accent/20 text-accent',
    completed: 'bg-green-500/15 text-green-500',
    cancelled: 'bg-destructive/15 text-destructive',
};

const PARTNER_TYPE_LABELS: Record<string, string> = {
    host: 'Host',
    sponsor: 'Sponsor',
    food: 'Food',
    beverage: 'Beverage',
    medical: 'Medical',
    organization: 'Organization',
    media: 'Media',
    logistics: 'Logistics',
    other: 'Other',
};

const ACCESSIBILITY_LABELS: { key: keyof EventData; label: string }[] = [
    { key: 'walkers_welcome', label: 'Walkers welcome' },
    { key: 'all_paces_welcome', label: 'All paces welcome' },
    { key: 'all_ages_welcome', label: 'All ages welcome' },
    { key: 'stroller_friendly', label: 'Stroller friendly' },
    { key: 'wheelchair_accessible', label: 'Wheelchair accessible' },
    { key: 'sweeper_present', label: 'Sweeper present' },
    { key: 'service_animals_allowed', label: 'Service animals allowed' },
    { key: 'leashed_pets_allowed', label: 'Leashed pets allowed' },
    { key: 'quiet_space_available', label: 'Quiet space available' },
];

function DetailRow({
    icon: Icon,
    label,
    value,
}: {
    icon: React.ComponentType<{ className?: string }>;
    label: string;
    value: string | null;
}) {
    return (
        <div className="flex items-start gap-3 border-b border-white/5 py-3 last:border-b-0">
            <Icon className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
            <div className="flex flex-1 flex-col">
                <span className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                    {label}
                </span>
                <span className="mt-0.5 text-sm text-foreground">
                    {value ?? '—'}
                </span>
            </div>
        </div>
    );
}

function FaqItem({ entry }: { entry: FaqEntry }) {
    const [open, setOpen] = useState(false);

    return (
        <Collapsible open={open} onOpenChange={setOpen}>
            <CollapsibleTrigger className="flex w-full items-center justify-between gap-3 rounded-lg border border-white/5 bg-white/[0.02] px-4 py-3 text-left text-sm font-medium text-foreground transition-colors hover:bg-white/[0.04]">
                {entry.question}
                <ChevronDown
                    className={`h-4 w-4 shrink-0 text-muted-foreground transition-transform ${
                        open ? 'rotate-180' : ''
                    }`}
                />
            </CollapsibleTrigger>
            <CollapsibleContent className="px-4 pt-2 pb-3 text-sm text-muted-foreground">
                {entry.answer}
            </CollapsibleContent>
        </Collapsible>
    );
}

export default function EventsShow({
    event,
    related,
    has_related_records,
    requires_otp,
    can_record_attendance,
}: Props) {
    const { dialog, openConfirm } = useConfirmDialog();
    const [otpOpen, setOtpOpen] = useState(false);

    // The registration form is editable only while the event is Draft.
    // After Open Registration, the form is locked. The show page uses
    // this to enable/disable the "Create Registration Form" action.
    // The server enforces the same rule via canEditRegistrationForm().
    const canEditRegistrationForm = event.status === 'draft';

    const openRegistration = () => {
        openConfirm({
            variant: 'primary',
            title: 'Open registration?',
            description:
                'Participants will be able to register for this event. The registration form will lock and can no longer be edited.',
            confirmLabel: 'Open Registration',
            onConfirm: () => router.post(`/events/${event.id}/open-registration`),
        });
    };

    const closeRegistration = () => {
        openConfirm({
            variant: 'warning',
            title: 'Close registration?',
            description:
                'No new participants can register after this. This action cannot be undone.',
            confirmLabel: 'Close Registration',
            onConfirm: () => router.post(`/events/${event.id}/close-registration`),
        });
    };

    const requestDelete = () => {
        openConfirm({
            variant: 'danger',
            title: has_related_records
                ? 'Delete Event and Related Data?'
                : 'Delete Event?',
            description: has_related_records
                ? 'This event already has related records. Deleting it will permanently remove the event and its associated data. This action cannot be undone.'
                : 'This will permanently delete this event. This action cannot be undone.',
            confirmLabel: has_related_records ? 'Delete Everything' : 'Delete Event',
            children: has_related_records ? (
                <ul className="ml-1 list-disc space-y-1 pl-4 text-sm text-muted-foreground">
                    {related.registration_fields > 0 && (
                        <li>Registration Fields: {related.registration_fields}</li>
                    )}
                    {related.registrations > 0 && (
                        <li>Registrations: {related.registrations}</li>
                    )}
                    {related.attendances > 0 && (
                        <li>Attendance Records: {related.attendances}</li>
                    )}
                </ul>
            ) : null,
            onConfirm: () => {
                if (requires_otp) {
                    setOtpOpen(true);
                } else {
                    router.delete(`/events/${event.id}`);
                }
            },
        });
    };

    const hasAccessibility = ACCESSIBILITY_LABELS.some(
        ({ key }) => event[key] === true,
    );

    const hasPartners = event.partners !== null && event.partners.length > 0;

    const hasFaq = event.faq !== null && event.faq.length > 0;

    const hasCoordinates =
        event.venue_latitude !== null && event.venue_longitude !== null;

    const osmUrl = hasCoordinates
        ? `https://www.openstreetmap.org/?mlat=${event.venue_latitude}&mlon=${event.venue_longitude}#map=16/${event.venue_latitude}/${event.venue_longitude}`
        : null;

    return (
        <>
            <Head title={event.event_name} />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-6">
                <Link
                    href="/events"
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Events
                </Link>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            {event.event_name}
                        </h1>
                        <div className="mt-2 flex flex-wrap items-center gap-3">
                            <span
                                className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${
                                    statusStyles[event.status] ?? statusStyles.draft
                                }`}
                            >
                                {event.status_label}
                            </span>
                            {event.distance_label && (
                                <span className="text-xs text-muted-foreground">
                                    {event.distance_label}
                                </span>
                            )}
                            {event.creator && (
                                <span className="text-xs text-muted-foreground">
                                    Created by {event.creator}
                                </span>
                            )}
                        </div>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
                    {/* Left: Event details */}
                    <div className="flex flex-col gap-6">
                        <div className="glass-panel rounded-xl p-6">
                            <h2 className="mb-3 text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                                Event Details
                            </h2>

                            <DetailRow
                                icon={Tag}
                                label="Event Type"
                                value={event.event_type_label}
                            />
                            <DetailRow
                                icon={Calendar}
                                label="Event Date"
                                value={event.event_date}
                            />
                            <DetailRow
                                icon={Clock}
                                label="Time"
                                value={
                                    event.start_time && event.end_time
                                        ? `${event.start_time} – ${event.end_time}`
                                        : event.start_time ?? event.end_time
                                }
                            />
                            {event.distance_label && (
                                <DetailRow
                                    icon={MapPin}
                                    label="Distance"
                                    value={event.distance_label}
                                />
                            )}
                            <DetailRow
                                icon={FileText}
                                label="Description"
                                value={event.description}
                            />
                            <DetailRow
                                icon={Clock}
                                label="Registration Window"
                                value={
                                    event.registration_start &&
                                    event.registration_end
                                        ? `${event.registration_start} → ${event.registration_end}`
                                        : event.registration_start
                                          ? `Opened ${event.registration_start}`
                                          : event.registration_end
                                            ? `Closed ${event.registration_end}`
                                            : null
                                }
                            />
                        </div>

                        {/* Venue */}
                        {(event.venue || event.venue_address) && (
                            <div className="glass-panel rounded-xl p-6">
                                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                                    Venue
                                </h2>

                                {event.venue && (
                                    <p className="text-sm font-medium text-foreground">
                                        {event.venue}
                                    </p>
                                )}
                                {event.venue_address && (
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {event.venue_address}
                                    </p>
                                )}
                                {osmUrl && (
                                    <a
                                        href={osmUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="mt-3 inline-flex items-center gap-1.5 text-xs font-medium text-lime-brand transition-colors hover:text-lime-brand/80"
                                    >
                                        View on OpenStreetMap
                                        <ExternalLink className="h-3 w-3" />
                                    </a>
                                )}
                            </div>
                        )}

                        {/* Course */}
                        {event.course_url && (
                            <div className="glass-panel rounded-xl p-6">
                                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                                    Course
                                </h2>
                                <a
                                    href={event.course_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-2 text-sm font-medium text-lime-brand transition-colors hover:text-lime-brand/80"
                                >
                                    View Route
                                    <ExternalLink className="h-4 w-4" />
                                </a>
                            </div>
                        )}

                        {/* Partners */}
                        {hasPartners && (
                            <div className="glass-panel rounded-xl p-6">
                                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                                    Partners
                                </h2>
                                <ul className="flex flex-col">
                                    {event.partners!.map((partner, i) => (
                                        <li
                                            key={i}
                                            className="flex items-center gap-3 border-b border-white/5 py-2.5 last:border-b-0"
                                        >
                                            <span className="w-24 shrink-0 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                                {PARTNER_TYPE_LABELS[partner.type] ??
                                                    partner.type}
                                            </span>
                                            <span className="text-sm text-foreground">
                                                {partner.name}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        {/* Accessibility */}
                        {hasAccessibility && (
                            <div className="glass-panel rounded-xl p-6">
                                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                                    Accessibility
                                </h2>
                                <ul className="grid gap-2 sm:grid-cols-2">
                                    {ACCESSIBILITY_LABELS.filter(
                                        ({ key }) => event[key] === true,
                                    ).map(({ key, label }) => (
                                        <li
                                            key={key}
                                            className="flex items-center gap-2 text-sm text-foreground"
                                        >
                                            <span className="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-lime-brand/20 text-[10px] text-lime-brand">
                                                ✓
                                            </span>
                                            {label}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        {/* FAQ */}
                        {hasFaq && (
                            <div className="glass-panel rounded-xl p-6">
                                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                                    Frequently Asked Questions
                                </h2>
                                <div className="flex flex-col gap-2">
                                    {event.faq!.map((entry, i) => (
                                        <FaqItem key={i} entry={entry} />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Right: Workflow actions */}
                    <div className="glass-panel flex h-fit flex-col gap-4 rounded-xl p-6">
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                            Workflow Actions
                        </h2>

                        <p className="text-xs text-muted-foreground">
                            Actions become available as the workflow progresses.
                        </p>

                        <div className="flex flex-col gap-2">
                            {event.can_edit ? (
                                <Button variant="outline" className="justify-start" asChild>
                                    <Link href={`/events/${event.id}/edit`}>
                                        <Pencil className="mr-2 h-4 w-4" />
                                        Edit Event
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    disabled
                                    variant="outline"
                                    className="justify-start"
                                    title="This event can no longer be edited"
                                >
                                    <Pencil className="mr-2 h-4 w-4" />
                                    Edit Event
                                </Button>
                            )}

                            {canEditRegistrationForm ? (
                                <Button variant="outline" className="justify-start" asChild>
                                    <Link href={`/events/${event.id}/registration-form`}>
                                        <FileText className="mr-2 h-4 w-4" />
                                        Create Registration Form
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    disabled
                                    variant="outline"
                                    className="justify-start"
                                    title="The registration form is locked after registration opens"
                                >
                                    <FileText className="mr-2 h-4 w-4" />
                                    Create Registration Form
                                </Button>
                            )}

                            {can_record_attendance && (
                                <Button
                                    variant="outline"
                                    className="justify-start border-lime-brand/40 text-lime-brand hover:border-lime-brand hover:bg-lime-brand hover:text-navy-900"
                                    asChild
                                >
                                    <Link href={`/events/${event.id}/attendance`}>
                                        <ClipboardCheck className="mr-2 h-4 w-4" />
                                        Record Attendance
                                    </Link>
                                </Button>
                            )}

                            {event.can_open_registration ? (
                                <Button
                                    onClick={openRegistration}
                                    className="justify-start bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                                >
                                    <UserPlus className="mr-2 h-4 w-4" />
                                    Open Registration
                                </Button>
                            ) : event.can_close_registration ? (
                                <Button
                                    onClick={closeRegistration}
                                    variant="outline"
                                    className="justify-start"
                                >
                                    <UserMinus className="mr-2 h-4 w-4" />
                                    Close Registration
                                </Button>
                            ) : (
                                <Button
                                    disabled
                                    variant="outline"
                                    className="justify-start"
                                    title="Registration actions are not available in this state"
                                >
                                    <UserPlus className="mr-2 h-4 w-4" />
                                    Open Registration
                                </Button>
                            )}
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border border-destructive/30 bg-destructive/5 p-6">
                    <h2 className="text-sm font-semibold uppercase tracking-wider text-destructive">
                        Danger Zone
                    </h2>
                    <p className="mt-2 text-xs text-muted-foreground">
                        Deleting this event is permanent and cannot be undone. Related records
                        (registration fields, registrations, attendance) will also be removed.
                        Participants are preserved.
                    </p>
                    <Button
                        variant="destructive"
                        className="mt-4"
                        onClick={requestDelete}
                    >
                        <Trash2 className="mr-2 h-4 w-4" />
                        Delete Event
                    </Button>
                </div>
            </div>

            {dialog}

            <EventDeletionOtpDialog
                eventId={event.id}
                open={otpOpen}
                onOpenChange={setOtpOpen}
            />
        </>
    );
}

EventsShow.layout = {
    breadcrumbs: [
        { title: 'Events', href: '/events' },
        { title: 'View', href: '#' },
    ],
};
