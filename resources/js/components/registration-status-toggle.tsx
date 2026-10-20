import { useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function RegistrationStatusToggle({ eventId, isOpen }: { eventId: number; isOpen: boolean }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ password: '' });
    const label = isOpen ? 'Close Registration' : 'Reopen Registration';
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/events/${eventId}/${isOpen ? 'close' : 'reopen'}-registration`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onFinish: () => form.reset('password'),
        });
    };
    return <Dialog open={open} onOpenChange={(value) => {
        if (form.processing) return;
        setOpen(value); form.reset(); form.clearErrors();
    }}>
        <DialogTrigger asChild><Button variant="outline">{label}</Button></DialogTrigger>
        <DialogContent>
            <DialogHeader><DialogTitle>{label}?</DialogTitle><DialogDescription>
                {isOpen ? 'New registrations will stop. People already verifying can finish within their original 24-hour session. You can reopen registration later.' : 'New participants will be able to register again using the same public link.'}
            </DialogDescription></DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <Label htmlFor={`registration-password-${eventId}`}>Your account password</Label>
                <Input id={`registration-password-${eventId}`} type="password" autoComplete="current-password" autoFocus required
                    value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} disabled={form.processing} />
                <InputError message={form.errors.password} />
                <Button type="submit" disabled={form.processing || !form.data.password}>{form.processing ? 'Saving…' : label}</Button>
            </form>
        </DialogContent>
    </Dialog>;
}
