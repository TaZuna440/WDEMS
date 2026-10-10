import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
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

type Participant = {
    id: number | null;
    first_name: string | null;
    last_name: string | null;
    email: string | null;
    contact_number: string | null;
    address: string | null;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    registrationId: number;
    participant: Participant;
};

/**
 * Edit a participant from the monitor's actions menu.
 *
 * Scope: first name, last name, email, contact number, address. Age
 * is not editable — the spec deliberately excludes it because age is
 * descriptive data the participant confirmed at registration.
 *
 * The email and phone UNIQUE constraints still apply server-side.
 * Editing to a value that collides with another participant returns
 * an inline error under the relevant field. Editing back to the
 * current value is allowed.
 *
 * Form data re-seeds on every open so navigating between cards in
 * the feed does not leak the previous card's values into the modal.
 */
export default function EditParticipantDialog({
    open,
    onOpenChange,
    registrationId,
    participant,
}: Props) {
    const form = useForm({
        first_name: participant.first_name ?? '',
        last_name: participant.last_name ?? '',
        email: participant.email ?? '',
        contact_number: participant.contact_number ?? '',
        address: participant.address ?? '',
    });

    // Re-seed form data whenever the dialog opens against a new
    // participant. Without this, opening the dialog on card B after
    // editing card A shows card A's values.
    useEffect(() => {
        if (! open) {
            return;
        }

        form.setData({
            first_name: participant.first_name ?? '',
            last_name: participant.last_name ?? '',
            email: participant.email ?? '',
            contact_number: participant.contact_number ?? '',
            address: participant.address ?? '',
        });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, participant.id]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        form.put(`/registrations/${registrationId}/participant`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit participant</DialogTitle>
                    <DialogDescription>
                        Update this participant's contact and descriptive
                        details. Age is not editable.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="edit-participant-first-name">
                                First name{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="edit-participant-first-name"
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
                            <Label htmlFor="edit-participant-last-name">
                                Last name{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="edit-participant-last-name"
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
                        <Label htmlFor="edit-participant-email">
                            Email
                        </Label>
                        <Input
                            id="edit-participant-email"
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
                        <Label htmlFor="edit-participant-contact">
                            Contact number
                        </Label>
                        <Input
                            id="edit-participant-contact"
                            type="tel"
                            value={form.data.contact_number}
                            onChange={(e) =>
                                form.setData(
                                    'contact_number',
                                    e.target.value,
                                )
                            }
                            autoComplete="off"
                        />
                        <InputError message={form.errors.contact_number} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="edit-participant-address">
                            Address
                        </Label>
                        <Input
                            id="edit-participant-address"
                            value={form.data.address}
                            onChange={(e) =>
                                form.setData('address', e.target.value)
                            }
                            autoComplete="off"
                        />
                        <InputError message={form.errors.address} />
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
                            {form.processing ? 'Saving…' : 'Save changes'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
