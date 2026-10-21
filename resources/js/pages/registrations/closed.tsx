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

            <div className="border-border bg-card/40 rounded-xl border p-10 text-center">
                <div className="bg-secondary mx-auto mb-6 flex h-14 w-14 items-center justify-center rounded-full">
                    <CalendarDays className="text-muted-foreground h-6 w-6" />
                </div>

                <h1 className="text-foreground text-xl font-semibold">
                    {event.event_name}
                </h1>

                {event.event_date !== null && (
                    <p className="text-muted-foreground mt-1 text-sm">
                        {event.event_date}
                    </p>
                )}

                <p className="text-foreground mt-8 text-base">
                    Registration for this event is closed.
                </p>
                <p className="text-muted-foreground mt-2 text-sm">
                    If you believe this is a mistake, contact the event
                    organizer directly.
                </p>
            </div>
        </>
    );
}
