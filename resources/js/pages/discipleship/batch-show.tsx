import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    Pencil,
    Plus,
    Save,
    Trash2,
    Users,
} from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import DatePicker from '@/components/date-picker';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useConfirmDialog } from '@/hooks/use-confirm-dialog';

type BatchData = {
    id: number;
    name: string;
    start_date: string;
};

type ProgramData = {
    id: number;
    name: string;
    slug: string;
};

type SessionRow = {
    id: number;
    session_date: string;
    topic: string;
    notes: string | null;
};

type EnrollmentRow = {
    id: number;
    participant_id: number;
    full_name: string | null;
    email: string | null;
    status: string;
};

type AttendanceRow = {
    enrollment_id: number;
    session_id: number;
    status: string;
};

type ProgressRow = {
    attended: number;
    applicable: number;
    percentage: number | null;
    required_sessions: number | null;
    is_complete: boolean;
};

type Props = {
    batch: BatchData;
    program: ProgramData;
    sessions: SessionRow[];
    sessions_count: number;
    enrollments: EnrollmentRow[];
    enrollments_count: number;
    attendances: AttendanceRow[];
    progress: Record<number, ProgressRow>;
};

type AttendanceStatus = 'present' | 'absent' | 'excused' | '';

const STATUS_OPTIONS: { value: AttendanceStatus; label: string }[] = [
    { value: '', label: '—' },
    { value: 'present', label: 'Present' },
    { value: 'absent', label: 'Absent' },
    { value: 'excused', label: 'Excused' },
];

const CELL_STYLES: Record<AttendanceStatus, string> = {
    present:
        'bg-lime-brand/15 text-lime-700 border-lime-brand/30 dark:text-lime-brand',
    absent: 'bg-destructive/10 text-destructive border-destructive/30',
    excused:
        'bg-[#13253B]/10 text-[#13253B] border-[#13253B]/30 dark:bg-yellow-500/10 dark:text-yellow-500 dark:border-yellow-500/30',
    '': 'bg-transparent text-muted-foreground border-white/10',
};

function formatDate(iso: string): string {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });
}

function cellKey(enrollmentId: number, sessionId: number): string {
    return `${enrollmentId}:${sessionId}`;
}

function ProgressCell({ progress }: { progress: ProgressRow | undefined }) {
    if (!progress || progress.applicable === 0) {
        return (
            <span className="text-xs italic text-muted-foreground/60">
                —
            </span>
        );
    }

    const pct = progress.percentage ?? 0;

    return (
        <div className="flex items-center gap-2">
            <div className="h-1.5 w-14 overflow-hidden rounded-full bg-white/5">
                <div
                    className="h-full rounded-full bg-lime-brand"
                    style={{ width: `${pct}%` }}
                />
            </div>
            <span className="text-xs tabular-nums text-muted-foreground">
                {progress.attended}/{progress.applicable}
            </span>
        </div>
    );
}

export default function DiscipleshipBatchShow({
    batch,
    program,
    sessions,
    sessions_count,
    enrollments,
    enrollments_count,
    attendances,
    progress,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<SessionRow | null>(null);
    const { dialog, openConfirm } = useConfirmDialog();

    const [cells, setCells] = useState<Record<string, AttendanceStatus>>(() => {
        const initial: Record<string, AttendanceStatus> = {};
        for (const a of attendances) {
            initial[cellKey(a.enrollment_id, a.session_id)] = a.status as AttendanceStatus;
        }
        return initial;
    });

    const [dirty, setDirty] = useState<Set<string>>(new Set());
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        const initial: Record<string, AttendanceStatus> = {};
        for (const a of attendances) {
            initial[cellKey(a.enrollment_id, a.session_id)] = a.status as AttendanceStatus;
        }
        setCells(initial);
        setDirty(new Set());
    }, [attendances]);

    const hasDirty = dirty.size > 0;

    const setCell = (enrollmentId: number, sessionId: number, status: AttendanceStatus) => {
        const key = cellKey(enrollmentId, sessionId);
        setCells((prev) => ({ ...prev, [key]: status }));
        setDirty((prev) => {
            const next = new Set(prev);
            next.add(key);
            return next;
        });
    };

    const save = () => {
        if (!hasDirty) return;
        setSaving(true);

        const payload = {
            cells: Array.from(dirty)
                .map((key) => {
                    const [eidStr, sidStr] = key.split(':');
                    const status = cells[key];
                    if (status === '') return null;
                    return {
                        enrollment_id: Number(eidStr),
                        session_id: Number(sidStr),
                        status,
                    };
                })
                .filter((c): c is { enrollment_id: number; session_id: number; status: 'present' | 'absent' | 'excused' } => c !== null),
        };

        if (payload.cells.length === 0) {
            setSaving(false);
            return;
        }

        router.post(`/discipleship/batches/${batch.id}/attendances/bulk`, payload, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    };

    const deleteSession = (session: SessionRow) => {
        openConfirm({
            variant: 'danger',
            title: `Delete "${session.topic}"?`,
            description: `Session on ${formatDate(session.session_date)} will be permanently removed. Its attendance records will also be removed.`,
            confirmLabel: 'Delete',
            onConfirm: () =>
                router.delete(`/discipleship/sessions/${session.id}`, {
                    preserveScroll: true,
                }),
        });
    };

    return (
        <>
            <Head title={`${batch.name} — Discipleship`} />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
                <Link
                    href={`/discipleship/programs/${program.slug}`}
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    {program.name}
                </Link>

                <div className="glass-panel rounded-xl p-6">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div className="pl-10">
                            <h1 className="text-xl font-semibold text-foreground">
                                {batch.name}
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Starts {formatDate(batch.start_date)}
                            </p>
                            <p className="mt-3 text-xs text-muted-foreground">
                                {sessions_count}{' '}
                                {sessions_count === 1 ? 'session' : 'sessions'}
                                {' · '}
                                {enrollments_count}{' '}
                                {enrollments_count === 1
                                    ? 'person enrolled'
                                    : 'people enrolled'}
                            </p>
                        </div>
                        <Button
                            onClick={() => setCreateOpen(true)}
                            className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Add Session
                        </Button>
                    </div>
                </div>

                <section className="flex flex-col gap-3">
                    <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                        Sessions
                    </h2>

                    {sessions.length === 0 ? (
                        <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-12 text-center">
                            <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-lime-brand/15">
                                <CalendarDays className="h-7 w-7 text-lime-brand" />
                            </div>
                            <p className="mb-1 text-sm font-medium text-foreground">
                                No sessions yet
                            </p>
                            <p className="mb-6 max-w-sm text-xs text-muted-foreground">
                                Add the first session to begin scheduling.
                            </p>
                            <Button
                                onClick={() => setCreateOpen(true)}
                                className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                Add Session
                            </Button>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-2">
                            {sessions.map((session) => (
                                <div
                                    key={session.id}
                                    className="glass-panel flex items-start justify-between gap-3 rounded-xl p-4"
                                >
                                    <div className="flex flex-1 flex-col gap-1">
                                        <div className="flex items-center gap-3">
                                            <CalendarDays className="h-4 w-4 shrink-0 text-muted-foreground" />
                                            <span className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                                {formatDate(session.session_date)}
                                            </span>
                                        </div>
                                        <p className="text-sm font-medium text-foreground">
                                            {session.topic}
                                        </p>
                                        {session.notes && (
                                            <p className="text-xs text-muted-foreground">
                                                {session.notes}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex shrink-0 items-center gap-1">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setEditTarget(session)}
                                            aria-label={`Edit ${session.topic}`}
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => deleteSession(session)}
                                            className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            aria-label={`Delete ${session.topic}`}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </section>

                <section className="flex flex-col gap-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                            Attendance
                        </h2>
                        {hasDirty && (
                            <div className="flex items-center gap-2">
                                <span className="text-xs text-muted-foreground">
                                    {dirty.size} unsaved{' '}
                                    {dirty.size === 1 ? 'change' : 'changes'}
                                </span>
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={save}
                                    disabled={saving}
                                    className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                                >
                                    <Save className="mr-1.5 h-3.5 w-3.5" />
                                    {saving ? 'Saving…' : 'Save Attendance'}
                                </Button>
                            </div>
                        )}
                    </div>

                    {enrollments.length === 0 ? (
                        <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-12 text-center">
                            <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-lime-brand/15">
                                <Users className="h-7 w-7 text-lime-brand" />
                            </div>
                            <p className="mb-1 text-sm font-medium text-foreground">
                                No enrollments
                            </p>
                            <p className="max-w-sm text-xs text-muted-foreground">
                                Enroll participants in this program before
                                recording attendance.
                            </p>
                        </div>
                    ) : sessions.length === 0 ? (
                        <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-12 text-center">
                            <p className="text-sm text-muted-foreground">
                                Add at least one session to record attendance.
                            </p>
                        </div>
                    ) : (
                        <div className="glass-panel overflow-hidden rounded-xl">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-white/10">
                                            <th className="sticky left-0 z-10 bg-background/80 px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground backdrop-blur">
                                                Participant
                                            </th>
                                            {sessions.map((s) => (
                                                <th
                                                    key={s.id}
                                                    className="min-w-[100px] px-2 py-3 text-center text-xs font-medium text-muted-foreground"
                                                    title={s.topic}
                                                >
                                                    {formatDate(s.session_date)}
                                                </th>
                                            ))}
                                            <th className="min-w-[120px] px-3 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                                Progress
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {enrollments.map((enrollment) => (
                                            <tr
                                                key={enrollment.id}
                                                className="border-b border-white/5 last:border-b-0"
                                            >
                                                <td className="sticky left-0 z-10 bg-background/80 px-4 py-3 font-medium text-foreground backdrop-blur">
                                                    {enrollment.full_name ?? '—'}
                                                </td>
                                                {sessions.map((s) => {
                                                    const key = cellKey(enrollment.id, s.id);
                                                    const value = cells[key] ?? '';
                                                    const isDirty = dirty.has(key);
                                                    return (
                                                        <td
                                                            key={s.id}
                                                            className="px-1 py-2 text-center"
                                                        >
                                                            <select
                                                                value={value}
                                                                onChange={(e) =>
                                                                    setCell(
                                                                        enrollment.id,
                                                                        s.id,
                                                                        e.target.value as AttendanceStatus,
                                                                    )
                                                                }
                                                                className={`cursor-pointer rounded-full border px-2 py-1 text-xs font-medium outline-none ${CELL_STYLES[value]} ${
                                                                    isDirty
                                                                        ? 'ring-1 ring-lime-brand/40'
                                                                        : ''
                                                                }`}
                                                            >
                                                                {STATUS_OPTIONS.map((o) => (
                                                                    <option
                                                                        key={o.value}
                                                                        value={o.value}
                                                                    >
                                                                        {o.label}
                                                                    </option>
                                                                ))}
                                                            </select>
                                                        </td>
                                                    );
                                                })}
                                                <td className="px-3 py-3">
                                                    <ProgressCell progress={progress[enrollment.id]} />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </section>
            </div>

            {dialog}

            {createOpen && (
                <SessionFormDialog
                    mode="create"
                    batchId={batch.id}
                    open={createOpen}
                    onOpenChange={setCreateOpen}
                />
            )}

            {editTarget !== null && (
                <SessionFormDialog
                    mode="edit"
                    batchId={batch.id}
                    session={editTarget}
                    open={editTarget !== null}
                    onOpenChange={(open) => {
                        if (!open) setEditTarget(null);
                    }}
                />
            )}
        </>
    );
}

type FormMode = 'create' | 'edit';

type FormProps = {
    mode: FormMode;
    batchId: number;
    session?: SessionRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function SessionFormDialog({
    mode,
    batchId,
    session,
    open,
    onOpenChange,
}: FormProps) {
    const isEdit = mode === 'edit';

    const form = useForm({
        session_date: session?.session_date ?? '',
        topic: session?.topic ?? '',
        notes: session?.notes ?? '',
    });

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();

        const onSuccess = () => {
            form.reset();
            onOpenChange(false);
        };

        if (isEdit && session) {
            form.put(`/discipleship/sessions/${session.id}`, {
                onSuccess,
                preserveScroll: true,
            });
        } else {
            form.post(`/discipleship/batches/${batchId}/sessions`, {
                onSuccess,
                preserveScroll: true,
            });
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? 'Edit Session' : 'Add Session'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEdit
                            ? 'Update the details of this session.'
                            : 'Create a new session for this batch.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={onSubmit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="session-date">
                            Session Date{' '}
                            <span className="text-destructive">*</span>
                        </Label>
                        <DatePicker
                            id="session-date"
                            value={form.data.session_date}
                            onChange={(v) => form.setData('session_date', v)}
                            placeholder="Pick a date"
                        />
                        <InputError message={form.errors.session_date} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="session-topic">
                            Topic <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="session-topic"
                            value={form.data.topic}
                            onChange={(e) => form.setData('topic', e.target.value)}
                            placeholder="e.g. Week 1 — Welcome and Orientation"
                            autoFocus
                        />
                        <InputError message={form.errors.topic} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="session-notes">Notes</Label>
                        <textarea
                            id="session-notes"
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            rows={3}
                            className="border-input bg-input placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-20 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                            placeholder="Optional notes about this session"
                        />
                        <InputError message={form.errors.notes} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                        >
                            {form.processing
                                ? 'Saving…'
                                : isEdit
                                  ? 'Save Changes'
                                  : 'Create Session'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

DiscipleshipBatchShow.layout = {
    breadcrumbs: [
        { title: 'Discipleship', href: '/discipleship' },
        { title: 'Batch', href: '#' },
    ],
};
