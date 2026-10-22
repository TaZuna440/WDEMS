import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    Clock,
    Plus,
    Repeat,
    Trash2,
    Users,
} from 'lucide-react';
import { useState } from 'react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useConfirmDialog } from '@/hooks/use-confirm-dialog';
import { schedulingDateBounds } from '@/lib/scheduling-date-bounds';

type Occurrence = {
    id: number;
    occurrence_date: string;
    start_time: string | null;
    end_time: string | null;
    service_leader: string | null;
    topic: string | null;
    notes: string | null;
    member_count: number;
    first_timer_count: number;
    registration_event_id: number | null;
};

type ServiceType = {
    value: string;
    label: string;
};

type Props = {
    service_type: ServiceType;
    occurrences: Occurrence[];
};

const DAYS = [
    { value: '0', label: 'Sunday' },
    { value: '1', label: 'Monday' },
    { value: '2', label: 'Tuesday' },
    { value: '3', label: 'Wednesday' },
    { value: '4', label: 'Thursday' },
    { value: '5', label: 'Friday' },
    { value: '6', label: 'Saturday' },
];

function formatDate(iso: string): string {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

export default function ServicesShow({ service_type, occurrences }: Props) {
    const dateBounds = schedulingDateBounds();
    const isYouth = service_type.value === 'youth';
    const [addOpen, setAddOpen] = useState(false);
    const [recurringOpen, setRecurringOpen] = useState(false);
    const { dialog, openConfirm } = useConfirmDialog();

    const addForm = useForm({
        occurrence_date: '',
        start_time: '',
        end_time: '',
        service_leader: '',
        topic: '',
        notes: '',
    });

    const recurringForm = useForm({
        pattern: 'weekly' as 'weekly' | 'fortnightly',
        day_of_week: '0',
        first_date: '',
        weeks: 12,
        start_time: '',
        end_time: '',
        service_leader: '',
    });

    const submitAdd = (e: React.FormEvent) => {
        e.preventDefault();
        addForm.post(
            `/events/church/services/${service_type.value}/occurrences`,
            {
                onSuccess: () => {
                    setAddOpen(false);
                    addForm.reset();
                },
                preserveScroll: true,
            },
        );
    };

    const submitRecurring = (e: React.FormEvent) => {
        e.preventDefault();
        recurringForm.post(
            `/events/church/services/${service_type.value}/occurrences/recurring`,
            {
                onSuccess: () => {
                    setRecurringOpen(false);
                    recurringForm.reset();
                },
                preserveScroll: true,
            },
        );
    };

    const deleteOccurrence = (occurrence: Occurrence) => {
        openConfirm({
            variant: 'danger',
            title: 'Delete this occurrence?',
            description: `The service on ${formatDate(occurrence.occurrence_date)} and its attendance records will be permanently removed.`,
            confirmLabel: 'Delete',
            onConfirm: () =>
                router.delete(
                    `/events/church/services/occurrences/${occurrence.id}`,
                    {
                        preserveScroll: true,
                    },
                ),
        });
    };

    return (
        <>
            <Head title={`${service_type.label} — Services`} />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-6">
                <Link
                    href="/events/church/services"
                    className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-2 text-sm transition-colors"
                >
                    <ArrowLeft className="h-4 w-4" />
                    All Services
                </Link>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="pl-10">
                        <h1 className="text-foreground text-2xl font-semibold">
                            {service_type.label}
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {occurrences.length === 0
                                ? 'No attendance records yet.'
                                : `${occurrences.length} ${
                                      occurrences.length === 1
                                          ? 'occurrence'
                                          : 'occurrences'
                                  }`}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setRecurringOpen(true)}
                        >
                            <Repeat className="mr-2 h-4 w-4" />
                            Recurring Schedule
                        </Button>
                        <Button
                            onClick={() => setAddOpen(true)}
                            className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Add Record
                        </Button>
                    </div>
                </div>

                {occurrences.length === 0 ? (
                    <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-16 text-center">
                        <div className="bg-lime-brand/15 mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                            <CalendarDays className="text-lime-brand h-8 w-8" />
                        </div>
                        <h2 className="text-foreground mb-2 text-lg font-semibold">
                            No records yet
                        </h2>
                        <p className="text-muted-foreground mb-6 max-w-sm text-sm">
                            Add a service record to begin tracking attendance.
                        </p>
                        <Button
                            onClick={() => setAddOpen(true)}
                            className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Add Record
                        </Button>
                    </div>
                ) : (
                    <div className="flex flex-col gap-3">
                        {occurrences.map((occurrence) => (
                            <div
                                key={occurrence.id}
                                className="glass-panel flex flex-col gap-3 rounded-xl p-5 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <Link
                                    href={`/events/church/services/occurrences/${occurrence.id}/attendance`}
                                    className="flex flex-1 flex-col gap-2"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <CalendarDays className="text-muted-foreground h-4 w-4" />
                                        <span className="text-foreground text-sm font-semibold">
                                            {formatDate(
                                                occurrence.occurrence_date,
                                            )}
                                        </span>
                                        {occurrence.topic && (
                                            <span className="text-muted-foreground text-sm">
                                                · {occurrence.topic}
                                            </span>
                                        )}
                                    </div>
                                    <div className="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                                        {occurrence.start_time &&
                                            occurrence.end_time && (
                                                <span className="inline-flex items-center gap-1.5">
                                                    <Clock className="h-3 w-3" />
                                                    {occurrence.start_time} –{' '}
                                                    {occurrence.end_time}
                                                </span>
                                            )}
                                        {occurrence.service_leader && (
                                            <span>
                                                Led by{' '}
                                                {occurrence.service_leader}
                                            </span>
                                        )}
                                        <span className="inline-flex items-center gap-1.5">
                                            <Users className="h-3 w-3" />
                                            {occurrence.member_count +
                                                occurrence.first_timer_count}{' '}
                                            marked
                                        </span>
                                    </div>
                                </Link>

                                <div className="flex shrink-0 items-center gap-1">
                                    {occurrence.registration_event_id !==
                                    null ? (
                                        <Link
                                            href={`/events/church/events/${occurrence.registration_event_id}/registrations`}
                                            className="text-primary text-sm hover:underline"
                                        >
                                            View registrations
                                        </Link>
                                    ) : (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                if (
                                                    window.confirm(
                                                        'Activate the shared Registration → Attendance workflow? Existing attendance will be preserved; adding and editing participants moves to Registration.',
                                                    )
                                                ) {
                                                    router.post(
                                                        `/services/occurrences/${occurrence.id}/registration-bridge`,
                                                    );
                                                }
                                            }}
                                        >
                                            Set up registration and public link
                                        </Button>
                                    )}
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            deleteOccurrence(occurrence)
                                        }
                                        className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        aria-label={`Delete ${formatDate(occurrence.occurrence_date)} occurrence`}
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

            {/* Manual occurrence dialog */}
            <Dialog open={addOpen} onOpenChange={setAddOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Add Service Record</DialogTitle>
                        <DialogDescription>
                            One occurrence of {service_type.label}.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitAdd} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="occurrence_date">
                                Service Date{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <DatePicker
                                id="occurrence_date"
                                value={addForm.data.occurrence_date}
                                onChange={(v) =>
                                    addForm.setData('occurrence_date', v)
                                }
                                placeholder="Pick a date"
                                minDate={dateBounds.minDate}
                                maxDate={dateBounds.maxDate}
                            />
                            <InputError
                                message={addForm.errors.occurrence_date}
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="add_start">
                                    Start Time{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <TimePicker
                                    id="add_start"
                                    value={addForm.data.start_time}
                                    onChange={(v) =>
                                        addForm.setData('start_time', v)
                                    }
                                />
                                <InputError
                                    message={addForm.errors.start_time}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="add_end">End Time</Label>
                                <TimePicker
                                    id="add_end"
                                    value={addForm.data.end_time}
                                    onChange={(v) =>
                                        addForm.setData('end_time', v)
                                    }
                                    clearable
                                />
                                <InputError message={addForm.errors.end_time} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="add_leader">Service Leader</Label>
                            <Input
                                id="add_leader"
                                value={addForm.data.service_leader}
                                onChange={(e) =>
                                    addForm.setData(
                                        'service_leader',
                                        e.target.value,
                                    )
                                }
                                placeholder="e.g. Pastor David"
                            />
                            <InputError
                                message={addForm.errors.service_leader}
                            />
                        </div>

                        {isYouth && (
                            <div className="grid gap-2">
                                <Label htmlFor="add_topic">Topic</Label>
                                <Input
                                    id="add_topic"
                                    value={addForm.data.topic}
                                    onChange={(e) =>
                                        addForm.setData('topic', e.target.value)
                                    }
                                    placeholder="e.g. Faith in Action"
                                />
                                <InputError message={addForm.errors.topic} />
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="add_notes">Notes</Label>
                            <textarea
                                id="add_notes"
                                value={addForm.data.notes}
                                onChange={(e) =>
                                    addForm.setData('notes', e.target.value)
                                }
                                rows={3}
                                className="border-input bg-input placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-20 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                                placeholder="Optional notes about this service"
                            />
                            <InputError message={addForm.errors.notes} />
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setAddOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={addForm.processing}
                                className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                            >
                                {addForm.processing ? 'Saving…' : 'Save Record'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Recurring dialog */}
            <Dialog open={recurringOpen} onOpenChange={setRecurringOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Recurring Schedule</DialogTitle>
                        <DialogDescription>
                            Generate a batch of occurrences. Existing dates are
                            skipped.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitRecurring} className="grid gap-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="pattern">Pattern</Label>
                                <Select
                                    value={recurringForm.data.pattern}
                                    onValueChange={(v) =>
                                        recurringForm.setData(
                                            'pattern',
                                            v as 'weekly' | 'fortnightly',
                                        )
                                    }
                                >
                                    <SelectTrigger id="pattern">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="weekly">
                                            Weekly
                                        </SelectItem>
                                        <SelectItem value="fortnightly">
                                            Fortnightly
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="day">Day of Week</Label>
                                <Select
                                    value={recurringForm.data.day_of_week}
                                    onValueChange={(v) =>
                                        recurringForm.setData('day_of_week', v)
                                    }
                                >
                                    <SelectTrigger id="day">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {DAYS.map((d) => (
                                            <SelectItem
                                                key={d.value}
                                                value={d.value}
                                            >
                                                {d.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="first_date">
                                First Date{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <DatePicker
                                id="first_date"
                                value={recurringForm.data.first_date}
                                onChange={(v) =>
                                    recurringForm.setData('first_date', v)
                                }
                                placeholder="Pick the first occurrence"
                                minDate={dateBounds.minDate}
                                maxDate={dateBounds.maxDate}
                            />
                            <InputError
                                message={recurringForm.errors.first_date}
                            />
                            <p className="text-muted-foreground text-xs">
                                If this date is not the selected day, it will be
                                adjusted forward.
                            </p>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="weeks">Number of Occurrences</Label>
                            <Input
                                id="weeks"
                                type="number"
                                min={1}
                                max={52}
                                value={recurringForm.data.weeks}
                                onChange={(e) =>
                                    recurringForm.setData(
                                        'weeks',
                                        Number(e.target.value),
                                    )
                                }
                            />
                            <InputError message={recurringForm.errors.weeks} />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="rec_start">Start Time</Label>
                                <TimePicker
                                    id="rec_start"
                                    value={recurringForm.data.start_time}
                                    onChange={(v) =>
                                        recurringForm.setData('start_time', v)
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="rec_end">End Time</Label>
                                <TimePicker
                                    id="rec_end"
                                    value={recurringForm.data.end_time}
                                    onChange={(v) =>
                                        recurringForm.setData('end_time', v)
                                    }
                                    clearable
                                />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="rec_leader">Service Leader</Label>
                            <Input
                                id="rec_leader"
                                value={recurringForm.data.service_leader}
                                onChange={(e) =>
                                    recurringForm.setData(
                                        'service_leader',
                                        e.target.value,
                                    )
                                }
                                placeholder="e.g. Pastor David"
                            />
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setRecurringOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={recurringForm.processing}
                                className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                            >
                                {recurringForm.processing
                                    ? 'Generating…'
                                    : 'Generate'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

ServicesShow.layout = {
    breadcrumbs: [
        { title: 'Services', href: '/events/church/services' },
        { title: 'Service', href: '#' },
    ],
};
