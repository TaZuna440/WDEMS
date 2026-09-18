import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    AlertCircle,
    ArrowLeft,
    Check,
    Copy,
    ExternalLink,
    FileSpreadsheet,
    Link2,
    Loader2,
    Pencil,
    Plus,
    Save,
    Trash2,
    X,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import QuestionEditorDialog, {
    type QuestionDraft,
} from '@/components/question-editor-dialog';

type Setup = {
    id: number;
    form_url: string | null;
    form_edit_url: string | null;
    status: string;
    created_at: string | null;
    google_sheet_id: string | null;
    sheet_url: string | null;
    sheet_linked_at: string | null;
};

type Question = {
    id: string;
    title: string;
    type: string;
    type_label: string;
    required: boolean;
    options: string[];
};

type DraftQuestion = QuestionDraft & {
    tempId: string;
    id: string | null;
};

type HistoryEntry = {
    id: number;
    action: string;
    item_title: string | null;
    user_name: string | null;
    google_email_used: string | null;
    changes: Record<string, number> | null;
    created_at: string | null;
};

type EventData = {
    id: number;
    event_name: string;
    status: string;
    status_label: string;
};

type Props = {
    event: EventData;
    setup: Setup | null;
    questions: Question[];
    questions_error: string | null;
    can_edit_questions: boolean;
    can_create: boolean;
    can_create_reason: string | null;
    history: HistoryEntry[];
    link_script: string | null;
};

const TYPE_LABEL: Record<string, string> = {
    SHORT_ANSWER: 'Short answer',
    PARAGRAPH: 'Paragraph',
    MULTIPLE_CHOICE: 'Multiple choice',
    CHECKBOX: 'Checkbox',
    DROP_DOWN: 'Dropdown',
    RADIO: 'Multiple choice',
};

function buildDraft(questions: Question[]): DraftQuestion[] {
    return questions.map((q) => ({
        tempId: q.id,
        id: q.id,
        title: q.title,
        type: q.type,
        required: q.required,
        options: q.options,
    }));
}

function describeHistory(h: HistoryEntry): string {
    switch (h.action) {
        case 'form_created':
            return 'Created the registration form';
        case 'sheet_linked':
            return `Linked responses to a Sheet${h.item_title ? ` (${h.item_title})` : ''}`;
        case 'question_added':
            return `Added question "${h.item_title ?? ''}"`;
        case 'question_deleted':
            return `Removed question "${h.item_title ?? ''}"`;
        case 'questions_synced': {
            const c = h.changes ?? {};
            const added = c.created ?? 0;
            const updated = c.updated ?? 0;
            const removed = c.deleted ?? 0;
            const parts: string[] = [];
            if (added > 0) parts.push(`${added} added`);
            if (updated > 0) parts.push(`${updated} edited`);
            if (removed > 0) parts.push(`${removed} removed`);
            return parts.length > 0
                ? `Saved changes (${parts.join(', ')})`
                : 'Saved changes';
        }
        default:
            return h.action;
    }
}

function formatTime(iso: string | null): string {
    if (! iso) return '';
    const d = new Date(iso);
    return d.toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function RegistrationSetup({
    event,
    setup,
    questions,
    questions_error,
    can_edit_questions,
    can_create,
    can_create_reason,
    history,
    link_script,
}: Props) {
    const form = useForm({});
    const page = usePage<{ errors: Record<string, string> }>();
    const [draft, setDraft] = useState<DraftQuestion[]>(() => buildDraft(questions));
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [editingTempId, setEditingTempId] = useState<string | null>(null);
    const [addOpen, setAddOpen] = useState(false);

    const [verifying, setVerifying] = useState(false);
    const [copiedForm, setCopiedForm] = useState(false);
    const [copiedScript, setCopiedScript] = useState(false);
    const [copiedSheet, setCopiedSheet] = useState(false);

    useEffect(() => {
        setDraft(buildDraft(questions));
        setDirty(false);
    }, [questions]);

    const copyToClipboard = async (text: string, setter: (v: boolean) => void) => {
        if (! text) return;
        try {
            await navigator.clipboard.writeText(text);
            setter(true);
            window.setTimeout(() => setter(false), 2000);
        } catch {
            // Clipboard unavailable — silently fail
        }
    };

    const createForm = () => {
        form.post(`/events/${event.id}/registration/setup`, {
            preserveScroll: true,
        });
    };

    const verifySheet = () => {
        setVerifying(true);
        router.post(
            `/events/${event.id}/registration/setup/verify-sheet`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setVerifying(false),
            },
        );
    };

    const handleAdd = (data: QuestionDraft) => {
        setDraft((prev) => [
            ...prev,
            {
                ...data,
                tempId: `new-${Date.now()}-${Math.random().toString(36).slice(2)}`,
                id: null,
            },
        ]);
        setDirty(true);
    };

    const handleEdit = (tempId: string, data: QuestionDraft) => {
        setDraft((prev) =>
            prev.map((q) => (q.tempId === tempId ? { ...q, ...data } : q)),
        );
        setDirty(true);
    };

    const handleDelete = (tempId: string) => {
        setDraft((prev) => prev.filter((q) => q.tempId !== tempId));
        setDirty(true);
    };

    const handleCancel = () => {
        setDraft(buildDraft(questions));
        setDirty(false);
    };

    const handleSave = () => {
        setSaving(true);
        router.post(
            `/events/${event.id}/registration/setup/questions/sync`,
            {
                questions: draft.map((q) => ({
                    id: q.id,
                    title: q.title,
                    type: q.type,
                    required: q.required,
                    options: q.options,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const serverError = page.props.errors?.form;
    const questionError = page.props.errors?.question;
    const sheetError = page.props.errors?.sheet;

    const editingDraft = editingTempId
        ? draft.find((q) => q.tempId === editingTempId) ?? null
        : null;

    return (
        <>
            <Head title={`Registration Setup — ${event.event_name}`} />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-6 pb-28">
                <Link
                    href={`/events/${event.id}`}
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Event
                </Link>

                <div>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Registration Setup
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {event.event_name}
                    </p>
                </div>

                {serverError && (
                    <Alert variant="destructive">
                        <AlertDescription>{serverError}</AlertDescription>
                    </Alert>
                )}

                {questionError && (
                    <Alert variant="destructive">
                        <AlertDescription>{questionError}</AlertDescription>
                    </Alert>
                )}

                {setup === null ? (
                    <div className="glass-panel rounded-xl p-8 text-center">
                        <FileSpreadsheet className="mx-auto h-10 w-10 text-muted-foreground" />

                        <p className="mt-4 text-sm text-foreground">
                            No registration form has been created for this event.
                        </p>

                        {can_create ? (
                            <>
                                <p className="mt-2 text-xs text-muted-foreground">
                                    WDEMS will create a Google Form with default questions
                                    and publish it for this event.
                                </p>
                                <Button
                                    onClick={createForm}
                                    disabled={form.processing}
                                    className="mt-6 bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                                >
                                    {form.processing ? (
                                        <>
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            Creating form…
                                        </>
                                    ) : (
                                        'Create Registration Form'
                                    )}
                                </Button>
                            </>
                        ) : (
                            <p className="mt-4 text-xs text-destructive">
                                {can_create_reason}
                            </p>
                        )}
                    </div>
                ) : (
                    <>
                        {/* Form info panel */}
                        <div className="glass-panel flex flex-col gap-6 rounded-xl p-6">
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    Form URL
                                </p>
                                <p className="mt-1 break-all text-sm text-foreground">
                                    {setup.form_url}
                                </p>
                            </div>

                            <div>
                                <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    Status
                                </p>
                                <p className="mt-1 text-sm capitalize text-foreground">
                                    {setup.status}
                                </p>
                            </div>

                            {setup.form_url && (
                                <div className="flex flex-wrap gap-2">
                                    <Button asChild>
                                        <a
                                            href={setup.form_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <ExternalLink className="mr-2 h-4 w-4" />
                                            Open Form in Google
                                        </a>
                                    </Button>
                                    {setup.form_edit_url && (
                                        <Button variant="outline" asChild>
                                            <a
                                                href={setup.form_edit_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <Pencil className="mr-2 h-4 w-4" />
                                                Edit Form in Google
                                            </a>
                                        </Button>
                                    )}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            copyToClipboard(setup.form_url ?? '', setCopiedForm)
                                        }
                                    >
                                        {copiedForm ? (
                                            <Check className="mr-2 h-4 w-4" />
                                        ) : (
                                            <Copy className="mr-2 h-4 w-4" />
                                        )}
                                        {copiedForm ? 'Copied!' : 'Copy Registration Link'}
                                    </Button>
                                </div>
                            )}
                        </div>

                        {/* Sheet linkage panel */}
                        {setup.google_sheet_id === null ? (
                            <div className="glass-panel flex flex-col gap-4 rounded-xl p-6">
                                <div>
                                    <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                                        Link Responses to a Sheet
                                    </h2>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        Your Form is ready. Now create the Sheet where responses will land.
                                        This takes about 30 seconds and only needs to be done once per event.
                                    </p>
                                </div>

                                {sheetError && (
                                    <Alert variant="destructive">
                                        <AlertCircle className="h-4 w-4" />
                                        <AlertDescription>{sheetError}</AlertDescription>
                                    </Alert>
                                )}

                                <ol className="flex flex-col gap-4 text-sm">
                                    <li className="flex gap-3">
                                        <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/5 text-xs font-medium">
                                            1
                                        </span>
                                        <div className="flex-1">
                                            <p className="text-foreground">
                                                Open your Form in Google Forms (edit view)
                                            </p>
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                The edit view lets you access the Script Editor.
                                            </p>
                                            {setup.form_edit_url && (
                                                <Button asChild size="sm" variant="outline" className="mt-2">
                                                    <a
                                                        href={setup.form_edit_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        <Pencil className="mr-2 h-3 w-3" />
                                                        Open Edit View
                                                    </a>
                                                </Button>
                                            )}
                                        </div>
                                    </li>

                                    <li className="flex gap-3">
                                        <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/5 text-xs font-medium">
                                            2
                                        </span>
                                        <div className="flex-1">
                                            <p className="text-foreground">
                                                Click the <span className="font-mono">⋮</span> menu (top-right)
                                                {' '}→ <span className="font-medium">Script editor</span>
                                            </p>
                                        </div>
                                    </li>

                                    <li className="flex gap-3">
                                        <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/5 text-xs font-medium">
                                            3
                                        </span>
                                        <div className="flex-1">
                                            <p className="text-foreground">
                                                Paste this code into the editor:
                                            </p>
                                            <div className="relative mt-2">
                                                <pre className="overflow-x-auto rounded-md border border-white/10 bg-navy-900/60 p-3 text-xs text-foreground">
                                                    <code>{link_script}</code>
                                                </pre>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    className="absolute right-2 top-2"
                                                    onClick={() =>
                                                        copyToClipboard(link_script ?? '', setCopiedScript)
                                                    }
                                                >
                                                    {copiedScript ? (
                                                        <Check className="h-3 w-3" />
                                                    ) : (
                                                        <Copy className="h-3 w-3" />
                                                    )}
                                                </Button>
                                            </div>
                                        </div>
                                    </li>

                                    <li className="flex gap-3">
                                        <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/5 text-xs font-medium">
                                            4
                                        </span>
                                        <div className="flex-1">
                                            <p className="text-foreground">
                                                Click <span className="font-medium">Save 💾</span> then{' '}
                                                <span className="font-medium">Run ▶</span>
                                            </p>
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                Google will ask for permission — click "Advanced" → "Go to
                                                (unsafe)" → Allow.
                                            </p>
                                        </div>
                                    </li>

                                    <li className="flex gap-3">
                                        <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/5 text-xs font-medium">
                                            5
                                        </span>
                                        <div className="flex-1">
                                            <p className="text-foreground">
                                                Come back here and verify the link
                                            </p>
                                        </div>
                                    </li>
                                </ol>

                                <div className="flex justify-end border-t border-white/5 pt-4">
                                    <Button
                                        type="button"
                                        onClick={verifySheet}
                                        disabled={verifying}
                                        className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                                    >
                                        {verifying ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Verifying…
                                            </>
                                        ) : (
                                            <>
                                                <Link2 className="mr-2 h-4 w-4" />
                                                Verify Sheet Link
                                            </>
                                        )}
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <div className="glass-panel flex flex-col gap-4 rounded-xl p-6">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                                            Linked Sheet
                                        </h2>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            Responses from this Form automatically land in this Google Sheet.
                                        </p>
                                    </div>
                                    <span className="rounded-full bg-lime-brand/20 px-2 py-0.5 text-xs font-medium text-lime-brand">
                                        Linked
                                    </span>
                                </div>

                                <div className="flex flex-col gap-1">
                                    <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                        Sheet URL
                                    </p>
                                    <p className="break-all text-sm text-foreground">
                                        {setup.sheet_url}
                                    </p>
                                </div>

                                {setup.sheet_linked_at && (
                                    <p className="text-xs text-muted-foreground">
                                        Linked {formatTime(setup.sheet_linked_at)}
                                    </p>
                                )}

                                {setup.sheet_url && (
                                    <div className="flex flex-wrap gap-2">
                                        <Button asChild size="sm" variant="outline">
                                            <a
                                                href={setup.sheet_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <ExternalLink className="mr-2 h-3 w-3" />
                                                Open Sheet
                                            </a>
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                copyToClipboard(setup.sheet_url ?? '', setCopiedSheet)
                                            }
                                        >
                                            {copiedSheet ? (
                                                <Check className="mr-2 h-3 w-3" />
                                            ) : (
                                                <Copy className="mr-2 h-3 w-3" />
                                            )}
                                            {copiedSheet ? 'Copied!' : 'Copy URL'}
                                        </Button>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Questions panel */}
                        <div className="glass-panel flex flex-col gap-4 rounded-xl p-6">
                            <div className="flex items-center justify-between gap-2">
                                <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                                    Questions
                                </h2>

                                {can_edit_questions ? (
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={() => setAddOpen(true)}
                                    >
                                        <Plus className="mr-1 h-4 w-4" />
                                        Add Question
                                    </Button>
                                ) : (
                                    <span className="text-xs text-muted-foreground">
                                        View only
                                    </span>
                                )}
                            </div>

                            {questions_error && (
                                <Alert variant="destructive">
                                    <AlertDescription>{questions_error}</AlertDescription>
                                </Alert>
                            )}

                            {!questions_error && draft.length === 0 && (
                                <p className="text-xs text-muted-foreground">
                                    No questions on this form yet.
                                </p>
                            )}

                            {draft.length > 0 && (
                                <ul className="flex flex-col">
                                    {draft.map((q, i) => (
                                        <li
                                            key={q.tempId}
                                            className="flex items-start justify-between gap-4 border-b border-white/5 py-3 last:border-b-0"
                                        >
                                            <div className="flex flex-1 flex-col">
                                                <span className="text-sm text-foreground">
                                                    {i + 1}. {q.title}
                                                    {q.required && (
                                                        <span className="ml-1 text-destructive">*</span>
                                                    )}
                                                    {q.id === null && (
                                                        <span className="ml-2 rounded bg-lime-brand/20 px-1.5 py-0.5 text-xs text-lime-brand">
                                                            New
                                                        </span>
                                                    )}
                                                </span>
                                                <span className="mt-0.5 text-xs text-muted-foreground">
                                                    {TYPE_LABEL[q.type] ?? q.type}
                                                    {q.options.length > 0 &&
                                                        ` · ${q.options.join(', ')}`}
                                                </span>
                                            </div>

                                            {can_edit_questions && (
                                                <div className="flex gap-1">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="icon"
                                                        className="h-8 w-8 shrink-0"
                                                        onClick={() => setEditingTempId(q.tempId)}
                                                        title="Edit question"
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="icon"
                                                        className="h-8 w-8 shrink-0 border-destructive/40 text-destructive hover:border-destructive hover:bg-destructive hover:text-white"
                                                        onClick={() => handleDelete(q.tempId)}
                                                        title="Remove question"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>

                        {/* History panel */}
                        <div className="glass-panel flex flex-col gap-4 rounded-xl p-6">
                            <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                                History
                            </h2>

                            {history.length === 0 ? (
                                <p className="text-xs text-muted-foreground">
                                    No changes recorded yet.
                                </p>
                            ) : (
                                <ul className="flex flex-col">
                                    {history.map((h) => (
                                        <li
                                            key={h.id}
                                            className="flex flex-col gap-0.5 border-b border-white/5 py-2 last:border-b-0"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <span className="text-sm text-foreground">
                                                    {describeHistory(h)}
                                                </span>
                                                <span className="shrink-0 text-xs text-muted-foreground">
                                                    {formatTime(h.created_at)}
                                                </span>
                                            </div>
                                            <span className="text-xs text-muted-foreground">
                                                by {h.user_name ?? '—'}
                                                {h.google_email_used
                                                    ? ` · ${h.google_email_used}`
                                                    : ''}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </>
                )}
            </div>

            {dirty && (
                <div className="fixed bottom-6 left-1/2 z-50 flex -translate-x-1/2 items-center gap-3 rounded-full border border-lime-brand/40 bg-navy-900/95 px-4 py-2 shadow-2xl backdrop-blur">
                    <span className="text-sm text-foreground">
                        You have unsaved changes.
                    </span>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={handleCancel}
                        disabled={saving}
                    >
                        <X className="mr-1 h-4 w-4" />
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        onClick={handleSave}
                        disabled={saving}
                        className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                    >
                        {saving ? (
                            <>
                                <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                                Saving…
                            </>
                        ) : (
                            <>
                                <Save className="mr-1 h-4 w-4" />
                                Save Changes
                            </>
                        )}
                    </Button>
                </div>
            )}

            <QuestionEditorDialog
                open={addOpen}
                onOpenChange={setAddOpen}
                mode="add"
                onSave={handleAdd}
            />

            <QuestionEditorDialog
                open={editingTempId !== null}
                onOpenChange={(open) => {
                    if (!open) setEditingTempId(null);
                }}
                mode="edit"
                initialValue={
                    editingDraft
                        ? {
                              title: editingDraft.title,
                              type: editingDraft.type,
                              required: editingDraft.required,
                              options: editingDraft.options,
                          }
                        : null
                }
                onSave={(data) => {
                    if (editingTempId) handleEdit(editingTempId, data);
                    setEditingTempId(null);
                }}
            />
        </>
    );
}

RegistrationSetup.layout = {
    breadcrumbs: [
        { title: 'Events', href: '/events' },
        { title: 'Registration Setup', href: '#' },
    ],
};
