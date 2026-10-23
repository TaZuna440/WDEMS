import { useForm } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
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
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    registrationId: number;
    participantName: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function RegistrationDeletionPasswordDialog({
    registrationId,
    participantName,
    open,
    onOpenChange,
}: Props) {
    const form = useForm({ password: '' });

    const close = () => {
        form.resetAndClearErrors();
        onOpenChange(false);
    };

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.delete(`/registrations/${registrationId}`, {
            preserveScroll: true,
            onSuccess: close,
        });
    };

    return (
        <Dialog open={open} onOpenChange={(nextOpen) => !nextOpen && close()}>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <LockKeyhole className="h-4 w-4" />
                            Confirm Registration Deletion
                        </DialogTitle>
                        <DialogDescription>
                            Enter your password to permanently delete the
                            registration for <strong>{participantName}</strong>.
                            This cannot be undone.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="mt-4 flex flex-col gap-2">
                        <Label htmlFor="registration-deletion-password">
                            Password
                        </Label>
                        <Input
                            id="registration-deletion-password"
                            type="password"
                            autoComplete="current-password"
                            required
                            autoFocus
                            value={form.data.password}
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                            disabled={form.processing}
                        />
                        <InputError message={form.errors.password} />
                    </div>

                    <DialogFooter className="mt-4 gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={close}
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={form.processing || !form.data.password}
                        >
                            {form.processing ? (
                                <>
                                    <Spinner className="mr-2" />
                                    Deleting…
                                </>
                            ) : (
                                'Confirm Delete'
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
