import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';

type Person = { id: number; name: string; email: string | null; phone: string | null; birthday: string | null; provisional: boolean };
type Props = { event: { id: number; event_name: string }; pairs: { left: Person; right: Person; reasons: string[] }[] };
export default function Duplicates({ event, pairs }: Props) {
    const [search, setSearch] = useState('');
    const rows = pairs.filter(p => `${p.left.name} ${p.right.name} ${p.reasons.join(' ')}`.toLowerCase().includes(search.trim().toLowerCase()));
    return <><Head title={`Possible duplicates — ${event.event_name}`} /><main className="mx-auto max-w-5xl space-y-5 p-6">
        <Link className="text-sm underline" href={`/events/${event.id}`}>Back to Event</Link>
        <h1 className="text-2xl font-semibold">Possible Duplicates</h1>
        <p className="text-sm text-muted-foreground">{event.event_name} · Similar names with a shared birthdate, email, or phone. Review with the participant before taking action; a shared contact does not prove the same person. This report does not merge records.</p>
        <Input aria-label="Search possible duplicates" placeholder="Search names or match reasons…" value={search} onChange={e => setSearch(e.target.value)} />
        <p className="text-sm">{rows.length} suspicious pairs</p>
        {rows.map(pair => <article key={`${pair.left.id}-${pair.right.id}`} className="space-y-3 rounded-xl border p-5">
            <h2 className="text-sm font-medium">{pair.reasons.join(' · ')}</h2>
            <div className="grid gap-4 sm:grid-cols-2">{[pair.left, pair.right].map(p => <div key={p.id} className="space-y-1 text-sm"><p className="font-semibold">{p.name} · Registration #{p.id}</p><p>{p.email ?? 'No email'} · {p.phone ?? 'No phone'}</p><p>Birthdate: {p.birthday ?? 'Not recorded'}{p.provisional ? ' · Provisional' : ''}</p><Link className="underline" href={`/events/${event.id}/attendance?q=${encodeURIComponent(p.name)}`}>Review attendance</Link></div>)}</div>
        </article>)}
        {!rows.length && <p className="rounded-xl border p-5 text-sm">No matching suspicious pairs. This is an advisory check and cannot rule out all duplicates.</p>}
    </main></>;
}
Duplicates.layout = { breadcrumbs: [{ title: 'Events', href: '/events' }, { title: 'Possible Duplicates', href: '#' }] };
