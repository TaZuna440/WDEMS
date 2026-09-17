import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type EventData = {
    id: number;
    event_name: string;
    description: string | null;
    event_date: string | null;
    start_time: string | null;
    end_time: string | null;
    venue: string | null;
    status: string;
    status_label: string;
};

type Props = {
    event: EventData;
};

export default function EventsEdit({ event }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        event_name: event.event_name ?? '',
        description: event.description ?? '',
        event_date: event.event_date ?? '',
        start_time: event.start_time ?? '',
        end_time: event.end_time ?? '',
        venue: event.venue ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
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

                <form
                    onSubmit={submit}
                    className="glass-panel flex flex-col gap-6 rounded-xl p-8"
                >
                    {/* Event Name */}
                    <div className="grid gap-2">
                        <Label htmlFor="event_name">
                            Event Name{' '}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="event_name"
                            value={data.event_name}
                            onChange={(e) =>
                                setData('event_name', e.target.value)
                            }
                            required
                            autoFocus
                        />
                        <InputError message={errors.event_name} />
                    </div>

                    {/* Description */}
                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <textarea
                            id="description"
                            value={data.description}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                            rows={3}
                            className="border-input bg-input placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-20 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                        />
                        <InputError message={errors.description} />
                    </div>

                    {/* Date + Times */}
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="event_date">
                                Event Date{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="event_date"
                                type="date"
                                value={data.event_date}
                                onChange={(e) =>
                                    setData('event_date', e.target.value)
                                }
                                required
                            />
                            <InputError message={errors.event_date} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="start_time">Start Time</Label>
                            <Input
                                id="start_time"
                                type="time"
                                value={data.start_time}
                                onChange={(e) =>
                                    setData('start_time', e.target.value)
                                }
                            />
                            <InputError message={errors.start_time} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="end_time">End Time</Label>
                            <Input
                                id="end_time"
                                type="time"
                                value={data.end_time}
                                onChange={(e) =>
                                    setData('end_time', e.target.value)
                                }
                            />
                            <InputError message={errors.end_time} />
                        </div>
                    </div>

                    {/* Venue */}
                    <div className="grid gap-2">
                        <Label htmlFor="venue">Venue</Label>
                        <Input
                            id="venue"
                            value={data.venue}
                            onChange={(e) =>
                                setData('venue', e.target.value)
                            }
                        />
                        <InputError message={errors.venue} />
                    </div>

                    {/* Actions */}
                    <div className="flex items-center justify-end gap-3 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            asChild
                            disabled={processing}
                        >
                            <Link href={`/events/${event.id}`}>Cancel</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                        >
                            <Save className="mr-2 h-4 w-4" />
                            {processing ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

EventsEdit.layout = {
    breadcrumbs: [
        { title: 'Events', href: '/events' },
        { title: 'Edit', href: '#' },
    ],
};