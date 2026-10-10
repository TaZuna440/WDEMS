import { useEffect, useState } from 'react';
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
import { Label } from '@/components/ui/label';

const MAX_LENGTH = 2000;

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    registrationId: number;
    participantName: string;
    initialNote: string | null;
};

/**
 * Add or edit a free-form note on a registration.
 *
 * The note is capped at 2000 characters server-side. A live counter
 * shows the remaining budget; the counter turns destructive-colored
 * in the last 100 characters. An empty submission clears the note —
 * the server stores null.
 *
 * Note text re-seeds on every open so navigating between cards does
 * not leak the previous card's content.
 */
export default function NoteDialog({
    open,
    onOpenChange,
    registrationId,
    participantName,
    initialNote,
}: Props) {
    const form = useForm({
        note: initialNote ?? '',
    });

    const [remaining, setRemaining] = useState(
        MAX_LENGTH - (initialNote?.length ?? 0),
    );

    useEffect(() => {
        if (! open) {
            return;
        }

        const seed = initialNote ?? '';
        form.setData('note', seed);
        form.clearErrors();
        setRemaining(MAX_LENGTH - seed.length);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, registrationId]);

    const handleChange = (value: string) => {
        form.setData('note', value);
        setRemaining(MAX_LENGTH - value.length);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        form.post(`/registrations/${registrationId}/note`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    const isNearLimit = remaining <= 100;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Add note</DialogTitle>
                    <DialogDescription>
                        A private note attached to {participantName}'s
                        registration. Visible only to staff.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="registration-note">Note</Label>
                        <textarea
                            id="registration-note"
                            value={form.data.note}
                            onChange={(e) => handleChange(e.target.value)}
                            rows={6}
                            autoFocus
                            maxLength={MAX_LENGTH}
                            placeholder="Anything the organizer should know about this registration."
                            className="flex min-h-[120px] w-full rounded-md border border-white/10 bg-white/[0.02] px-3 py-2 text-sm text-foreground focus:border-lime-brand/50 focus:outline-none focus:ring-1 focus:ring-lime-brand/50"
                        />
                        <div className="flex items-center justify-between">
                            <InputError message={form.errors.note} />
                            <p
                                className={`ml-auto text-xs ${
                                    isNearLimit
                                        ? 'text-destructive'
                                        : 'text-muted-foreground'
                                }`}
                            >
                                {remaining} characters remaining
                            </p>
                        </div>
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
                            {form.processing ? 'Saving…' : 'Save note'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
