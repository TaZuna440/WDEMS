import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Search, Users } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { churchParticipantTypeLabel } from '@/lib/church-participant-types';

type Row = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    code: string | null;
    status: 'unmarked' | 'present' | 'absent';
    participant_type: string | null;
    fellowship_group_id: number | null;
};
type Props = {
    event: { id: number; event_name: string };
    rows: Row[];
    counts: {
        registered: number;
        present: number;
        absent: number;
        unmarked: number;
    };
    filters: { q?: string; code?: string; filter?: string };
    summary: boolean;
    urls: {
        registrations: string;
        attendance: string;
        reports: string;
    };
};
export default function ChurchAttendance({
    event,
    rows,
    counts,
    filters,
    summary,
    urls,
}: Props) {
    const [query, setQuery] = useState(filters.q ?? '');
    const [code, setCode] = useState(filters.code ?? '');
    const [error, setError] = useState('');
    const base = `/events/${event.id}/church-attendance`;
    const search = (filter = filters.filter ?? 'all') =>
        router.get(base, { q: query, code, filter }, { preserveState: true });
    return (
        <>
            <Head
                title={`${summary ? 'Summary' : 'Attendance'} — ${event.event_name}`}
            />
            <main className="mx-auto max-w-6xl space-y-5 p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <Link
                            href={urls.registrations}
                            className="text-muted-foreground hover:text-foreground mb-2 inline-flex items-center gap-2 text-sm"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Back to registrations
                        </Link>
                        <h1 className="text-2xl font-semibold">
                            {event.event_name} ·{' '}
                            {summary ? 'Attendance Summary' : 'Attendance'}
                        </h1>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={urls.registrations}>Registrations</Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link
                                href={
                                    summary
                                        ? urls.attendance
                                        : `${urls.attendance}?summary=1`
                                }
                            >
                                {summary ? 'Edit Attendance' : 'View Summary'}
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={urls.reports}>Reports</Link>
                        </Button>
                    </div>
                </header>
                <div className="flex flex-wrap gap-3">
                    {Object.entries(counts).map(([name, count]) => (
                        <div key={name} className="bg-card rounded border p-4">
                            {name}: <strong>{count}</strong>
                        </div>
                    ))}
                </div>
                {error && (
                    <p role="alert" className="text-destructive">
                        {error}
                    </p>
                )}
                {!summary && (
                    <>
                        <form
                            className="flex gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                search();
                            }}
                        >
                            <Input
                                aria-label="Registration code"
                                placeholder="e.g. KM4X-P2N7"
                                value={code}
                                onChange={(e) => setCode(e.target.value)}
                            />
                            <Button>Look up</Button>
                        </form>
                        <form
                            className="flex gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                search();
                            }}
                        >
                            <div className="relative w-full max-w-lg">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                <Input
                                    aria-label="Search name, email or phone"
                                    placeholder="Search name, email, or phone..."
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    className="pl-9"
                                />
                            </div>
                            <Button variant="outline">Search</Button>
                        </form>
                        <div className="flex gap-2">
                            {['all', 'unmarked', 'present', 'absent'].map(
                                (filter) => (
                                    <Button
                                        key={filter}
                                        variant={
                                            filter === (filters.filter ?? 'all')
                                                ? 'default'
                                                : 'outline'
                                        }
                                        onClick={() => search(filter)}
                                    >
                                        {filter} (
                                        {filter === 'all'
                                            ? counts.registered
                                            : counts[
                                                  filter as
                                                      | 'present'
                                                      | 'absent'
                                                      | 'unmarked'
                                              ]}
                                        )
                                    </Button>
                                ),
                            )}
                        </div>
                    </>
                )}
                {rows.length === 0 ? (
                    <section className="glass-panel flex flex-col items-center justify-center rounded-xl border p-12 text-center">
                        <div className="bg-lime-brand/15 mb-4 flex h-14 w-14 items-center justify-center rounded-full">
                            {filters.q || filters.code ? (
                                <Search className="text-lime-brand h-7 w-7" />
                            ) : (
                                <Users className="text-lime-brand h-7 w-7" />
                            )}
                        </div>
                        <h2 className="text-lg font-semibold">
                            {filters.q || filters.code
                                ? 'No participants found'
                                : 'No registrations yet'}
                        </h2>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {filters.q || filters.code
                                ? 'Try another search term or registration code.'
                                : 'Participants will appear here after they register.'}
                        </p>
                    </section>
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr>
                                    {['Name', 'Contact', 'Type', 'Status'].map(
                                        (label) => (
                                            <th key={label} className="p-3">
                                                {label}
                                            </th>
                                        ),
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => (
                                    <tr key={row.id} className="border-t">
                                        <td className="p-3">{row.name}</td>
                                        <td className="p-3">
                                            {row.email}
                                            <div>{row.phone}</div>
                                        </td>
                                        <td className="p-3">
                                            {churchParticipantTypeLabel(
                                                row.participant_type,
                                            )}
                                        </td>
                                        <td className="p-3">
                                            {summary ? (
                                                row.status
                                            ) : (
                                                <select
                                                    aria-label={`Attendance for ${row.name}`}
                                                    className="bg-background rounded border p-2"
                                                    value={row.status}
                                                    onChange={(e) =>
                                                        router.post(
                                                            `${base}/${row.id}`,
                                                            {
                                                                status: e.target
                                                                    .value,
                                                            },
                                                            {
                                                                preserveScroll: true,
                                                                onError: (
                                                                    errors,
                                                                ) =>
                                                                    setError(
                                                                        Object.values(
                                                                            errors,
                                                                        ).join(
                                                                            ' ',
                                                                        ),
                                                                    ),
                                                            },
                                                        )
                                                    }
                                                >
                                                    {[
                                                        'unmarked',
                                                        'present',
                                                        'absent',
                                                    ].map((status) => (
                                                        <option key={status}>
                                                            {status}
                                                        </option>
                                                    ))}
                                                </select>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </main>
        </>
    );
}
ChurchAttendance.layout = {
    breadcrumbs: [{ title: 'Church attendance', href: '#' }],
};
