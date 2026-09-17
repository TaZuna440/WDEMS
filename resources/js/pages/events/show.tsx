import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Calendar,
    Clock,
    FileText,
    MapPin,
    Settings,
    Pencil,
    Tag,
    UserPlus,
    UserMinus,
    Lock,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useConfirmDialog } from '@/hooks/use-confirm-dialog';

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

type Props = {
    event: EventData;
};

const statusStyles: Record<string, string> = {
    draft: 'bg-secondary text-foreground',
    configured: 'bg-blue-500/15 text-blue-500',
    registration_open: 'bg-lime-brand/20 text-lime-brand',
    registration_closed: 'bg-yellow-500/15 text-yellow-500',
    ongoing: 'bg-accent/20 text-accent',
    completed: 'bg-green-500/15 text-green-500',
    cancelled: 'bg-destructive/15 text-destructive',
};

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

export default function EventsShow({ event }: Props) {
    const { dialog, openConfirm } = useConfirmDialog();

    const openRegistration = () => {
        openConfirm({
            variant: 'primary',
            title: 'Open registration?',
            description:
                'Participants will be able to register for this event.',
            confirmLabel: 'Open Registration',
            onConfirm: () =>
                router.post(`/events/${event.id}/open-registration`),
        });
    };

    const closeRegistration = () => {
        openConfirm({
            variant: 'warning',
            title: 'Close registration?',
            description:
                'No new participants can register after this. This action cannot be undone.',
            confirmLabel: 'Close Registration',
            onConfirm: () =>
                router.post(`/events/${event.id}/close-registration`),
        });
    };

    return (
        <>
            <Head title={event.event_name} />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-6">
                {/* Back link */}
                <Link
                    href="/events"
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Events
                </Link>

                {/* Header */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            {event.event_name}
                        </h1>
                        <div className="mt-2 flex items-center gap-3">
                            <span
                                className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${
                                    statusStyles[event.status] ??
                                    statusStyles.draft
                                }`}
                            >
                                {event.status_label}
                            </span>
                            {event.creator && (
                                <span className="text-xs text-muted-foreground">
                                    Created by {event.creator}
                                </span>
                            )}
                        </div>
                    </div>
                </div>

                {/* Two-column layout */}
                <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
                    {/* Left: Event details */}
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
                        <DetailRow
                            icon={MapPin}
                            label="Venue"
                            value={event.venue}
                        />
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

                    {/* Right: Workflow actions */}
                    <div className="glass-panel flex flex-col gap-4 rounded-xl p-6">
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                            Workflow Actions
                        </h2>

                        <p className="text-xs text-muted-foreground">
                            Actions become available as the workflow progresses.
                        </p>

                        <div className="flex flex-col gap-2">
                            {/* Edit — enabled when the event is still editable */}
                            {event.can_edit ? (
                                <Button
                                    variant="outline"
                                    className="justify-start"
                                    asChild
                                >
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

                            {/* Configure — links to the options page */}
                            <Button
                                variant="outline"
                                className="justify-start"
                                asChild
                            >
                                <Link href={`/events/${event.id}/options`}>
                                    <Settings className="mr-2 h-4 w-4" />
                                    Configure Options
                                </Link>
                            </Button>

                            {/* Open/Close Registration — dynamic */}
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

                        <div className="mt-2 flex items-start gap-2 rounded-md border border-white/5 bg-white/5 p-3">
                            <Lock className="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                            <p className="text-xs text-muted-foreground">
                                These actions are being built in later pages.
                                They will enable as each feature lands.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {dialog}
        </>
    );
}

EventsShow.layout = {
    breadcrumbs: [
        { title: 'Events', href: '/events' },
        { title: 'View', href: '#' },
    ],
};