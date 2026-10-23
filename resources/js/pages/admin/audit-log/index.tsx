import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type AuditLogRow = {
    id: number;
    actor_type: 'user' | 'system';
    action: string;
    target_type: string | null;
    target_id: number | null;
    details: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string;
    user: { id: number; name: string; email: string } | null;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

type PaginatedLogs = {
    data: AuditLogRow[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
};

type Props = {
    logs: PaginatedLogs;
    filters: {
        search: string;
        action: string;
        actor_type: string;
        user_id: string | number;
    };
    actions: string[];
};

export default function AdminAuditLogIndex({ logs, filters, actions }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [userId, setUserId] = useState(String(filters.user_id ?? ''));
    const [actorType, setActorType] = useState(filters.actor_type || 'all');

    const applyFilters = (action = filters.action) => {
        router.get(
            '/admin/audit-log',
            {
                search,
                action,
                actor_type: actorType === 'all' ? '' : actorType,
                user_id: userId,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Audit Log" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
                <header className="pl-10">
                    <h1 className="text-foreground text-2xl font-semibold">
                        Audit Log
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Chronological record of critical account and event
                        actions.
                    </p>
                </header>

                <form
                    className="flex flex-col gap-3 sm:flex-row"
                    onSubmit={(event) => {
                        event.preventDefault();
                        applyFilters();
                    }}
                >
                    <Input
                        aria-label="Search audit log"
                        placeholder="Search action, user, target, or details"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <Input
                        aria-label="Filter by user ID"
                        type="number"
                        min="1"
                        placeholder="User ID"
                        value={userId}
                        onChange={(event) => setUserId(event.target.value)}
                        className="sm:max-w-36"
                    />
                    <Select
                        value={actorType}
                        onValueChange={(value) => {
                            setActorType(value);
                            router.get(
                                '/admin/audit-log',
                                {
                                    search,
                                    action: filters.action,
                                    actor_type: value === 'all' ? '' : value,
                                    user_id: userId,
                                },
                                {
                                    preserveState: true,
                                    preserveScroll: true,
                                    replace: true,
                                },
                            );
                        }}
                    >
                        <SelectTrigger
                            aria-label="Filter by actor type"
                            className="sm:w-48"
                        >
                            <SelectValue placeholder="All actors" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All actors</SelectItem>
                            <SelectItem value="user">User Actions</SelectItem>
                            <SelectItem value="system">
                                System Actions
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.action || 'all'}
                        onValueChange={(value) =>
                            applyFilters(value === 'all' ? '' : value)
                        }
                    >
                        <SelectTrigger
                            aria-label="Filter by action type"
                            className="sm:w-56"
                        >
                            <SelectValue placeholder="All action types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                All action types
                            </SelectItem>
                            {actions.map((action) => (
                                <SelectItem key={action} value={action}>
                                    {action}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </form>

                <div className="glass-panel overflow-x-auto rounded-xl">
                    <table className="w-full text-left text-sm">
                        <thead className="border-border text-muted-foreground border-b">
                            <tr>
                                <th className="px-4 py-3 font-medium">Time</th>
                                <th className="px-4 py-3 font-medium">User</th>
                                <th className="px-4 py-3 font-medium">
                                    Action
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Target
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Details
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-border divide-y">
                            {logs.data.map((log) => (
                                <tr key={log.id} className="align-top">
                                    <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">
                                        {new Date(
                                            log.created_at,
                                        ).toLocaleString()}
                                    </td>
                                    <td className="px-4 py-3">
                                        {log.actor_type === 'system' ? (
                                            <span className="bg-muted text-muted-foreground inline-flex rounded-full px-2.5 py-1 text-xs font-medium">
                                                System
                                            </span>
                                        ) : log.user ? (
                                            `${log.user.name} (${log.user.email})`
                                        ) : (
                                            'Deleted user'
                                        )}
                                    </td>
                                    <td className="px-4 py-3 font-medium">
                                        {log.action}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {log.target_type
                                            ? `${log.target_type.split('\\').pop()} #${log.target_id}`
                                            : '—'}
                                    </td>
                                    <td className="text-muted-foreground max-w-md px-4 py-3 whitespace-pre-wrap">
                                        {log.action === 'email.sent' &&
                                        Array.isArray(log.details?.to) ? (
                                            <p className="mb-1">
                                                Sent email to{' '}
                                                {log.details.to.join(', ')}
                                            </p>
                                        ) : null}
                                        {log.details ? (
                                            <pre className="max-w-md overflow-x-auto font-sans text-xs whitespace-pre-wrap">
                                                {JSON.stringify(
                                                    log.details,
                                                    null,
                                                    2,
                                                )}
                                            </pre>
                                        ) : (
                                            '—'
                                        )}
                                        {log.ip_address && (
                                            <div className="mt-1 text-xs">
                                                IP: {log.ip_address}
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {logs.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="text-muted-foreground px-4 py-10 text-center"
                                    >
                                        No audit entries found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <footer className="text-muted-foreground flex flex-wrap items-center justify-between gap-3 text-sm">
                    <span>
                        {logs.total} entries · Page {logs.current_page} of{' '}
                        {logs.last_page}
                    </span>
                    <nav aria-label="Audit log pages" className="flex gap-1">
                        {logs.links.map((link, index) => (
                            <Link
                                key={`${index}-${link.label}`}
                                href={link.url ?? '#'}
                                preserveScroll
                                className={`rounded border px-3 py-1 ${
                                    link.active
                                        ? 'border-lime-brand bg-lime-brand/15 text-foreground'
                                        : 'border-border hover:bg-accent'
                                } ${link.url ? '' : 'pointer-events-none opacity-50'}`}
                            >
                                {link.label
                                    .replace('&laquo;', '‹')
                                    .replace('&raquo;', '›')}
                            </Link>
                        ))}
                    </nav>
                </footer>
            </div>
        </>
    );
}
