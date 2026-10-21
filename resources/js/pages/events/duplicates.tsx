import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { AlertCircle, CheckCircle2, UserX, Edit3, Trash2 } from 'lucide-react';

type Person = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    birthday: string | null;
    provisional: boolean;
};

type HeldRegistration = {
    id: number;
    created_at: string;
    held_reason: string | null;
    participant: {
        id: number;
        first_name: string;
        middle_name: string | null;
        last_name: string;
        suffix: string | null;
        email: string | null;
        contact_number: string | null;
        birthday: string | null;
    };
    candidate: {
        id: number;
        created_at: string;
        participant: {
            id: number;
            first_name: string;
            middle_name: string | null;
            last_name: string;
            suffix: string | null;
            email: string | null;
            contact_number: string | null;
            birthday: string | null;
        };
    } | null;
};

type Props = {
    event: { id: number; event_name: string };
    pairs: { left: Person; right: Person; reasons: string[] }[];
    held_registrations?: HeldRegistration[];
    review_status?: string;
};

export default function Duplicates({
    event,
    pairs,
    held_registrations = [],
    review_status,
}: Props) {
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [reason, setReason] = useState('');
    const [search, setSearch] = useState('');
    const [activeTab, setActiveTab] = useState<'queue' | 'audit'>('queue');
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editForm, setEditForm] = useState<{
        first_name: string;
        middle_name: string;
        last_name: string;
        suffix: string;
        email: string;
        contact_number: string;
    }>({
        first_name: '',
        middle_name: '',
        last_name: '',
        suffix: '',
        email: '',
        contact_number: '',
    });

    const handleAction = (
        registrationId: number,
        action: string,
        extraData = {},
    ) => {
        if (
            action === 'remove' &&
            !confirm('Are you sure you want to remove this submission?')
        ) {
            return;
        }
        router.post(
            `/events/${event.id}/possible-duplicates/${registrationId}/resolve`,
            {
                action,
                reason,
                ...extraData,
            },
            {
                preserveScroll: true,
                onStart: () => {
                    setProcessing(true);
                    setErrors({});
                },
                onFinish: () => setProcessing(false),
                onError: setErrors,
                onSuccess: () => {
                    if (editingId === registrationId) {
                        setEditingId(null);
                    }
                },
            },
        );
    };

    const startEditing = (held: HeldRegistration) => {
        setEditingId(held.id);
        setEditForm({
            first_name: held.participant.first_name ?? '',
            middle_name: held.participant.middle_name ?? '',
            last_name: held.participant.last_name ?? '',
            suffix: held.participant.suffix ?? '',
            email: held.participant.email ?? '',
            contact_number: held.participant.contact_number ?? '',
        });
    };

    const rows = pairs.filter((p) =>
        `${p.left.name} ${p.right.name} ${p.reasons.join(' ')}`
            .toLowerCase()
            .includes(search.trim().toLowerCase()),
    );

    const heldRows = held_registrations.filter((h) => {
        const pName =
            `${h.participant.first_name} ${h.participant.middle_name ?? ''} ${h.participant.last_name}`.toLowerCase();
        const cName = h.candidate
            ? `${h.candidate.participant.first_name} ${h.candidate.participant.last_name}`.toLowerCase()
            : '';
        const query = search.trim().toLowerCase();
        return (
            pName.includes(query) ||
            cName.includes(query) ||
            (h.held_reason ?? '').toLowerCase().includes(query)
        );
    });

    return (
        <>
            <Head title={`Duplicate Management — ${event.event_name}`} />
            <main className="mx-auto max-w-5xl space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <Link
                            className="text-muted-foreground text-sm hover:underline"
                            href={`/events/${event.id}`}
                        >
                            &larr; Back to Event
                        </Link>
                        <h1 className="mt-1 text-2xl font-semibold">
                            Duplicate Management
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {event.event_name}
                        </p>
                    </div>
                </div>

                <div className="border-border flex border-b">
                    <button
                        type="button"
                        onClick={() => setActiveTab('queue')}
                        className={`border-b-2 px-4 py-2 text-sm font-medium transition-colors ${
                            activeTab === 'queue'
                                ? 'border-lime-brand dark:text-lime-brand text-lime-700'
                                : 'text-muted-foreground hover:text-foreground border-transparent'
                        }`}
                    >
                        Review Queue ({held_registrations.length})
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('audit')}
                        className={`border-b-2 px-4 py-2 text-sm font-medium transition-colors ${
                            activeTab === 'audit'
                                ? 'border-lime-brand dark:text-lime-brand text-lime-700'
                                : 'text-muted-foreground hover:text-foreground border-transparent'
                        }`}
                    >
                        Possible Duplicates Audit ({pairs.length})
                    </button>
                </div>

                {review_status && <p role="status">{review_status}</p>}
                {Object.entries(errors).map(([key, message]) => (
                    <p
                        key={key}
                        role="alert"
                        className="text-destructive text-sm"
                    >
                        {message}
                    </p>
                ))}
                <Input
                    aria-label="Review reason"
                    placeholder="Reason for review decision (optional)"
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                />
                <Input
                    aria-label="Search duplicates"
                    placeholder="Search names, contacts, or reasons…"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />

                {activeTab === 'queue' && (
                    <div className="space-y-4">
                        <div className="rounded-lg border border-yellow-500/20 bg-yellow-500/10 p-4 text-xs text-yellow-800 dark:text-yellow-300">
                            <strong>Held Registrations:</strong> These
                            submissions have matching contact info with minor
                            name variations (e.g. omitted middle name or
                            suffix). They are excluded from attendance counts
                            until you take action.
                        </div>

                        {heldRows.map((held) => (
                            <article
                                key={held.id}
                                className="border-border bg-card space-y-4 rounded-xl border p-5"
                            >
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <AlertCircle className="h-4 w-4 text-yellow-600 dark:text-yellow-400" />
                                        <span className="text-sm font-semibold">
                                            Submission #{held.id} Held &middot;
                                            Reason:{' '}
                                            {held.held_reason ??
                                                'Similar identity match'}
                                        </span>
                                    </div>
                                    <span className="text-muted-foreground text-xs">
                                        Submitted{' '}
                                        {new Date(
                                            held.created_at,
                                        ).toLocaleString()}
                                    </span>
                                </div>

                                <div className="bg-muted/40 grid gap-4 rounded-lg p-4 sm:grid-cols-2">
                                    <div>
                                        <p className="text-muted-foreground mb-1 text-xs font-semibold uppercase">
                                            New Submission (Held)
                                        </p>
                                        <p className="text-sm font-medium">
                                            {held.participant.first_name}{' '}
                                            {held.participant.middle_name}{' '}
                                            {held.participant.last_name}{' '}
                                            {held.participant.suffix}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {held.participant.email ??
                                                'No email'}{' '}
                                            &middot;{' '}
                                            {held.participant.contact_number ??
                                                'No phone'}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            DOB:{' '}
                                            {held.participant.birthday ??
                                                'Not recorded'}
                                        </p>
                                    </div>

                                    <div>
                                        <p className="text-muted-foreground mb-1 text-xs font-semibold uppercase">
                                            Candidate Existing Registration
                                        </p>
                                        {held.candidate ? (
                                            <>
                                                <p className="text-sm font-medium">
                                                    {
                                                        held.candidate
                                                            .participant
                                                            .first_name
                                                    }{' '}
                                                    {
                                                        held.candidate
                                                            .participant
                                                            .middle_name
                                                    }{' '}
                                                    {
                                                        held.candidate
                                                            .participant
                                                            .last_name
                                                    }{' '}
                                                    {
                                                        held.candidate
                                                            .participant.suffix
                                                    }
                                                    <span className="text-muted-foreground ml-2 text-xs">
                                                        (#{held.candidate.id})
                                                    </span>
                                                </p>
                                                <p className="text-muted-foreground text-xs">
                                                    {held.candidate.participant
                                                        .email ??
                                                        'No email'}{' '}
                                                    &middot;{' '}
                                                    {held.candidate.participant
                                                        .contact_number ??
                                                        'No phone'}
                                                </p>
                                                <p className="text-muted-foreground text-xs">
                                                    DOB:{' '}
                                                    {held.candidate.participant
                                                        .birthday ??
                                                        'Not recorded'}
                                                </p>
                                            </>
                                        ) : (
                                            <p className="text-muted-foreground text-xs">
                                                Candidate registration details
                                                unavailable
                                            </p>
                                        )}
                                    </div>
                                </div>

                                {editingId === held.id ? (
                                    <div className="border-border space-y-3 border-t pt-4">
                                        <h3 className="text-sm font-semibold">
                                            Correct Participant Details
                                        </h3>
                                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                            <Input
                                                placeholder="First name"
                                                value={editForm.first_name}
                                                onChange={(e) =>
                                                    setEditForm({
                                                        ...editForm,
                                                        first_name:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                            <Input
                                                placeholder="Middle name"
                                                value={editForm.middle_name}
                                                onChange={(e) =>
                                                    setEditForm({
                                                        ...editForm,
                                                        middle_name:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                            <Input
                                                placeholder="Last name"
                                                value={editForm.last_name}
                                                onChange={(e) =>
                                                    setEditForm({
                                                        ...editForm,
                                                        last_name:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                            <Input
                                                placeholder="Suffix"
                                                value={editForm.suffix}
                                                onChange={(e) =>
                                                    setEditForm({
                                                        ...editForm,
                                                        suffix: e.target.value,
                                                    })
                                                }
                                            />
                                            <Input
                                                aria-label="Email"
                                                type="email"
                                                placeholder="Email"
                                                value={editForm.email}
                                                onChange={(e) =>
                                                    setEditForm({
                                                        ...editForm,
                                                        email: e.target.value,
                                                    })
                                                }
                                            />
                                            <Input
                                                aria-label="Contact number"
                                                placeholder="Contact number"
                                                value={editForm.contact_number}
                                                onChange={(e) =>
                                                    setEditForm({
                                                        ...editForm,
                                                        contact_number:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                        </div>
                                        <div className="flex gap-2">
                                            <Button
                                                disabled={processing}
                                                size="sm"
                                                onClick={() =>
                                                    handleAction(
                                                        held.id,
                                                        'correct_details',
                                                        editForm,
                                                    )
                                                }
                                            >
                                                Save &amp; Recheck
                                            </Button>
                                            <Button
                                                disabled={processing}
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setEditingId(null)
                                                }
                                            >
                                                Cancel
                                            </Button>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex flex-wrap items-center gap-2 pt-1">
                                        <Button
                                            disabled={processing}
                                            size="sm"
                                            variant="secondary"
                                            onClick={() =>
                                                handleAction(
                                                    held.id,
                                                    'confirm_duplicate',
                                                )
                                            }
                                        >
                                            <UserX className="text-muted-foreground mr-1 h-3.5 w-3.5" />
                                            Confirm Duplicate (Close)
                                        </Button>

                                        <Button
                                            disabled={processing}
                                            size="sm"
                                            className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                                            onClick={() =>
                                                handleAction(
                                                    held.id,
                                                    'confirm_separate',
                                                )
                                            }
                                        >
                                            <CheckCircle2 className="mr-1 h-3.5 w-3.5" />
                                            Confirm Separate Person (Release)
                                        </Button>

                                        <Button
                                            disabled={processing}
                                            size="sm"
                                            variant="outline"
                                            onClick={() => startEditing(held)}
                                        >
                                            <Edit3 className="text-muted-foreground mr-1 h-3.5 w-3.5" />
                                            Correct Details
                                        </Button>

                                        <Button
                                            disabled={processing}
                                            size="sm"
                                            variant="ghost"
                                            className="text-destructive hover:bg-destructive/10"
                                            onClick={() =>
                                                handleAction(held.id, 'remove')
                                            }
                                        >
                                            <Trash2 className="mr-1 h-3.5 w-3.5" />
                                            Remove
                                        </Button>
                                    </div>
                                )}
                            </article>
                        ))}

                        {heldRows.length === 0 && (
                            <p className="text-muted-foreground rounded-xl border p-6 text-center text-sm">
                                No held registrations currently in the review
                                queue.
                            </p>
                        )}
                    </div>
                )}

                {activeTab === 'audit' && (
                    <div className="space-y-4">
                        <p className="text-muted-foreground text-xs">
                            Similar names with a shared birthdate, email, or
                            phone. Review with the participant before taking
                            action; a shared contact alone does not prove the
                            same person.
                        </p>
                        {rows.map((pair) => (
                            <article
                                key={`${pair.left.id}-${pair.right.id}`}
                                className="bg-card space-y-3 rounded-xl border p-5"
                            >
                                <h2 className="text-sm font-medium">
                                    {pair.reasons.join(' · ')}
                                </h2>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {[pair.left, pair.right].map((p) => (
                                        <div
                                            key={p.id}
                                            className="bg-muted/30 space-y-1 rounded-lg p-3 text-sm"
                                        >
                                            <p className="font-semibold">
                                                {p.name} · Registration #{p.id}
                                            </p>
                                            <p>
                                                {p.email ?? 'No email'} ·{' '}
                                                {p.phone ?? 'No phone'}
                                            </p>
                                            <p>
                                                Birthdate:{' '}
                                                {p.birthday ?? 'Not recorded'}
                                                {p.provisional
                                                    ? ' · Provisional'
                                                    : ''}
                                            </p>
                                            <Link
                                                className="text-xs underline"
                                                href={`/events/${event.id}/attendance?q=${encodeURIComponent(p.name)}`}
                                            >
                                                Review attendance
                                            </Link>
                                        </div>
                                    ))}
                                </div>
                            </article>
                        ))}
                        {!rows.length && (
                            <p className="text-muted-foreground rounded-xl border p-6 text-center text-sm">
                                No matching suspicious pairs found in this
                                event.
                            </p>
                        )}
                    </div>
                )}
            </main>
        </>
    );
}
Duplicates.layout = {
    breadcrumbs: [
        { title: 'Events', href: '/events' },
        { title: 'Duplicate Management', href: '#' },
    ],
};
