import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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

type Props = {
    event_types: EventTypeOption[];
};

export default function EventsCreate({ event_types }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        event_type: '',
        event_name: '',
        description: '',
        event_date: '',
        start_time: '',
        end_time: '',
        venue: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/events');
    };

    return (
        <>
            <Head title="Create Event" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6">
                {/* Back link */}
                <Link
                    href="/events"
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Events
                </Link>

                {/* Header */}
                <div>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Create Event
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Set up a new running event. Choose the event type
                        first — it determines the workflow WDEMS will follow.
                    </p>
                </div>

                {/* Form */}
                <form
                    onSubmit={submit}
                    className="glass-panel flex flex-col gap-6 rounded-xl p-8"
                >
                    {/* Event Type */}
                    <div className="grid gap-2">
                        <Label htmlFor="event_type">
                            Event Type{' '}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Select
                            value={data.event_type}
                            onValueChange={(value) =>
                                setData('event_type', value)
                            }
                        >
                            <SelectTrigger id="event_type">
                                <SelectValue placeholder="Select event type" />
                            </SelectTrigger>
                            <SelectContent>
                                {event_types.map((type) => (
                                    <SelectItem
                                        key={type.value}
                                        value={type.value}
                                    >
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
                            placeholder="e.g. Young Professionals Community Run #1"
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
                            placeholder="Brief description of the event..."
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
                            placeholder="e.g. Sports Arena"
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
                            <Link href="/events">Cancel</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                        >
                            <Save className="mr-2 h-4 w-4" />
                            {processing ? 'Creating...' : 'Create Event'}
                        </Button>
                    </div>
                </form>
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