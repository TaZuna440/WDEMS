import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Participant = {
    registration_id: number;
    full_name: string;
    contact: string | null;
    age: number | null;
    birthdate: string | null;
    source_label: string;
    attendance_status: 'present' | 'absent';
    total_appearances: number;
    history: { event_name: string; event_date: string | null; status: string }[];
    details: Record<string, string | number | null>;
    answers: { label: string; value: string | null }[];
};
type Props = {
    event: { id: number; event_name: string; event_date: string | null; venue: string | null; status: string; status_label: string; event_type_label: string };
    summary: { registered_form: number; no_shows: number; show_rate: number | null; sources: { online: number; paper: number; quick: number } };
    cross_event: { rows: { id: number; event_name: string; event_date: string | null; status: string; is_current: boolean }[] };
    participants: Participant[];
};
const selectClass = 'rounded-md border border-input bg-background px-3 py-2 text-sm';

function answerText(value: string | null) {
    if (!value) return '—';
    try {
        const parsed: unknown = JSON.parse(value);
        if (Array.isArray(parsed)) return parsed.join(', ');
    } catch { /* Plain text answers are already displayable. */ }
    return value;
}

export default function ReportShow({ event, summary, cross_event, participants }: Props) {
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('all');
    const [source, setSource] = useState('all');
    const [detailed, setDetailed] = useState(false);
    const [eventSearch, setEventSearch] = useState('');
    const [eventStatus, setEventStatus] = useState('all');
    const rows = participants.filter(p =>
        `${p.full_name} ${p.contact ?? ''}`.toLowerCase().includes(search.trim().toLowerCase()) &&
        (status === 'all' || p.attendance_status === status) &&
        (source === 'all' || p.source_label === source),
    );
    const events = cross_event.rows.filter(e =>
        e.event_name.toLowerCase().includes(eventSearch.trim().toLowerCase()) &&
        (eventStatus === 'all' || e.status === eventStatus),
    );
    return <>
        <Head title={`Report — ${event.event_name}`} />
        <main className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6">
            <div className="flex items-center justify-between print:hidden">
                <Link href={`/events/${event.id}`} className="text-sm underline">Back to Event</Link>
                <Button variant="outline" onClick={() => window.print()}>Print report</Button>
            </div>
            <header>
                <h1 className="text-2xl font-semibold">{event.event_name}</h1>
                <p className="text-sm text-muted-foreground">{[event.status_label, event.event_date, event.venue, event.event_type_label].filter(Boolean).join(' · ')}</p>
            </header>
            {event.status === 'ongoing' && <p className="rounded-xl border p-4 text-sm">Live report — figures may change until finalization. Participants without a check-in are shown as Absent.</p>}
            <section aria-label="Event summary" className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {[
                    ['Registered (Form)', summary.registered_form],
                    ['Registration sources', `Online ${summary.sources.online} · Paper Form ${summary.sources.paper} · Quick Walk-in ${summary.sources.quick}`],
                    ['No-shows', summary.no_shows],
                    ['Show Rate (Form)', summary.show_rate === null ? '—' : `${summary.show_rate.toFixed(1)}%`],
                ].map(([label, value]) => <div key={label} className="glass-panel rounded-xl p-5"><h2 className="text-xs uppercase text-muted-foreground">{label}</h2><p className="mt-2 text-lg font-semibold">{value}</p></div>)}
            </section>
            <section className="space-y-4">
                <h2 className="text-sm font-semibold uppercase tracking-wider">Cross-events</h2>
                <div className="flex flex-wrap gap-3 print:hidden">
                    <Input aria-label="Search events" placeholder="Search events…" value={eventSearch} onChange={e => setEventSearch(e.target.value)} className="max-w-sm" />
                    <select aria-label="Filter events by status" className={selectClass} value={eventStatus} onChange={e => setEventStatus(e.target.value)}><option value="all">All statuses</option><option value="ongoing">Ongoing</option><option value="completed">Completed</option></select>
                </div>
                <ul className="divide-y rounded-xl border">
                    {events.map(e => <li key={e.id} className="flex flex-wrap items-center justify-between gap-2 p-4"><Link className="font-medium underline" href={`/events/${e.id}/report`}>{e.event_name}{e.is_current ? ' (this event)' : ''}</Link><span className="text-sm text-muted-foreground">{e.event_date ?? 'No date'} · {e.status === 'completed' ? 'Completed' : 'Ongoing'}</span></li>)}
                    {!events.length && <li className="p-4 text-sm text-muted-foreground">No matching events.</li>}
                </ul>
            </section>
            <section className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-sm font-semibold uppercase tracking-wider">Participants ({rows.length})</h2>
                    <div className="flex flex-wrap gap-2 print:hidden">
                        <Button variant="outline" onClick={() => setDetailed(!detailed)}>{detailed ? 'View Participant Table' : 'View Detailed Participants'}</Button>
                        <Button variant="outline" asChild><a href={`/events/${event.id}/report/export`}>Export CSV (all participants)</a></Button>
                    </div>
                </div>
                <div className="flex flex-wrap gap-3 print:hidden">
                    <Input aria-label="Search participants" placeholder="Search name or contact…" value={search} onChange={e => setSearch(e.target.value)} className="max-w-sm" />
                    <select aria-label="Attendance filter" className={selectClass} value={status} onChange={e => setStatus(e.target.value)}><option value="all">All attendance</option><option value="present">Present</option><option value="absent">Absent</option></select>
                    <select aria-label="Source filter" className={selectClass} value={source} onChange={e => setSource(e.target.value)}><option value="all">All sources</option>{['Online', 'Paper Form', 'Quick Walk-in'].map(label => <option key={label}>{label}</option>)}</select>
                </div>
                {!rows.length ? <p className="rounded-xl border p-6 text-sm text-muted-foreground">No matching participants.</p> : detailed ? <div className="space-y-4">
                    {rows.map(p => <article key={p.registration_id} className="glass-panel space-y-4 rounded-xl p-5">
                        <h3 className="font-semibold">{p.full_name}</h3>
                        <p className="text-sm">{p.attendance_status === 'present' ? 'Present' : 'Absent'} · {p.source_label} · {p.total_appearances} appearances</p>
                        <dl className="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                            {Object.entries({ Age: p.age, Birthdate: p.birthdate, ...p.details }).map(([label, value]) => <div key={label}><dt className="text-muted-foreground">{label}</dt><dd className="whitespace-pre-wrap break-words">{value ?? '—'}</dd></div>)}
                            {p.answers.map((a, i) => <div key={i}><dt className="text-muted-foreground">{a.label}</dt><dd className="whitespace-pre-wrap break-words">{answerText(a.value)}</dd></div>)}
                        </dl>
                        <History participant={p} />
                    </article>)}
                </div> : <div className="overflow-x-auto rounded-xl border"><table className="w-full text-left text-sm"><thead><tr className="border-b">{['Name', 'Contact', 'Age', 'Birthdate', 'Source', 'Attendance', 'Appearances'].map(h => <th key={h} className="p-3 font-medium">{h}</th>)}</tr></thead><tbody>
                    {rows.map(p => <tr key={p.registration_id} className="border-b last:border-0"><td className="p-3 font-medium">{p.full_name}</td><td className="p-3">{p.contact ?? '—'}</td><td className="p-3">{p.age ?? '—'}</td><td className="p-3">{p.birthdate ?? '—'}</td><td className="p-3">{p.source_label}</td><td className="p-3">{p.attendance_status === 'present' ? 'Present' : 'Absent'}</td><td className="p-3">{p.total_appearances}<History participant={p} /></td></tr>)}
                </tbody></table></div>}
            </section>
        </main>
    </>;
}
function History({ participant }: { participant: Participant }) {
    if (!participant.history.length) return null;
    return <details className="mt-2 text-sm"><summary className="cursor-pointer text-muted-foreground">Prior appearances</summary><ul className="mt-2 space-y-1">{participant.history.map((h, i) => <li key={i}>{h.event_name} · {h.event_date ?? 'No date'} · Present</li>)}</ul></details>;
}
ReportShow.layout = { breadcrumbs: [{ title: 'Events', href: '/events' }, { title: 'Report', href: '#' }] };
