import { Clock, UserPlus, X } from 'lucide-react';
import { useEffect, useMemo } from 'react';
import { useForm } from '@inertiajs/react';
import DatePicker from '@/components/date-picker';
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
import { birthdayPickerBounds } from '@/lib/public-registration-validation';

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

type EncodingMode = 'live' | 'deferred';

type Props = {
    eventId: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    requirements: CommonFieldRequirements;
    fields: ServerField[];
    // Admin force-open bypass. When true, the POST carries
    // force_open=1 so the controller's gate check is skipped.
    // Only ever set to true for admins — the attendance page
    // computes the value from the user's role.
    forceOpen?: boolean;
};

export default function WalkInDialog({
    eventId,
    open,
    onOpenChange,
    requirements,
    fields,
    forceOpen = false,
}: Props) {
    const form = useForm<{
        first_name: string;
        last_name: string;
        middle_name: string;
        suffix: string;
        age: string | number;
        birthday: string;
        email: string;
        contact_number: string;
        address: string;
        mark_present: boolean;
        identity: string;
        responses: Record<number, string | string[]>;
        encoding_mode: EncodingMode;
        arrival_time: string;
    }>({
        first_name: '',
        last_name: '',
        middle_name: '',
        suffix: '',
        age: '' as string | number,
        birthday: '',
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
        // Live is the default: the staff member is entering the
        // participant at the table, right now. attendance_time on the
        // server will be now().
        encoding_mode: 'live',
        // Only used when encoding_mode === 'deferred'. Server ignores
        // it when the mode is live. Required in deferred mode — see
        // WalkInRegistrationRequest::withValidator() Pass 3.
        arrival_time: '',
    });

    useEffect(() => {
        if (!open) {
            form.reset();
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        form.post(
            `/events/${eventId}/attendance/walk-in${
                forceOpen ? '?force_open=1' : ''
            }`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    onOpenChange(false);
                },
            },
        );
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

    // Birthday picker bounds, computed once per dialog mount. Same
    // bounds the client validator enforces (age 3-120). Not
    // recomputed per render — the dialog submits in one sitting.
    const birthdayBounds = useMemo(() => birthdayPickerBounds(), []);
    const requiredMark = <span className="text-destructive"> *</span>;

    const isDeferred = form.data.encoding_mode === 'deferred';

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
                    {/* Encoding mode — commits the whole submission to
                        either a live timestamp (now()) or a deferred
                        arrival time read off a paper form. Live is the
                        default; the mode must be chosen before the
                        details are entered. */}
                    <div className="grid gap-3 rounded-md border border-white/5 bg-white/[0.02] p-3">
                        <Label>Encoding mode</Label>
                        <div className="inline-flex w-fit rounded-lg border border-white/10 bg-white/[0.02] p-0.5">
                            <button
                                type="button"
                                onClick={() =>
                                    form.setData('encoding_mode', 'live')
                                }
                                className={
                                    form.data.encoding_mode === 'live'
                                        ? 'text-foreground rounded-md bg-white/10 px-3 py-1.5 text-xs font-medium'
                                        : 'text-muted-foreground hover:text-foreground rounded-md px-3 py-1.5 text-xs font-medium'
                                }
                            >
                                Live — encoding as they arrive
                            </button>
                            <button
                                type="button"
                                onClick={() =>
                                    form.setData('encoding_mode', 'deferred')
                                }
                                className={
                                    form.data.encoding_mode === 'deferred'
                                        ? 'text-foreground rounded-md bg-white/10 px-3 py-1.5 text-xs font-medium'
                                        : 'text-muted-foreground hover:text-foreground rounded-md px-3 py-1.5 text-xs font-medium'
                                }
                            >
                                Deferred — transcribing from paper
                            </button>
                        </div>
                        <p className="text-muted-foreground text-xs">
                            {isDeferred
                                ? 'Arrival time will be read from the paper form. Use this when entering a walk-in after the fact.'
                                : 'Arrival time will be recorded as right now. Use this when the participant is standing in front of you.'}
                        </p>
                    </div>

                    {/* Arrival time — only meaningful when the mode is
                        deferred. In live mode the server stamps now()
                        and ignores any submitted value. */}
                    {isDeferred && (
                        <div className="grid gap-2">
                            <Label htmlFor="walk-in-arrival-time">
                                <Clock className="mr-1 inline h-3.5 w-3.5" />
                                Arrival time{requiredMark}
                            </Label>
                            <Input
                                id="walk-in-arrival-time"
                                type="time"
                                value={form.data.arrival_time}
                                onChange={(e) =>
                                    form.setData('arrival_time', e.target.value)
                                }
                                aria-invalid={!!form.errors.arrival_time}
                            />
                            <p className="text-muted-foreground text-xs">
                                The time on the paper form, not the current
                                time.
                            </p>
                            <InputError message={form.errors.arrival_time} />
                        </div>
                    )}

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

                    <div className="grid gap-4 sm:grid-cols-2">
                        {(['middle_name', 'suffix'] as const).map((key) => (
                            <div key={key} className="grid gap-2">
                                <Label htmlFor={`walk-in-${key}`}>
                                    {key === 'middle_name'
                                        ? 'Middle name'
                                        : 'Suffix'}{' '}
                                    (optional)
                                </Label>
                                <Input
                                    id={`walk-in-${key}`}
                                    value={form.data[key]}
                                    maxLength={key === 'suffix' ? 10 : 50}
                                    onChange={(e) =>
                                        form.setData(key, e.target.value)
                                    }
                                />
                                <InputError message={form.errors[key]} />
                            </div>
                        ))}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="walk-in-birthday">
                            Date of birth{requiredMark}
                        </Label>
                        <DatePicker
                            id="walk-in-birthday"
                            value={form.data.birthday ?? ''}
                            onChange={(value) =>
                                form.setData('birthday', value)
                            }
                            placeholder="MM/DD/YYYY"
                            minDate={birthdayBounds.minDate}
                            maxDate={birthdayBounds.maxDate}
                            autoFocusTyped
                        />
                        <InputError message={form.errors.birthday} />
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
                            <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">
                                Additional Information
                            </p>

                            {fields.map((field) => {
                                const key = `responses.${field.id}`;
                                const error = (
                                    form.errors as Record<string, string>
                                )[key];
                                const value = form.data.responses[field.id];

                                return (
                                    <div key={field.id} className="grid gap-2">
                                        <Label
                                            htmlFor={`walk-in-field-${field.id}`}
                                        >
                                            {field.label}
                                            {field.is_required && requiredMark}
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
                                                className="text-foreground focus:border-lime-brand/50 focus:ring-lime-brand/50 flex min-h-[80px] w-full rounded-md border border-white/10 bg-white/[0.02] px-3 py-2 text-sm focus:ring-1 focus:outline-none"
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
                                                className="text-foreground focus:border-lime-brand/50 h-9 w-full rounded-md border border-white/10 bg-white/[0.02] px-3 text-sm focus:outline-none"
                                            >
                                                <option value="">
                                                    Choose…
                                                </option>
                                                {field.options.map((option) => (
                                                    <option
                                                        key={option}
                                                        value={option}
                                                    >
                                                        {option}
                                                    </option>
                                                ))}
                                            </select>
                                        )}

                                        {field.field_type === 'radio' && (
                                            <div className="flex flex-col gap-2 pt-1">
                                                {field.options.map((option) => (
                                                    <label
                                                        key={option}
                                                        className="text-foreground flex cursor-pointer items-center gap-2 text-sm"
                                                    >
                                                        <input
                                                            type="radio"
                                                            name={`walk-in-field-${field.id}`}
                                                            value={option}
                                                            checked={
                                                                value === option
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
                                                ))}
                                            </div>
                                        )}

                                        {field.field_type === 'checkbox' && (
                                            <div className="flex flex-col gap-2 pt-1">
                                                {field.options.map((option) => {
                                                    const list = Array.isArray(
                                                        value,
                                                    )
                                                        ? value
                                                        : [];
                                                    return (
                                                        <label
                                                            key={option}
                                                            className="text-foreground flex cursor-pointer items-center gap-2 text-sm"
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                value={option}
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
                                                })}
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
                        <div className="border-destructive/40 bg-destructive/10 flex items-start gap-2 rounded-md border p-3">
                            <X className="text-destructive mt-0.5 h-4 w-4 shrink-0" />
                            <p className="text-destructive text-sm">
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
                            {form.processing ? 'Adding...' : 'Add Walk-In'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
