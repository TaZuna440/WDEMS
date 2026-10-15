import { Head } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';

type Props = {
    event_name: string;
    event_date: string | null;
};

export default function RegistrationConfirmed({ event_name, event_date }: Props) {
    return (
        <>
            <Head title={`Registration confirmed — ${event_name}`} />
            <div className="rounded-xl border border-lime-brand/30 bg-lime-brand/5 p-10 text-center">
                <div className="mx-auto mb-6 flex h-14 w-14 items-center justify-center rounded-full bg-lime-brand/15">
                    <CheckCircle2 className="h-7 w-7 text-lime-brand" />
                </div>
                <h1 className="text-xl font-semibold text-foreground">
                    Your registration is confirmed.
                </h1>
                <p className="mt-2 text-sm text-muted-foreground">
                    Your Registration Code has been acknowledged and is no longer displayed here.
                </p>
                <p className="mt-6 text-sm text-foreground">
                    {event_name}
                    {event_date !== null && ` · ${event_date}`}
                </p>
            </div>
        </>
    );
}
