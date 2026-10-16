import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    Clock,
    MapPin,
    Pencil,
    Plus,
    Trash2,
    UserRound,
    Users,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import DatePicker from '@/components/date-picker';
import InputError from '@/components/input-error';
import TimePicker from '@/components/time-picker';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useConfirmDialog } from '@/hooks/use-confirm-dialog';

type EventRow = {
    id: number;
    name: string;
    description: string | null;
    event_date: string;
    start_time: string | null;
    end_time: string | null;
    venue: string | null;
    facilitator: string | null;
    notes: string | null;
    group_count: number;
    attendance_count: number;
};

type FellowshipTypeData = {
    value: string;
    label: string;
};

type Props = {
    fellowship_type: FellowshipTypeData;
    events: EventRow[];
};

function formatDate(iso: string): string {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

export default function FellowshipShow({ fellowship_type, events }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<EventRow | null>(null);
    const { dialog, openConfirm } = useConfirmDialog();

    const deleteEvent = (event: EventRow) => {
        openConfirm({
            variant: 'danger',
            title: 'Delete this gathering?',
            description: `"${event.name}" on ${formatDate(event.event_date)} and its ${event.attendance_count} attendance ${event.attendance_count === 1 ? 'record' : 'records'} will be permanently removed. Participants are preserved.`,
            confirmLabel: 'Delete',
            onConfirm: () =>
                router.delete(`/fellowship/events/${event.id}`, {
                    preserveScroll: true,
                }),
        });
    };

    return (
        <>
            <Head title={`${fellowship_type.label} — Fellowship`} />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-6">
                <Link
                    href="/events/church/fellowship"
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    All Fellowship
                </Link>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="pl-10">
                        <h1 className="text-foreground text-2xl font-semibold">
                            {fellowship_type.label}
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {events.length === 0
                                ? 'No gatherings yet.'
                                : `${events.length} ${
                                      events.length === 1
                                          ? 'gathering'
                                          : 'gatherings'
                                  }`}
                        </p>
                    </div>

                    <Button
                        onClick={() => setCreateOpen(true)}
                        className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        Add Gathering
                    </Button>
                </div>

                {events.length === 0 ? (
                    <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-16 text-center">
                        <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-lime-brand/15">
                            <Users className="h-8 w-8 text-lime-brand" />
                        </div>
                        <h2 className="mb-2 text-lg font-semibold text-foreground">
                            No gatherings yet
                        </h2>
                        <p className="mb-6 max-w-sm text-sm text-muted-foreground">
                            Add the first gathering to begin tracking
                            attendance.
                        </p>
                        <Button
                            onClick={() => setCreateOpen(true)}
                            className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Add Gathering
                        </Button>
                    </div>
                ) : (
                    <div className="flex flex-col gap-3">
                        {events.map((event) => (
                            <div
                                key={event.id}
                                className="glass-panel flex flex-col gap-3 rounded-xl p-5 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <Link
                                    href={`/fellowship/events/${event.id}/attendance`}
                                    className="flex flex-1 flex-col gap-2"
                                >
                                    <div className="flex items-center gap-2">
                                        <CalendarDays className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm font-semibold text-foreground">
                                            {event.name}
                                        </span>
                                    </div>
                                    {event.description && (
                                        <p className="line-clamp-2 text-xs text-muted-foreground">
                                            {event.description}
                                        </p>
                                    )}
                                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                        <span>{formatDate(event.event_date)}</span>
                                        {event.start_time && event.end_time && (
                                            <span className="inline-flex items-center gap-1.5">
                                                <Clock className="h-3 w-3" />
                                                {event.start_time} –{' '}
                                                {event.end_time}
                                            </span>
                                        )}
                                        {event.venue && (
                                            <span className="inline-flex items-center gap-1.5">
                                                <MapPin className="h-3 w-3" />
                                                {event.venue}
                                            </span>
                                        )}
                                        {event.facilitator && (
                                            <span className="inline-flex items-center gap-1.5">
                                                <UserRound className="h-3 w-3" />
                                                {event.facilitator}
                                            </span>
                                        )}
                                        <span className="inline-flex items-center gap-1.5">
                                            <Users className="h-3 w-3" />
                                            {event.attendance_count} marked
                                        </span>
                                    </div>
                                </Link>

                                <div className="flex shrink-0 items-center gap-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setEditTarget(event)}
                                        aria-label={`Edit ${event.name}`}
                                    >
                                        <Pencil className="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => deleteEvent(event)}
                                        className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        aria-label={`Delete ${event.name}`}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {dialog}

            {createOpen && (
                <EventFormDialog
                    mode="create"
                    typeValue={fellowship_type.value}
                    open={createOpen}
                    onOpenChange={setCreateOpen}
                />
            )}

            {editTarget !== null && (
                <EventFormDialog
                    mode="edit"
                    typeValue={fellowship_type.value}
                    event={editTarget}
                    open={editTarget !== null}
                    onOpenChange={(open) => {
                        if (!open) setEditTarget(null);
                    }}
                />
            )}
        </>
    );
}

type FormMode = 'create' | 'edit';

type FormProps = {
    mode: FormMode;
    typeValue: string;
    event?: EventRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function EventFormDialog({
    mode,
    typeValue,
    event,
    open,
    onOpenChange,
}: FormProps) {
    const isEdit = mode === 'edit';

    const form = useForm({
        name: event?.name ?? '',
        description: event?.description ?? '',
        event_date: event?.event_date ?? '',
        start_time: event?.start_time ?? '',
        end_time: event?.end_time ?? '',
        venue: event?.venue ?? '',
        facilitator: event?.facilitator ?? '',
        notes: event?.notes ?? '',
    });

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();

        const onSuccess = () => {
            form.reset();
            onOpenChange(false);
        };

        if (isEdit && event) {
            form.put(`/fellowship/events/${event.id}`, {
                onSuccess,
                preserveScroll: true,
            });
        } else {
            form.post(`/fellowship/${typeValue}/events`, {
                onSuccess,
                preserveScroll: true,
            });
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? 'Edit Gathering' : 'Add Gathering'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEdit
                            ? 'Update the details of this gathering.'
                            : 'Create a new fellowship gathering.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={onSubmit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="fellowship-name">
                            Name{' '}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="fellowship-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="e.g. November Family Fellowship"
                            autoFocus
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="fellowship-description">
                            Description
                        </Label>
                        <textarea
                            id="fellowship-description"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                            rows={3}
                            className="border-input bg-input placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-20 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                            placeholder="Optional. What is this gathering about?"
                        />
                        <InputError message={form.errors.description} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="fellowship-date">
                            Date{' '}
                            <span className="text-destructive">*</span>
                        </Label>
                        <DatePicker
                            id="fellowship-date"
                            value={form.data.event_date}
                            onChange={(v) =>
                                form.setData('event_date', v)
                            }
                            placeholder="Pick a date"
                        />
                        <InputError message={form.errors.event_date} />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="fellowship-start">
                                Start Time
                            </Label>
                            <TimePicker
                                id="fellowship-start"
                                value={form.data.start_time}
                                onChange={(v) =>
                                    form.setData('start_time', v)
                                }
                            />
                            <InputError message={form.errors.start_time} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="fellowship-end">
                                End Time
                            </Label>
                            <TimePicker
                                id="fellowship-end"
                                value={form.data.end_time}
                                onChange={(v) =>
                                    form.setData('end_time', v)
                                }
                                clearable
                            />
                            <InputError message={form.errors.end_time} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="fellowship-venue">Venue</Label>
                        <Input
                            id="fellowship-venue"
                            value={form.data.venue}
                            onChange={(e) =>
                                form.setData('venue', e.target.value)
                            }
                            placeholder="Where does the gathering take place?"
                        />
                        <InputError message={form.errors.venue} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="fellowship-facilitator">
                            Facilitator
                        </Label>
                        <Input
                            id="fellowship-facilitator"
                            value={form.data.facilitator}
                            onChange={(e) =>
                                form.setData('facilitator', e.target.value)
                            }
                            placeholder="Who is leading the gathering?"
                        />
                        <InputError message={form.errors.facilitator} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="fellowship-notes">Notes</Label>
                        <textarea
                            id="fellowship-notes"
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                            rows={3}
                            className="border-input bg-input placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-20 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                            placeholder="Optional notes about this gathering"
                        />
                        <InputError message={form.errors.notes} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                        >
                            {form.processing
                                ? 'Saving…'
                                : isEdit
                                  ? 'Save Changes'
                                  : 'Create Gathering'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

FellowshipShow.layout = {
    breadcrumbs: [
        { title: 'Fellowship', href: '/events/church/fellowship' },
        { title: 'Gathering', href: '#' },
    ],
};
