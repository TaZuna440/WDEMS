import { Head, Link } from '@inertiajs/react';

type Decision = {
    id: number;
    registration_id: number;
    action: string;
    reason: string;
    rule_version: string;
    created_at: string;
};

type Props = {
    event: { id: number; event_name: string };
    decisions: {
        data: Decision[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};

export default function Duplicates({ event, decisions }: Props) {
    return (
        <main className="mx-auto max-w-5xl space-y-6 p-6">
            <Head title="Automatic identity checks" />
            <Link href={`/events/${event.id}`} className="underline">
                Back to event
            </Link>
            <h1 className="text-2xl font-semibold">
                Automatic identity checks
            </h1>
            <p>{event.event_name}</p>
            <p className="text-muted-foreground">
                The system reuses exact identities and keeps uncertain matches
                separate. No organizer review is required. This history explains
                the automatic decisions.
            </p>
            {decisions.data.length === 0 && (
                <p>No automatic decisions recorded yet.</p>
            )}
            <div className="space-y-3">
                {decisions.data.map((decision) => (
                    <article
                        key={decision.id}
                        className="space-y-2 rounded-lg border p-4"
                    >
                        <h2 className="font-medium">
                            Registration #{decision.registration_id}:{' '}
                            {decision.action.replaceAll('_', ' ')}
                        </h2>
                        <p>{decision.reason}</p>
                        <p className="text-muted-foreground text-sm">
                            {decision.created_at} · System ·{' '}
                            {decision.rule_version}
                        </p>
                    </article>
                ))}
            </div>
            <nav className="flex gap-4" aria-label="Decision history pages">
                {decisions.prev_page_url && (
                    <Link href={decisions.prev_page_url}>Previous</Link>
                )}
                {decisions.next_page_url && (
                    <Link href={decisions.next_page_url}>Next</Link>
                )}
            </nav>
        </main>
    );
}
