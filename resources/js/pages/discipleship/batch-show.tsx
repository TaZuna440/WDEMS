import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
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

type Props = {
    batch: BatchData;
    program: ProgramData;
    sessions: SessionRow[];
    sessions_count: number;
    enrollments_count: number;
};

function formatDate(iso: string): string {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

export default function DiscipleshipBatchShow({
    batch,
    program,
    sessions,
    sessions_count,
    enrollments_count,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<SessionRow | null>(null);
    const { dialog, openConfirm } = useConfirmDialog();

    const deleteSession = (session: SessionRow) => {
        openConfirm({
            variant: 'danger',
            title: `Delete "${session.topic}"?`,
            description: `Session on ${formatDate(session.session_date)} will be permanently removed.`,
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

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-6">
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
                        <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-16 text-center">
                            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-lime-brand/15">
                                <CalendarDays className="h-8 w-8 text-lime-brand" />
                            </div>
                            <h2 className="mb-2 text-lg font-semibold text-foreground">
                                No sessions yet
                            </h2>
                            <p className="mb-6 max-w-sm text-sm text-muted-foreground">
                                Add the first session to begin scheduling
                                this batch.
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
