import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

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
};
export default function ChurchAttendance({
    event,
    rows,
    counts,
    filters,
    summary,
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
                <h1 className="text-2xl font-semibold">
                    {event.event_name} · {summary ? 'Summary' : 'Attendance'}
                </h1>
                <div className="flex gap-3">
                    <Link
                        className="underline"
                        href={`/events/${event.id}/registrations`}
                    >
                        Registration
                    </Link>
                    <Link
                        className="underline"
                        href={summary ? base : `${base}?summary=1`}
                    >
                        {summary ? 'Edit Attendance' : 'View Summary'}
                    </Link>
                </div>
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
                            <Input
                                aria-label="Search name, email or phone"
                                placeholder="e.g. Maria Santos, email, or phone"
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                            />
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
                <div className="overflow-x-auto rounded border">
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
                                        {row.participant_type}
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
                                                            onError: (errors) =>
                                                                setError(
                                                                    Object.values(
                                                                        errors,
                                                                    ).join(' '),
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
                            {!rows.length && (
                                <tr>
                                    <td colSpan={4} className="p-5">
                                        No matching active registrations.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </main>
        </>
    );
}
ChurchAttendance.layout = {
    breadcrumbs: [{ title: 'Church attendance', href: '#' }],
};
