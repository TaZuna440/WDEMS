import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    Download,
    Info,
    Pencil,
    Plus,
    Search,
    Trash2,
    UserMinus,
    UserPlus,
    Users,
    X,
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

type ProgressRow = {
    attended: number;
    applicable: number;
    percentage: number | null;
    required_sessions: number | null;
    is_complete: boolean;
};

type EnrollmentRow = {
    id: number;
    participant_id: number;
    full_name: string | null;
    email: string | null;
    contact_number: string | null;
    batch_id: number | null;
    batch_name: string | null;
    status: string;
    status_label: string;
    enrolled_at: string | null;
    notes: string | null;
    progress: ProgressRow;
};

type OverviewRow = {
    total: number;
    completed: number;
    in_progress: number;
    not_started: number;
    withdrawn: number;
    on_hold: number;
    completion_rate: number | null;
};

type Filters = {
    q: string;
    status: string;
    batch: string;
};

type Props = {
    program: ProgramData;
    batches: BatchRow[];
    batches_count: number;
    enrollments: EnrollmentRow[];
    enrollments_count: number;
    overview: OverviewRow;
    counts: Record<string, number>;
    filters: Filters;
};

const STATUS_STYLES: Record<string, string> = {
    enrolled:
        'bg-lime-brand/15 text-lime-700 border-lime-brand/30 dark:text-lime-brand',
    in_progress:
        'bg-accent/15 text-teal-700 border-accent/30 dark:text-accent',
    completed:
        'bg-green-500/15 text-green-700 border-green-500/30 dark:text-green-400',
    withdrawn: 'bg-destructive/10 text-destructive border-destructive/30',
    on_hold:
        'bg-[#13253B]/10 text-[#13253B] border-[#13253B]/30 dark:bg-yellow-500/10 dark:text-yellow-500 dark:border-yellow-500/30',
};

const STATUS_FILTERS: { value: string; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'enrolled', label: 'Enrolled' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'completed', label: 'Completed' },
    { value: 'on_hold', label: 'On Hold' },
    { value: 'withdrawn', label: 'Withdrawn' },
];

const NO_BATCH = '__none__';

function formatDate(iso: string): string {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function ProgressCell({ progress }: { progress: ProgressRow }) {
    if (progress.applicable === 0) {
        return (
            <span className="text-xs italic text-muted-foreground/60">
                No sessions yet
            </span>
        );
    }

    const pct = progress.percentage ?? 0;

    return (
        <div className="flex items-center gap-2">
            <div className="h-1.5 w-16 overflow-hidden rounded-full bg-secondary">
                <div
                    className="h-full rounded-full bg-lime-brand"
                    style={{ width: `${pct}%` }}
                />
            </div>
            <span className="text-xs tabular-nums text-muted-foreground">
                {progress.attended}/{progress.applicable}
            </span>
            <span className="text-xs tabular-nums text-muted-foreground">
                {pct.toFixed(0)}%
            </span>
        </div>
    );
}

function CompletionDonut({ rate }: { rate: number | null }) {
    const pct = rate ?? 0;
    const radius = 28;
    const circumference = 2 * Math.PI * radius;
    const dash = (pct / 100) * circumference;

    return (
        <div className="relative h-20 w-20">
            <svg viewBox="0 0 64 64" className="h-full w-full -rotate-90">
                <circle
                    cx="32"
                    cy="32"
                    r={radius}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="6"
                    className="text-white/5"
                />
                <circle
                    cx="32"
                    cy="32"
                    r={radius}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="6"
                    strokeLinecap="round"
                    strokeDasharray={`${dash} ${circumference}`}
                    className="text-lime-brand"
                />
            </svg>
            <div className="absolute inset-0 flex items-center justify-center">
                <span className="text-sm font-semibold text-foreground">
                    {pct.toFixed(0)}%
                </span>
            </div>
        </div>
    );
}

export default function DiscipleshipShow({
    program,
    batches,
    batches_count,
    enrollments,
    enrollments_count,
    overview,
    counts,
    filters,
}: Props) {
    const [localSearch, setLocalSearch] = useState(filters.q);
    const [createBatchOpen, setCreateBatchOpen] = useState(false);
    const [editBatchTarget, setEditBatchTarget] = useState<BatchRow | null>(null);
    const [addEnrollOpen, setAddEnrollOpen] = useState(false);
    const [editEnrollTarget, setEditEnrollTarget] = useState<EnrollmentRow | null>(null);
    const [withdrawTarget, setWithdrawTarget] = useState<EnrollmentRow | null>(null);
    const [detailsOpen, setDetailsOpen] = useState(false);
    const { dialog, openConfirm } = useConfirmDialog();

    const baseUrl = `/discipleship/programs/${program.slug}`;

    /**
     * Build the query string for a partial reload. Preserves the
     * filters that are not being overridden.
     */
    const buildQuery = (overrides: Record<string, string> = {}) => ({
        ...(filters.q !== '' ? { q: filters.q } : {}),
        ...(filters.status !== 'all' ? { status: filters.status } : {}),
        ...(filters.batch !== 'all' ? { batch: filters.batch } : {}),
        ...overrides,
    });

    // Debounced search — 300ms, matches the running-event
    // attendance page.
    useEffect(() => {
        const timer = window.setTimeout(() => {
            if (localSearch === filters.q) return;

            router.get(
                baseUrl,
                buildQuery({
                    q: localSearch,
                    status: filters.status,
                    batch: filters.batch,
                }),
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                    only: ['enrollments', 'enrollments_count', 'counts', 'filters'],
                },
            );
        }, 300);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [localSearch]);

    const applyFilter = (key: 'status' | 'batch', value: string) => {
        router.get(
            baseUrl,
            buildQuery({
                q: filters.q,
                status: key === 'status' ? value : filters.status,
                batch: key === 'batch' ? value : filters.batch,
            }),
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ['enrollments', 'enrollments_count', 'counts', 'filters'],
            },
        );
    };

    const clearFilters = () => {
        setLocalSearch('');
        router.get(
            baseUrl,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ['enrollments', 'enrollments_count', 'counts', 'filters'],
            },
        );
    };

    const hasActiveFilters =
        filters.q !== '' || filters.status !== 'all' || filters.batch !== 'all';

    // The CSV export route accepts the same q/status/batch filters
    // as show(). Build the href once so the anchor and any future
    // "download" button share the same URL.
    const csvHref = `/discipleship/programs/${program.slug}/enrollments/export${buildQueryString(filters)}`;

    const deleteBatch = (batch: BatchRow) => {
        openConfirm({
            variant: 'danger',
            title: `Delete "${batch.name}"?`,
            description: `This batch starting ${formatDate(batch.start_date)} will be permanently removed. Enrollments pointing at this batch will have their batch pointer cleared.`,
            confirmLabel: 'Delete',
            onConfirm: () =>
                router.delete(`/discipleship/batches/${batch.id}`, {
                    preserveScroll: true,
                }),
        });
    };

    const withdrawEnrollment = (enrollment: EnrollmentRow) => {
        setWithdrawTarget(enrollment);
    };

    return (
        <>
            <Head title={`${program.name} — Discipleship`} />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
                <Link
                    href="/discipleship"
                    className="inline-flex w-fit items-center gap-2 text-sm text-on-image-muted transition-colors hover:text-on-image"
                >
                    <ArrowLeft className="h-4 w-4" />
                    All Discipleship
                </Link>

                {/* Program header + overview + quick actions */}
                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="glass-panel rounded-xl p-6 lg:col-span-2">
                        <div className="pl-10">
                            <h1 className="text-xl font-semibold text-foreground">
                                {program.name}
                            </h1>
                            {program.description && (
                                <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                                    {program.description}
                                </p>
                            )}
                            <p className="mt-3 text-xs text-muted-foreground">
                                {batches_count}{' '}
                                {batches_count === 1 ? 'batch' : 'batches'}
                                {' · '}
                                {enrollments_count}{' '}
                                {enrollments_count === 1
                                    ? 'enrollment'
                                    : 'enrollments'}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-col gap-4">
                        <div className="glass-panel rounded-xl p-5">
                            <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                Program Overview
                            </p>
                            <div className="mt-3 flex items-center gap-4">
                                <CompletionDonut rate={overview.completion_rate} />
                                <div className="flex flex-1 flex-col gap-1 text-xs">
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">
                                            Total
                                        </span>
                                        <span className="tabular-nums text-foreground">
                                            {overview.total}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">
                                            Completed
                                        </span>
                                        <span className="tabular-nums text-foreground">
                                            {overview.completed}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">
                                            In Progress
                                        </span>
                                        <span className="tabular-nums text-foreground">
                                            {overview.in_progress}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">
                                            Not Started
                                        </span>
                                        <span className="tabular-nums text-foreground">
                                            {overview.not_started}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Quick Actions */}
                        <div className="glass-panel rounded-xl p-5">
                            <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                Quick Actions
                            </p>
                            <div className="mt-3 flex flex-col gap-2">
                                <button
                                    type="button"
                                    onClick={() => setDetailsOpen(true)}
                                    className="flex items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm text-foreground transition-colors hover:bg-secondary/60"
                                >
                                    <Info className="h-4 w-4 text-muted-foreground" />
                                    View program details
                                </button>
                                <a
                                    href="#batches"
                                    className="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-foreground transition-colors hover:bg-secondary/60"
                                >
                                    <CalendarDays className="h-4 w-4 text-muted-foreground" />
                                    Manage batches
                                </a>
                                <button
                                    type="button"
                                    onClick={() => setAddEnrollOpen(true)}
                                    className="flex items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm text-foreground transition-colors hover:bg-secondary/60"
                                >
                                    <UserPlus className="h-4 w-4 text-muted-foreground" />
                                    Add enrollment
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Batches */}
                <section id="batches" className="flex flex-col gap-3 scroll-mt-6">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-on-image-muted">
                            Batches
                        </h2>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => setCreateBatchOpen(true)}
                        >
                            <Plus className="mr-1.5 h-3.5 w-3.5" />
                            Add batch
                        </Button>
                    </div>

                    {batches.length === 0 ? (
                        <p className="glass-panel rounded-xl p-6 text-center text-sm text-muted-foreground">
                            No batches yet.
                        </p>
                    ) : (
                        <div className="flex flex-col gap-2">
                            {batches.map((batch) => (
                                <div
                                    key={batch.id}
                                    className="glass-panel flex items-center justify-between gap-3 rounded-xl px-5 py-3"
                                >
                                    <div className="flex flex-1 items-center gap-3">
                                        <CalendarDays className="h-4 w-4 shrink-0 text-muted-foreground" />
                                        <Link
                                            href={`/discipleship/batches/${batch.id}`}
                                            className="text-sm font-medium text-foreground hover:text-lime-brand"
                                        >
                                            {batch.name}
                                        </Link>
                                        <span className="text-xs text-muted-foreground">
                                            Starts {formatDate(batch.start_date)}
                                        </span>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-1">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setEditBatchTarget(batch)}
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
                </section>

                {/* Enrollments */}
                <section className="flex flex-col gap-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-on-image-muted">
                            Enrollments
                        </h2>
                        <div className="flex items-center gap-2">
                            <a
                                href={csvHref}
                                className="inline-flex h-8 items-center rounded-md border border-border bg-card/80 px-3 text-xs font-medium text-foreground shadow-xs backdrop-blur-sm transition-colors hover:bg-card"
                            >
                                <Download className="mr-1.5 h-3.5 w-3.5" />
                                Export CSV
                            </a>
                            <Button
                                type="button"
                                size="sm"
                                onClick={() => setAddEnrollOpen(true)}
                                className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                            >
                                <UserPlus className="mr-1.5 h-3.5 w-3.5" />
                                Add Enrollment
                            </Button>
                        </div>
                    </div>

                    {/* Filter bar */}
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div className="relative w-full lg:max-w-sm">
                            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={localSearch}
                                onChange={(e) => setLocalSearch(e.target.value)}
                                placeholder="Search name, email, or phone…"
                                className="pl-9"
                            />
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            {STATUS_FILTERS.map((f) => (
                                <button
                                    key={f.value}
                                    type="button"
                                    onClick={() => applyFilter('status', f.value)}
                                    className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition-colors ${
                                        filters.status === f.value
                                            ? 'border-lime-brand/40 bg-lime-brand/25 text-lime-700 dark:bg-lime-brand/20 dark:text-lime-brand'
                                            : 'border-border bg-card/70 text-foreground hover:bg-card'
                                    }`}
                                >
                                    {f.label}
                                    <span
                                        className={
                                            filters.status === f.value
                                                ? 'text-lime-brand'
                                                : 'text-muted-foreground/60'
                                        }
                                    >
                                        ({counts[f.value] ?? 0})
                                    </span>
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <Select
                            value={filters.batch}
                            onValueChange={(v) => applyFilter('batch', v)}
                        >
                            <SelectTrigger className="h-8 w-48 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All batches</SelectItem>
                                <SelectItem value="unassigned">Unassigned</SelectItem>
                                {batches.map((b) => (
                                    <SelectItem key={b.id} value={String(b.id)}>
                                        {b.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        {hasActiveFilters && (
                            <button
                                type="button"
                                onClick={clearFilters}
                                className="inline-flex items-center gap-1 text-xs text-muted-foreground transition-colors hover:text-foreground"
                            >
                                <X className="h-3 w-3" />
                                Clear filters
                            </button>
                        )}

                        <span className="text-xs text-on-image-muted">
                            {enrollments_count}{' '}
                            {enrollments_count === 1 ? 'result' : 'results'}
                        </span>
                    </div>

                    {enrollments.length === 0 ? (
                        <div className="glass-panel flex flex-col items-center justify-center rounded-xl p-12 text-center">
                            <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-lime-brand/15">
                                <Users className="h-7 w-7 text-lime-brand" />
                            </div>
                            <p className="mb-1 text-sm font-medium text-foreground">
                                {hasActiveFilters
                                    ? 'No matches'
                                    : 'No one is enrolled yet'}
                            </p>
                            <p className="mb-6 max-w-sm text-xs text-muted-foreground">
                                {hasActiveFilters
                                    ? 'Try a different search or filter.'
                                    : 'Enroll the first participant to begin tracking their progress through this program.'}
                            </p>
                            {!hasActiveFilters && (
                                <Button
                                    type="button"
                                    onClick={() => setAddEnrollOpen(true)}
                                    className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                                >
                                    <UserPlus className="mr-2 h-4 w-4" />
                                    Add Enrollment
                                </Button>
                            )}
                        </div>
                    ) : (
                        <div className="glass-panel overflow-hidden rounded-xl">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-border text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                            <th className="px-4 py-3">Participant</th>
                                            <th className="px-4 py-3">Contact</th>
                                            <th className="px-4 py-3">Batch</th>
                                            <th className="px-4 py-3">Status</th>
                                            <th className="px-4 py-3">Progress</th>
                                            <th className="px-4 py-3">Enrolled</th>
                                            <th className="px-4 py-3 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {enrollments.map((row) => (
                                            <tr
                                                key={row.id}
                                                className="border-b border-border last:border-b-0 hover:bg-white/[0.02]"
                                            >
                                                <td className="px-4 py-3 font-medium text-foreground">
                                                    {row.full_name ?? '—'}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {row.email ?? row.contact_number ?? '—'}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {row.batch_name ?? '—'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span
                                                        className={`inline-flex rounded-full border px-2 py-0.5 text-xs font-medium ${
                                                            STATUS_STYLES[row.status] ?? ''
                                                        }`}
                                                    >
                                                        {row.status_label}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <ProgressCell progress={row.progress} />
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {row.enrolled_at
                                                        ? formatDate(row.enrolled_at)
                                                        : '—'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                setEditEnrollTarget(row)
                                                            }
                                                            aria-label={`Edit enrollment for ${row.full_name ?? 'participant'}`}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                withdrawEnrollment(row)
                                                            }
                                                            disabled={row.status === 'withdrawn'}
                                                            className="text-destructive hover:bg-destructive/10 hover:text-destructive disabled:opacity-40"
                                                            aria-label={`Withdraw ${row.full_name ?? 'participant'}`}
                                                        >
                                                            <UserMinus className="h-4 w-4" />
                                                        </Button>
                                                    </div>
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

            <ProgramDetailsDialog
                program={program}
                batches={batches}
                overview={overview}
                open={detailsOpen}
                onOpenChange={setDetailsOpen}
            />

            {createBatchOpen && (
                <BatchFormDialog
                    mode="create"
                    programSlug={program.slug}
                    open={createBatchOpen}
                    onOpenChange={setCreateBatchOpen}
                />
            )}

            {editBatchTarget !== null && (
                <BatchFormDialog
                    mode="edit"
                    programSlug={program.slug}
                    batch={editBatchTarget}
                    open={editBatchTarget !== null}
                    onOpenChange={(open) => {
                        if (!open) setEditBatchTarget(null);
                    }}
                />
            )}

            {addEnrollOpen && (
                <AddEnrollmentDialog
                    programSlug={program.slug}
                    batches={batches}
                    open={addEnrollOpen}
                    onOpenChange={setAddEnrollOpen}
                />
            )}

            {editEnrollTarget !== null && (
                <EditEnrollmentDialog
                    enrollment={editEnrollTarget}
                    batches={batches}
                    open={editEnrollTarget !== null}
                    onOpenChange={(open) => {
                        if (!open) setEditEnrollTarget(null);
                    }}
                />
            )}

            {withdrawTarget !== null && (
                <WithdrawDialog
                    enrollment={withdrawTarget}
                    open={withdrawTarget !== null}
                    onOpenChange={(open) => {
                        if (!open) setWithdrawTarget(null);
                    }}
                />
            )}
        </>
    );
}

/**
 * Build the CSV export query string from a Filters object. Only
 * includes non-default values so the URL stays clean.
 */
function buildQueryString(filters: Filters): string {
    const parts: string[] = [];

    if (filters.q !== '') {
        parts.push(`q=${encodeURIComponent(filters.q)}`);
    }
    if (filters.status !== 'all') {
        parts.push(`status=${encodeURIComponent(filters.status)}`);
    }
    if (filters.batch !== 'all') {
        parts.push(`batch=${encodeURIComponent(filters.batch)}`);
    }

    return parts.length > 0 ? `?${parts.join('&')}` : '';
}

// -----------------------------------------------------------------
// Program Details dialog
// -----------------------------------------------------------------

type DetailsProps = {
    program: ProgramData;
    batches: BatchRow[];
    overview: OverviewRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function ProgramDetailsDialog({
    program,
    batches,
    overview,
    open,
    onOpenChange,
}: DetailsProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>{program.name}</DialogTitle>
                    <DialogDescription>
                        Program details and completion criteria.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    {program.description && (
                        <div className="grid gap-1">
                            <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                Description
                            </p>
                            <p className="text-sm text-foreground">
                                {program.description}
                            </p>
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-1">
                            <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                Batches
                            </p>
                            <p className="text-sm text-foreground">
                                {batches.length}
                            </p>
                        </div>
                        <div className="grid gap-1">
                            <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                Enrollments
                            </p>
                            <p className="text-sm text-foreground">
                                {overview.total}
                            </p>
                        </div>
                    </div>

                    <div className="rounded-md border border-border bg-card/40 p-3">
                        <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            Completion criteria
                        </p>
                        <p className="mt-2 text-sm text-foreground">
                            Required sessions:{' '}
                            <span className="font-medium">
                                {overview.total > 0
                                    ? (enrollmentRequiredText(overview))
                                    : 'Not configured'}
                            </span>
                        </p>
                        <p className="mt-2 text-xs text-muted-foreground">
                            Completion criteria are configured by the
                            organizer. Required session count and any
                            additional checklist are recorded on the
                            program record.
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Placeholder for the completion criteria display. Returns a
 * reasonable default text based on whether any required sessions
 * are configured at the program level. The current payload does
 * not carry required_sessions directly on the program (it is
 * per-enrollment in the progress rows), so this reads as
 * informative until the program payload is extended.
 */
function enrollmentRequiredText(overview: OverviewRow): string {
    if (overview.completion_rate === null) return 'Not configured';
    return 'Configured per enrollment';
}

// -----------------------------------------------------------------
// Batch dialog
// -----------------------------------------------------------------

type FormMode = 'create' | 'edit';

type BatchFormProps = {
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
}: BatchFormProps) {
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
                            onChange={(v) => form.setData('start_date', v)}
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

// -----------------------------------------------------------------
// Participant search types
// -----------------------------------------------------------------

type ParticipantMatch = {
    id: number;
    full_name: string;
    email: string | null;
    contact_number: string | null;
    already_enrolled: boolean;
};

// -----------------------------------------------------------------
// Add Enrollment dialog
// -----------------------------------------------------------------

type AddEnrollProps = {
    programSlug: string;
    batches: BatchRow[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function AddEnrollmentDialog({
    programSlug,
    batches,
    open,
    onOpenChange,
}: AddEnrollProps) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<ParticipantMatch[]>([]);
    const [loading, setLoading] = useState(false);
    const [selected, setSelected] = useState<ParticipantMatch | null>(null);

    const form = useForm({
        participant_id: '' as string | number,
        batch_id: NO_BATCH as string,
        enrolled_at: '' as string,
        notes: '',
    });

    useEffect(() => {
        if (!open || selected !== null) return;

        const timer = window.setTimeout(async () => {
            setLoading(true);
            try {
                const res = await fetch(
                    `/discipleship/programs/${programSlug}/participant-search?q=${encodeURIComponent(query)}`,
                    { headers: { Accept: 'application/json' } },
                );
                if (!res.ok) {
                    setResults([]);
                    return;
                }
                const data = (await res.json()) as {
                    participants: ParticipantMatch[];
                };
                setResults(data.participants);
            } catch {
                setResults([]);
            } finally {
                setLoading(false);
            }
        }, 250);

        return () => window.clearTimeout(timer);
    }, [query, open, programSlug, selected]);

    useEffect(() => {
        if (!open) {
            setQuery('');
            setResults([]);
            setSelected(null);
            form.reset();
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const pick = (p: ParticipantMatch) => {
        setSelected(p);
        form.setData('participant_id', p.id);
    };

    const clearPick = () => {
        setSelected(null);
        form.setData('participant_id', '');
        setQuery('');
    };

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            participant_id: Number(data.participant_id),
            batch_id:
                data.batch_id === NO_BATCH ? null : Number(data.batch_id),
            enrolled_at: data.enrolled_at === '' ? null : data.enrolled_at,
        }));
        form.post(`/discipleship/programs/${programSlug}/enrollments`, {
            onSuccess: () => {
                form.reset();
                setSelected(null);
                setQuery('');
                onOpenChange(false);
            },
            preserveScroll: true,
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Add Enrollment</DialogTitle>
                    <DialogDescription>
                        Search for a participant and enroll them in this
                        program. Each participant can hold one enrollment
                        per program.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={onSubmit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label>
                            Participant{' '}
                            <span className="text-destructive">*</span>
                        </Label>

                        {selected !== null ? (
                            <div className="flex items-center justify-between rounded-md border border-lime-brand/30 bg-lime-brand/5 px-3 py-2 text-sm">
                                <span className="font-medium text-foreground">
                                    {selected.full_name}
                                </span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={clearPick}
                                    className="h-7 text-xs"
                                >
                                    Change
                                </Button>
                            </div>
                        ) : (
                            <>
                                <div className="relative">
                                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        value={query}
                                        onChange={(e) => setQuery(e.target.value)}
                                        placeholder="Search name, email, or phone…"
                                        className="pl-9"
                                        autoFocus
                                    />
                                </div>

                                <div className="max-h-56 overflow-y-auto rounded-md border border-border">
                                    {loading ? (
                                        <p className="px-3 py-2 text-xs text-muted-foreground">
                                            Searching…
                                        </p>
                                    ) : results.length === 0 ? (
                                        <p className="px-3 py-2 text-xs text-muted-foreground">
                                            {query.trim() === ''
                                                ? 'No participants found.'
                                                : 'No matches. Try a different search.'}
                                        </p>
                                    ) : (
                                        <div className="flex flex-col">
                                            {results.map((p) => (
                                                <button
                                                    key={p.id}
                                                    type="button"
                                                    onClick={() => !p.already_enrolled && pick(p)}
                                                    disabled={p.already_enrolled}
                                                    className={`flex items-center justify-between gap-3 border-b border-border px-3 py-2 text-left text-sm last:border-b-0 ${
                                                        p.already_enrolled
                                                            ? 'cursor-not-allowed opacity-50'
                                                            : 'hover:bg-secondary/60'
                                                    }`}
                                                >
                                                    <div className="flex flex-col">
                                                        <span className="font-medium text-foreground">
                                                            {p.full_name}
                                                        </span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {p.email ?? p.contact_number ?? '—'}
                                                        </span>
                                                    </div>
                                                    {p.already_enrolled && (
                                                        <span className="shrink-0 text-xs italic text-muted-foreground">
                                                            Already enrolled
                                                        </span>
                                                    )}
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                <InputError message={form.errors.participant_id} />
                            </>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="add-batch">Batch</Label>
                        <Select
                            value={form.data.batch_id}
                            onValueChange={(v) => form.setData('batch_id', v)}
                        >
                            <SelectTrigger id="add-batch" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NO_BATCH}>No batch yet</SelectItem>
                                {batches.map((b) => (
                                    <SelectItem key={b.id} value={String(b.id)}>
                                        {b.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.batch_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="add-enrolled-at">Enrollment date</Label>
                        <DatePicker
                            id="add-enrolled-at"
                            value={form.data.enrolled_at}
                            onChange={(v) => form.setData('enrolled_at', v)}
                            placeholder="Defaults to today"
                        />
                        <p className="text-xs text-muted-foreground">
                            Leave empty to use today's date.
                        </p>
                        <InputError message={form.errors.enrolled_at} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="add-notes">Notes</Label>
                        <textarea
                            id="add-notes"
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            rows={2}
                            className="border-input bg-input placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-16 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                            placeholder="Optional. Any notes about this enrollment."
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
                            disabled={form.processing || selected === null}
                            className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                        >
                            {form.processing ? 'Enrolling…' : 'Enroll'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// -----------------------------------------------------------------
// Edit Enrollment dialog
// -----------------------------------------------------------------

type EditEnrollProps = {
    enrollment: EnrollmentRow;
    batches: BatchRow[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function EditEnrollmentDialog({
    enrollment,
    batches,
    open,
    onOpenChange,
}: EditEnrollProps) {
    const form = useForm({
        status: enrollment.status,
        batch_id:
            enrollment.batch_id !== null
                ? String(enrollment.batch_id)
                : (NO_BATCH as string),
        notes: enrollment.notes ?? '',
    });

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            batch_id:
                data.batch_id === NO_BATCH ? null : Number(data.batch_id),
        }));
        form.put(`/discipleship/enrollments/${enrollment.id}`, {
            onSuccess: () => onOpenChange(false),
            preserveScroll: true,
        });
    };

    const STATUS_OPTIONS = [
        { value: 'enrolled', label: 'Enrolled' },
        { value: 'in_progress', label: 'In Progress' },
        { value: 'completed', label: 'Completed' },
        { value: 'on_hold', label: 'On Hold' },
        { value: 'withdrawn', label: 'Withdrawn' },
    ];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit Enrollment</DialogTitle>
                    <DialogDescription>
                        {enrollment.full_name ?? 'This participant'}. Update
                        status, batch, or notes.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={onSubmit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="edit-status">Status</Label>
                        <Select
                            value={form.data.status}
                            onValueChange={(v) => form.setData('status', v)}
                        >
                            <SelectTrigger id="edit-status" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUS_OPTIONS.map((o) => (
                                    <SelectItem key={o.value} value={o.value}>
                                        {o.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.status} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="edit-batch">Batch</Label>
                        <Select
                            value={form.data.batch_id}
                            onValueChange={(v) => form.setData('batch_id', v)}
                        >
                            <SelectTrigger id="edit-batch" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NO_BATCH}>No batch</SelectItem>
                                {batches.map((b) => (
                                    <SelectItem key={b.id} value={String(b.id)}>
                                        {b.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.batch_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="edit-notes">Notes</Label>
                        <textarea
                            id="edit-notes"
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            rows={3}
                            className="border-input bg-input placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-16 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                            placeholder="Optional notes about this enrollment"
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
                            {form.processing ? 'Saving…' : 'Save Changes'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// -----------------------------------------------------------------
// Withdraw confirm dialog
// -----------------------------------------------------------------

type WithdrawProps = {
    enrollment: EnrollmentRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function WithdrawDialog({ enrollment, open, onOpenChange }: WithdrawProps) {
    const [processing, setProcessing] = useState(false);

    const withdraw = () => {
        setProcessing(true);
        router.delete(`/discipleship/enrollments/${enrollment.id}`, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-sm">
                <DialogHeader>
                    <DialogTitle>
                        Withdraw {enrollment.full_name ?? 'this participant'}?
                    </DialogTitle>
                    <DialogDescription>
                        Their enrollment status will be set to Withdrawn. The
                        enrollment record is kept for history — re-enrolling
                        later resets the same record.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={withdraw}
                        disabled={processing}
                    >
                        {processing ? 'Withdrawing…' : 'Withdraw'}
                    </Button>
                </DialogFooter>
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
