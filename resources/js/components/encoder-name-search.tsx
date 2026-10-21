import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type Match = { id: number; name: string; email: string | null; phone: string | null; provisional: boolean };
export default function EncoderNameSearch({ eventId, name, forceOpen = false }: { eventId: number; name: string; forceOpen?: boolean }) {
    const [marking, setMarking] = useState<number | null>(null);
    const [result, setResult] = useState<{ query: string; matches: Match[]; failed: boolean } | null>(null);
    const query = name.trim().replace(/\s+/g, ' ');
    useEffect(() => {
        if (query.length < 2) return;
        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            try {
                const response = await fetch(`/events/${eventId}/participant-review/search?q=${encodeURIComponent(query)}`, { signal: controller.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Search unavailable');
                const data = await response.json() as { matches: Match[] };
                setResult({ query, matches: data.matches, failed: false });
            } catch {
                if (!controller.signal.aborted) setResult({ query, matches: [], failed: true });
            }
        }, 300);
        return () => { window.clearTimeout(timer); controller.abort(); };
    }, [eventId, query]);
    if (query.length < 2 || result?.query !== query) return null;
    if (result.failed) return <p className="text-sm text-muted-foreground">Name lookup is unavailable. Check existing registrations before saving.</p>;
    if (!result.matches.length) return <p className="text-sm text-muted-foreground">No similar registrations found in this event.</p>;
    return <aside className="space-y-2 rounded-xl border border-amber-500/40 p-4 text-sm" aria-live="polite">
        <p className="font-medium">Review existing registrations before creating another</p>
        <ul className="space-y-2">{result.matches.map(m => <li key={m.id}><Link className="underline" href={`/events/${eventId}/attendance?q=${encodeURIComponent(m.name)}`}>{m.name}</Link> · {m.email ?? m.phone ?? 'No contact'}{m.provisional ? ' · Provisional' : ''} <button type="button" className="ml-2 rounded border px-2 py-1 font-medium" disabled={marking !== null} onClick={() => { setMarking(m.id); router.post(`/events/${eventId}/attendance/${m.id}/mark`, { status: 'present', force_open: forceOpen ? 1 : 0 }, { preserveScroll: true, onFinish: () => setMarking(null) }); }}>{marking === m.id ? 'Saving…' : 'Mark present'}</button></li>)}</ul>
        <p className="text-muted-foreground">Matches are suggestions. No records are linked automatically.</p>
    </aside>;
}
