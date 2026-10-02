import { Head } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';

type Props = {
    event: {
        event_name: string;
        event_date: string | null;
    };
};

/**
 * Rendered by EnsureRegistrationIsOpen when the bound event is not
 * accepting submissions — either status is not RegistrationOpen, or
 * registration_end has passed. HTTP 200, not 404.
 */
export default function RegistrationClosed({ event }: Props) {
    return (
        <>
            <Head title={`Registration closed — ${event.event_name}`} />

            <div className="rounded-xl border border-white/10 bg-white/[0.02] p-10 text-center">
                <div className="mx-auto mb-6 flex h-14 w-14 items-center justify-center rounded-full bg-white/5">
                    <CalendarDays className="h-6 w-6 text-muted-foreground" />
                </div>

                <h1 className="text-xl font-semibold text-foreground">
                    {event.event_name}
                </h1>

                {event.event_date !== null && (
                    <p className="mt-1 text-sm text-muted-foreground">
                        {event.event_date}
                    </p>
                )}

                <p className="mt-8 text-base text-foreground">
                    Registration for this event is closed.
                </p>
                <p className="mt-2 text-sm text-muted-foreground">
                    If you believe this is a mistake, contact the event
                    organizer directly.
                </p>
            </div>
        </>
    );
}
