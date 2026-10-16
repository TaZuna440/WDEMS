import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    Pencil,
    Plus,
    Trash2,
    Users,
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

type ProgramData = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
};

type BatchRow = {
    id: number;
    name: string;
    start_date: string;
};

type Props = {
    program: ProgramData;
    batches: BatchRow[];
    batches_count: number;
};

function formatDate(iso: string): string {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

export default function DiscipleshipShow({
    program,
    batches,
    batches_count,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<BatchRow | null>(null);
    const { dialog, openConfirm } = useConfirmDialog();

    const deleteBatch = (batch: BatchRow) => {
        openConfirm({
            variant: 'danger',
            title: `Delete "${batch.name}"?`,
            description: `This batch starting ${formatDate(batch.start_date)} will be permanently removed.`,
            confirmLabel: 'Delete',
            onConfirm: () =>
                router.delete(`/discipleship/batches/${batch.id}`, {
                    preserveScroll: true,
                }),
        });
    };

    return (
        <>
            <Head title={`${program.name} — Discipleship`} />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-6">
                <Link
                    href="/discipleship"
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    All Discipleship
                </Link>

                <div className="glass-panel rounded-xl p-6">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div className="pl-10">
                            <h1 className="text-xl font-semibold text-foreground">
                                {program.name}
                            </h1>
                            {program.description && (
                                <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                                    {program.description}
                                </p>
                            )}
                        </div>
                        <Button
                            onClick={() => setCreateOpen(true)}
                            className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Add Batch
                        </Button>
                    </div>
                </div>

                {batches.length === 0 ? (
                    <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-16 text-center">
                        <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-lime-brand/15">
                            <Users className="h-8 w-8 text-lime-brand" />
                        </div>
                        <h2 className="mb-2 text-lg font-semibold text-foreground">
                            No batches yet
                        </h2>
                        <p className="mb-6 max-w-sm text-sm text-muted-foreground">
                            Create the first batch of this program to begin
                            enrolling participants.
                        </p>
                        <Button
                            onClick={() => setCreateOpen(true)}
                            className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Add Batch
                        </Button>
                    </div>
                ) : (
                    <div className="flex flex-col gap-3">
                        {batches.map((batch) => (
                            <div
                                key={batch.id}
                                className="glass-panel flex items-center justify-between gap-3 rounded-xl p-5"
                            >
                                <div className="flex flex-1 flex-col gap-2">
                                    <span className="text-sm font-semibold text-foreground">
                                        {batch.name}
                                    </span>
                                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                        <span className="inline-flex items-center gap-1.5">
                                            <CalendarDays className="h-3 w-3" />
                                            Starts {formatDate(batch.start_date)}
                                        </span>
                                    </div>
                                </div>

                                <div className="flex shrink-0 items-center gap-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setEditTarget(batch)}
                                        aria-label={`Edit ${batch.name}`}
                                    >
                                        <Pencil className="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => deleteBatch(batch)}
                                        className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        aria-label={`Delete ${batch.name}`}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {dialog}

            {createOpen && (
                <BatchFormDialog
                    mode="create"
                    programSlug={program.slug}
                    open={createOpen}
                    onOpenChange={setCreateOpen}
                />
            )}

            {editTarget !== null && (
                <BatchFormDialog
                    mode="edit"
                    programSlug={program.slug}
                    batch={editTarget}
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
    programSlug: string;
    batch?: BatchRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function BatchFormDialog({
    mode,
    programSlug,
    batch,
    open,
    onOpenChange,
}: FormProps) {
    const isEdit = mode === 'edit';

    const form = useForm({
        name: batch?.name ?? '',
        start_date: batch?.start_date ?? '',
    });

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();

        const onSuccess = () => {
            form.reset();
            onOpenChange(false);
        };

        if (isEdit && batch) {
            form.put(`/discipleship/batches/${batch.id}`, {
                onSuccess,
                preserveScroll: true,
            });
        } else {
            form.post(`/discipleship/programs/${programSlug}/batches`, {
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
                        {isEdit ? 'Edit Batch' : 'Add Batch'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEdit
                            ? 'Update the details of this batch.'
                            : 'Create a new batch of this program.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={onSubmit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="batch-name">
                            Batch Name{' '}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="batch-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="e.g. Spring 2026 Cohort"
                            autoFocus
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="batch-start-date">
                            Start Date{' '}
                            <span className="text-destructive">*</span>
                        </Label>
                        <DatePicker
                            id="batch-start-date"
                            value={form.data.start_date}
                            onChange={(v) =>
                                form.setData('start_date', v)
                            }
                            placeholder="Pick a start date"
                        />
                        <InputError message={form.errors.start_date} />
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
                                  : 'Create Batch'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

DiscipleshipShow.layout = {
    breadcrumbs: [
        { title: 'Discipleship', href: '/discipleship' },
        { title: 'Program', href: '#' },
    ],
};
