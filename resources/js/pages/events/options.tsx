import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CircleCheck,
    Lock,
    Plus,
    Settings,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useConfirmDialog } from '@/hooks/use-confirm-dialog';

type EventData = {
    id: number;
    event_name: string;
    status: string;
    status_label: string;
    can_configure: boolean;
    can_mark_configured: boolean;
};

type OptionData = {
    id: number;
    option_type: string;
    option_name: string;
    option_value: string | null;
    is_required: boolean;
    is_available: boolean;
};

type Props = {
    event: EventData;
    options: OptionData[];
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

export default function EventOptions({ event, options }: Props) {
    const { dialog, openConfirm } = useConfirmDialog();
    const [showAddForm, setShowAddForm] = useState(options.length === 0);

    const addForm = useForm({
        option_type: '',
        option_name: '',
        option_value: '',
        is_required: false,
        is_available: true,
    });

    const submitAdd = (e: React.FormEvent) => {
        e.preventDefault();
        addForm.post(`/events/${event.id}/options`, {
            preserveScroll: true,
            onSuccess: () => {
                addForm.reset();
                setShowAddForm(false);
            },
        });
    };

    const updateOption = (
        option: OptionData,
        overrides: Partial<OptionData>,
    ) => {
        const payload = {
            option_type: option.option_type,
            option_name: option.option_name,
            option_value: option.option_value,
            is_required: option.is_required,
            is_available: option.is_available,
            ...overrides,
        };

        router.put(`/events/${event.id}/options/${option.id}`, payload, {
            preserveScroll: true,
        });
    };

    const deleteOption = (option: OptionData) => {
        openConfirm({
            variant: 'danger',
            title: 'Delete this option?',
            description: `"${option.option_name}" will be permanently removed. This cannot be undone.`,
            confirmLabel: 'Delete Option',
            onConfirm: () =>
                router.delete(`/events/${event.id}/options/${option.id}`, {
                    preserveScroll: true,
                }),
        });
    };

    const markConfigured = () => {
        openConfirm({
            variant: 'primary',
            title: 'Mark as configured?',
            description:
                'This enables registration actions for this event.',
            confirmLabel: 'Mark as Configured',
            onConfirm: () => router.post(`/events/${event.id}/configure`),
        });
    };

    return (
        <>
            <Head title={`Configure ${event.event_name}`} />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-6">
                <Link
                    href={`/events/${event.id}`}
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Event
                </Link>

                {/* Header */}
                <div>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Configure Event Options
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
                        <span className="text-sm text-muted-foreground">
                            {event.event_name}
                        </span>
                    </div>
                    <p className="mt-3 text-sm text-muted-foreground">
                        Define the choices participants will make during
                        registration — like distance, shirt size, or add-ons.
                    </p>
                </div>

                {/* Read-only banner */}
                {!event.can_configure && (
                    <div className="glass-panel flex items-start gap-3 rounded-xl p-4">
                        <Lock className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Options are locked because registration has already
                            opened. They can no longer be added, edited, or
                            removed.
                        </p>
                    </div>
                )}

                {/* Add form */}
                {event.can_configure &&
                    (!showAddForm ? (
                        <Button
                            onClick={() => setShowAddForm(true)}
                            variant="outline"
                            className="w-fit"
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Add Option
                        </Button>
                    ) : (
                        <form
                            onSubmit={submitAdd}
                            className="glass-panel flex flex-col gap-4 rounded-xl p-6"
                        >
                            <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                                Add New Option
                            </h2>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="option_type">
                                        Type{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        id="option_type"
                                        value={addForm.data.option_type}
                                        onChange={(e) =>
                                            addForm.setData(
                                                'option_type',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="e.g. distance, shirt, add-on"
                                    />
                                    <InputError
                                        message={addForm.errors.option_type}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="option_name">
                                        Name{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        id="option_name"
                                        value={addForm.data.option_name}
                                        onChange={(e) =>
                                            addForm.setData(
                                                'option_name',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="e.g. 5 KM, Large, Red"
                                    />
                                    <InputError
                                        message={addForm.errors.option_name}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="option_value">
                                    Value (optional)
                                </Label>
                                <Input
                                    id="option_value"
                                    value={addForm.data.option_value}
                                    onChange={(e) =>
                                        addForm.setData(
                                            'option_value',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Optional metadata"
                                />
                            </div>

                            <div className="flex flex-wrap items-center gap-6">
                                <div className="flex items-center space-x-3">
                                    <Checkbox
                                        id="is_required"
                                        checked={addForm.data.is_required}
                                        onCheckedChange={(v) =>
                                            addForm.setData(
                                                'is_required',
                                                v === true,
                                            )
                                        }
                                    />
                                    <Label htmlFor="is_required">
                                        Required
                                    </Label>
                                </div>

                                <div className="flex items-center space-x-3">
                                    <Checkbox
                                        id="is_available"
                                        checked={addForm.data.is_available}
                                        onCheckedChange={(v) =>
                                            addForm.setData(
                                                'is_available',
                                                v === true,
                                            )
                                        }
                                    />
                                    <Label htmlFor="is_available">
                                        Available
                                    </Label>
                                </div>
                            </div>

                            <div className="flex items-center justify-end gap-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        addForm.reset();
                                        setShowAddForm(options.length > 0);
                                    }}
                                    disabled={addForm.processing}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={addForm.processing}
                                    className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    {addForm.processing
                                        ? 'Adding...'
                                        : 'Add Option'}
                                </Button>
                            </div>
                        </form>
                    ))}

                {/* Options list */}
                {options.length === 0 ? (
                    <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-16 text-center">
                        <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-lime-brand/15">
                            <Settings className="h-8 w-8 text-lime-brand" />
                        </div>
                        <h2 className="mb-2 text-lg font-semibold text-foreground">
                            No options yet
                        </h2>
                        <p className="max-w-sm text-sm text-muted-foreground">
                            Add your first event option above — like a 5 KM
                            distance or a shirt size.
                        </p>
                    </div>
                ) : (
                    <div className="glass-panel overflow-hidden rounded-xl">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b border-white/10 text-left">
                                    <th className="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        Type
                                    </th>
                                    <th className="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        Name
                                    </th>
                                    <th className="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        Required
                                    </th>
                                    <th className="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        Available
                                    </th>
                                    {event.can_configure && (
                                        <th className="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                            Actions
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {options.map((option) => (
                                    <tr
                                        key={option.id}
                                        className="border-b border-white/5 transition-colors last:border-b-0 hover:bg-white/5"
                                    >
                                        <td className="px-6 py-4 text-sm text-muted-foreground">
                                            {option.option_type}
                                        </td>
                                        <td className="px-6 py-4 text-sm font-medium text-foreground">
                                            {option.option_name}
                                            {option.option_value && (
                                                <span className="ml-2 text-xs text-muted-foreground">
                                                    ({option.option_value})
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4">
                                            <Checkbox
                                                checked={option.is_required}
                                                disabled={!event.can_configure}
                                                onCheckedChange={() =>
                                                    updateOption(option, {
                                                        is_required:
                                                            !option.is_required,
                                                    })
                                                }
                                            />
                                        </td>
                                        <td className="px-6 py-4">
                                            <Checkbox
                                                checked={option.is_available}
                                                disabled={!event.can_configure}
                                                onCheckedChange={() =>
                                                    updateOption(option, {
                                                        is_available:
                                                            !option.is_available,
                                                    })
                                                }
                                            />
                                        </td>
                                        {event.can_configure && (
                                            <td className="px-6 py-4">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        deleteOption(option)
                                                    }
                                                    className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Mark Configured */}
                {event.can_mark_configured && (
                    <div className="glass-panel flex flex-col gap-4 rounded-xl p-6 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="text-sm font-semibold text-foreground">
                                Ready to open registration?
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Mark this event as configured to enable
                                registration actions.
                            </p>
                        </div>
                        <Button
                            onClick={markConfigured}
                            className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                        >
                            <CircleCheck className="mr-2 h-4 w-4" />
                            Mark as Configured
                        </Button>
                    </div>
                )}
            </div>

            {dialog}
        </>
    );
}

EventOptions.layout = {
    breadcrumbs: [
        { title: 'Events', href: '/events' },
        { title: 'Configure', href: '#' },
    ],
};