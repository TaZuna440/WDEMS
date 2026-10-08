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

type Props = {
    eventId: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    requirements: CommonFieldRequirements;
};

export default function WalkInDialog({
    eventId,
    open,
    onOpenChange,
    requirements,
}: Props) {
    const form = useForm({
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

    const requiredMark = (
        <span className="text-destructive"> *</span>
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg">
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
