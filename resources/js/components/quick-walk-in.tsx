import EncoderNameSearch from '@/components/encoder-name-search';
import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function QuickWalkIn({ eventId, forceOpen }: { eventId: number; forceOpen: boolean }) {
    const form = useForm({ first_name: '', middle_name: '', last_name: '', suffix: '', force_open: forceOpen });
    return <form className="space-y-4" onSubmit={(e) => {
        e.preventDefault();
        form.post(`/events/${eventId}/attendance/quick-walk-in`, {
            preserveScroll: true,
            onSuccess: () => form.reset('first_name', 'middle_name', 'last_name', 'suffix'),
        });
    }}>
        <p className="text-sm text-muted-foreground">Name-only entry marks this attendee present now. The record stays provisional for this event until staff review it.</p>
        <div className="grid gap-4 sm:grid-cols-2">
            {(['first_name', 'middle_name', 'last_name', 'suffix'] as const).map((field) => <div key={field} className="space-y-2">
                <Label htmlFor={`quick-${field}`}>{field.replace('_', ' ')}{field === 'first_name' || field === 'last_name' ? ' *' : ' (optional)'}</Label>
                <Input id={`quick-${field}`} value={form.data[field]} maxLength={field === 'suffix' ? 10 : 50} required={field === 'first_name' || field === 'last_name'}
                    onChange={(e) => form.setData(field, e.target.value)} disabled={form.processing} />
                <InputError message={form.errors[field]} />
            </div>)}
        </div>
        <EncoderNameSearch eventId={eventId} forceOpen={forceOpen} name={[form.data.first_name, form.data.middle_name, form.data.last_name, form.data.suffix].filter(Boolean).join(' ')} />
        <Button type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : 'Create walk-in and mark present'}</Button>
    </form>;
}
