import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, Check, Clock, UserCheck, UserX, X } from 'lucide-react';

type AttendanceStatus = 'present' | 'late' | 'absent' | 'excused';

type StatusOption = {
    value: AttendanceStatus;
    label: string;
};

type AttendanceRow = {
    id: number;
    source: string;
    registered_at: string | null;
    participant: {
        id: number | null;
        full_name: string | null;
        email: string | null;
        contact_number: string | null;
    };
    attendance: {
        status: AttendanceStatus;
        status_label: string;
        time: string | null;
        notes: string | null;
    } | null;
};

type EventData = {
    id: number;
    event_name: string;
    status: string;
    status_label: string;
    event_date: string | null;
    venue: string | null;
};

type Props = {
    event: EventData;
    rows: AttendanceRow[];
    attendance_statuses: StatusOption[];
};

const STATUS_STYLES: Record<AttendanceStatus, string> = {
    present: 'bg-green-500/15 text-green-400 border-green-500/30',
    late: 'bg-yellow-500/15 text-yellow-500 border-yellow-500/30',
    absent: 'bg-destructive/15 text-destructive border-destructive/30',
    excused: 'bg-blue-500/15 text-blue-400 border-blue-500/30',
};

const BUTTON_STYLES: Record<AttendanceStatus, string> = {
    present:
        'hover:border-green-500 hover:bg-green-500 hover:text-navy-900',
    late: 'hover:border-yellow-500 hover:bg-yellow-500 hover:text-navy-900',
    absent:
        'hover:border-destructive hover:bg-destructive hover:text-white',
    excused:
        'hover:border-blue-500 hover:bg-blue-500 hover:text-white',
};

const ICONS = {
    present: Check,
    late: Clock,
    absent: X,
    excused: UserX,
};

function sourceLabel(source: string): string {
    return source === 'walk_in' ? 'Walk-in' : source === 'paper' ? 'Paper' : 'Form';
}

export default function EventsAttendance({
    event,
    rows,
    attendance_statuses,
}: Props) {
    const [marking, setMarking] = useState<number | null>(null);

    const mark = (registrationId: number, status: AttendanceStatus) => {
        setMarking(registrationId);
        router.post(
            `/events/${event.id}/attendance/${registrationId}/mark`,
            { status },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setMarking(null),
            },
        );
    };

    return (
        <>
            <Head title={`Attendance — ${event.event_name}`} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6">
                <Link
                    href={`/events/${event.id}`}
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Event
                </Link>

                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Attendance
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {event.event_name}
                            {event.event_date && ` · ${event.event_date}`}
                        </p>
                    </div>
                    <span className="inline-flex w-fit rounded-full bg-secondary px-2.5 py-1 text-xs font-medium text-foreground">
                        {event.status_label}
                    </span>
                </div>

                <div className="glass-panel rounded-xl p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                            Registered Participants
                        </h2>
                        <span className="rounded-full bg-white/5 px-2 py-0.5 text-xs font-medium text-muted-foreground">
                            {rows.length}
                        </span>
                    </div>

                    {rows.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <UserCheck className="h-10 w-10 text-muted-foreground/40" />
                            <p className="mt-3 text-sm text-foreground">
                                No registrations yet.
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Participants will appear here once registration opens and responses come in.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-white/5 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                        <th className="pb-3 pr-4">Participant</th>
                                        <th className="pb-3 pr-4">Contact</th>
                                        <th className="pb-3 pr-4">Source</th>
                                        <th className="pb-3 pr-4">Current</th>
                                        <th className="pb-3 text-right">Mark as</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-b border-white/5 last:border-b-0"
                                        >
                                            <td className="py-3 pr-4">
                                                <div className="font-medium text-foreground">
                                                    {row.participant.full_name ?? '—'}
                                                </div>
                                                {row.participant.email && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {row.participant.email}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="py-3 pr-4 text-muted-foreground">
                                                {row.participant.contact_number ?? '—'}
                                            </td>
                                            <td className="py-3 pr-4 text-muted-foreground">
                                                {sourceLabel(row.source)}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {row.attendance ? (
                                                    <span
                                                        className={`inline-flex rounded-full border px-2 py-0.5 text-xs font-medium ${
                                                            STATUS_STYLES[row.attendance.status]
                                                        }`}
                                                    >
                                                        {row.attendance.status_label}
                                                    </span>
                                                ) : (
                                                    <span className="text-xs italic text-muted-foreground/60">
                                                        Not marked
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-3">
                                                <div className="flex justify-end gap-1">
                                                    {attendance_statuses.map((s) => {
                                                        const Icon = ICONS[s.value];
                                                        const isCurrent =
                                                            row.attendance?.status === s.value;

                                                        return (
                                                            <button
                                                                key={s.value}
                                                                type="button"
                                                                onClick={() =>
                                                                    mark(row.id, s.value)
                                                                }
                                                                disabled={marking === row.id}
                                                                title={s.label}
                                                                className={`inline-flex h-8 w-8 items-center justify-center rounded-md border text-xs transition-colors disabled:opacity-50 ${
                                                                    isCurrent
                                                                        ? STATUS_STYLES[s.value]
                                                                        : 'border-white/10 text-muted-foreground'
                                                                } ${BUTTON_STYLES[s.value]}`}
                                                            >
                                                                <Icon className="h-4 w-4" />
                                                            </button>
                                                        );
                                                    })}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

EventsAttendance.layout = {
    breadcrumbs: [
        { title: 'Events', href: '/events' },
        { title: 'Attendance', href: '#' },
    ],
};
