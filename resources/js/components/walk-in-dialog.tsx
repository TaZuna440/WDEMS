import { UserPlus, X } from 'lucide-react';
import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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

type CommonFieldRequirements = {
    email: boolean;
    contact_number: boolean;
    address: boolean;
};

type ServerField = {
    id: number;
    label: string;
    field_type: string;
    options: string[];
    is_required: boolean;
};

type Props = {
    eventId: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    requirements: CommonFieldRequirements;
    fields: ServerField[];
};

export default function WalkInDialog({
    eventId,
    open,
    onOpenChange,
    requirements,
    fields,
}: Props) {
    const form = useForm<{
        first_name: string;
        last_name: string;
        age: string | number;
        email: string;
        contact_number: string;
        address: string;
        mark_present: boolean;
        identity: string;
        responses: Record<number, string | string[]>;
    }>({
        first_name: '',
        last_name: '',
        age: '' as string | number,
        email: '',
        contact_number: '',
        address: '',
        mark_present: true,
        // Placeholder so TypeScript knows `identity` is a valid error
        // key. The server validates the email-or-phone rule and stores
        // the failure under this key. No validation rule reads it —
        // the server ignores the value.
        identity: '',
        responses: {},
    });

    useEffect(() => {
        if (! open) {
            form.reset();
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        form.post(`/events/${eventId}/attendance/walk-in`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    };

    const setResponse = (fieldId: number, value: string | string[]) => {
        form.setData('responses', {
            ...form.data.responses,
            [fieldId]: value,
        });
    };

    const toggleCheckboxOption = (fieldId: number, option: string) => {
        const current = form.data.responses[fieldId];
        const list = Array.isArray(current) ? current : [];
        const next = list.includes(option)
            ? list.filter((v) => v !== option)
            : [...list, option];
        setResponse(fieldId, next);
    };

    const requiredMark = (
        <span className="text-destructive"> *</span>
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] max-w-lg overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <UserPlus className="h-4 w-4" />
                        Add Walk-In Participant
                    </DialogTitle>
                    <DialogDescription>
                        Register someone who arrived on the day without
                        pre-registering. They will be added to this event's
                        participant list.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="walk-in-first-name">
                                First name{requiredMark}
                            </Label>
                            <Input
                                id="walk-in-first-name"
                                value={form.data.first_name}
                                onChange={(e) =>
                                    form.setData('first_name', e.target.value)
                                }
                                autoFocus
                                autoComplete="off"
                            />
                            <InputError message={form.errors.first_name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="walk-in-last-name">
                                Last name{requiredMark}
                            </Label>
                            <Input
                                id="walk-in-last-name"
                                value={form.data.last_name}
                                onChange={(e) =>
                                    form.setData('last_name', e.target.value)
                                }
                                autoComplete="off"
                            />
                            <InputError message={form.errors.last_name} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="walk-in-age">
                            Age{requiredMark}
                        </Label>
                        <Input
                            id="walk-in-age"
                            type="number"
                            inputMode="numeric"
                            min={1}
                            max={120}
                            value={form.data.age}
                            onChange={(e) =>
                                form.setData('age', e.target.value)
                            }
                            autoComplete="off"
                        />
                        <InputError message={form.errors.age} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="walk-in-email">
                            Email
                            {requirements.email && requiredMark}
                        </Label>
                        <Input
                            id="walk-in-email"
                            type="email"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            autoComplete="off"
                        />
                        <InputError message={form.errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="walk-in-contact">
                            Contact number
                            {requirements.contact_number && requiredMark}
                        </Label>
                        <Input
                            id="walk-in-contact"
                            type="tel"
                            value={form.data.contact_number}
                            onChange={(e) =>
                                form.setData('contact_number', e.target.value)
                            }
                            autoComplete="off"
                        />
                        <InputError message={form.errors.contact_number} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="walk-in-address">
                            Address
                            {requirements.address && requiredMark}
                        </Label>
                        <Input
                            id="walk-in-address"
                            value={form.data.address}
                            onChange={(e) =>
                                form.setData('address', e.target.value)
                            }
                            autoComplete="off"
                        />
                        <InputError message={form.errors.address} />
                    </div>

                    {fields.length > 0 && (
                        <div className="flex flex-col gap-4 border-t border-white/5 pt-4">
                            <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                Additional Information
                            </p>

                            {fields.map((field) => {
                                const key = `responses.${field.id}`;
                                const error = (
                                    form.errors as Record<string, string>
                                )[key];
                                const value =
                                    form.data.responses[field.id];

                                return (
                                    <div
                                        key={field.id}
                                        className="grid gap-2"
                                    >
                                        <Label
                                            htmlFor={`walk-in-field-${field.id}`}
                                        >
                                            {field.label}
                                            {field.is_required &&
                                                requiredMark}
                                        </Label>

                                        {field.field_type === 'textarea' && (
                                            <textarea
                                                id={`walk-in-field-${field.id}`}
                                                value={
                                                    typeof value === 'string'
                                                        ? value
                                                        : ''
                                                }
                                                onChange={(e) =>
                                                    setResponse(
                                                        field.id,
                                                        e.target.value,
                                                    )
                                                }
                                                rows={4}
                                                aria-invalid={!!error}
                                                className="flex min-h-[80px] w-full rounded-md border border-white/10 bg-white/[0.02] px-3 py-2 text-sm text-foreground focus:border-lime-brand/50 focus:outline-none focus:ring-1 focus:ring-lime-brand/50"
                                            />
                                        )}

                                        {field.field_type === 'select' && (
                                            <select
                                                id={`walk-in-field-${field.id}`}
                                                value={
                                                    typeof value === 'string'
                                                        ? value
                                                        : ''
                                                }
                                                onChange={(e) =>
                                                    setResponse(
                                                        field.id,
                                                        e.target.value,
                                                    )
                                                }
                                                aria-invalid={!!error}
                                                className="h-9 w-full rounded-md border border-white/10 bg-white/[0.02] px-3 text-sm text-foreground focus:border-lime-brand/50 focus:outline-none"
                                            >
                                                <option value="">
                                                    Choose…
                                                </option>
                                                {field.options.map(
                                                    (option) => (
                                                        <option
                                                            key={option}
                                                            value={option}
                                                        >
                                                            {option}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        )}

                                        {field.field_type === 'radio' && (
                                            <div className="flex flex-col gap-2 pt-1">
                                                {field.options.map(
                                                    (option) => (
                                                        <label
                                                            key={option}
                                                            className="flex cursor-pointer items-center gap-2 text-sm text-foreground"
                                                        >
                                                            <input
                                                                type="radio"
                                                                name={`walk-in-field-${field.id}`}
                                                                value={option}
                                                                checked={
                                                                    value ===
                                                                    option
                                                                }
                                                                onChange={() =>
                                                                    setResponse(
                                                                        field.id,
                                                                        option,
                                                                    )
                                                                }
                                                                className="h-4 w-4"
                                                            />
                                                            {option}
                                                        </label>
                                                    ),
                                                )}
                                            </div>
                                        )}

                                        {field.field_type === 'checkbox' && (
                                            <div className="flex flex-col gap-2 pt-1">
                                                {field.options.map(
                                                    (option) => {
                                                        const list =
                                                            Array.isArray(
                                                                value,
                                                            )
                                                                ? value
                                                                : [];
                                                        return (
                                                            <label
                                                                key={option}
                                                                className="flex cursor-pointer items-center gap-2 text-sm text-foreground"
                                                            >
                                                                <input
                                                                    type="checkbox"
                                                                    value={
                                                                        option
                                                                    }
                                                                    checked={list.includes(
                                                                        option,
                                                                    )}
                                                                    onChange={() =>
                                                                        toggleCheckboxOption(
                                                                            field.id,
                                                                            option,
                                                                        )
                                                                    }
                                                                    className="h-4 w-4"
                                                                />
                                                                {option}
                                                            </label>
                                                        );
                                                    },
                                                )}
                                            </div>
                                        )}

                                        {(field.field_type === 'text' ||
                                            field.field_type === 'number' ||
                                            field.field_type === 'email' ||
                                            field.field_type === 'date') && (
                                            <Input
                                                id={`walk-in-field-${field.id}`}
                                                type={
                                                    field.field_type === 'date'
                                                        ? 'date'
                                                        : field.field_type ===
                                                            'email'
                                                          ? 'email'
                                                          : field.field_type ===
                                                              'number'
                                                            ? 'number'
                                                            : 'text'
                                                }
                                                value={
                                                    typeof value === 'string'
                                                        ? value
                                                        : ''
                                                }
                                                onChange={(e) =>
                                                    setResponse(
                                                        field.id,
                                                        e.target.value,
                                                    )
                                                }
                                                autoComplete="off"
                                                aria-invalid={!!error}
                                            />
                                        )}

                                        <InputError message={error} />
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    {form.errors.identity && (
                        <div className="flex items-start gap-2 rounded-md border border-destructive/40 bg-destructive/10 p-3">
                            <X className="mt-0.5 h-4 w-4 shrink-0 text-destructive" />
                            <p className="text-sm text-destructive">
                                {form.errors.identity}
                            </p>
                        </div>
                    )}

                    <div className="flex items-center gap-3 rounded-md border border-white/5 bg-white/[0.02] p-3">
                        <Checkbox
                            id="walk-in-mark-present"
                            checked={form.data.mark_present}
                            onCheckedChange={(checked) =>
                                form.setData('mark_present', checked === true)
                            }
                        />
                        <Label
                            htmlFor="walk-in-mark-present"
                            className="cursor-pointer text-sm"
                        >
                            Mark as present
                        </Label>
                    </div>

                    <DialogFooter className="gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                        >
                            {form.processing
                                ? 'Adding...'
                                : 'Add Walk-In'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
