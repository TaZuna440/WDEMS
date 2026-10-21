import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Trash2, Edit2, UserPlus } from 'lucide-react';

type Registration = {
    id: number;
    name: string;
    email: string | null;
    contact: string | null;
    participant_type: string;
    guardian_name: string | null;
    guardian_contact: string | null;
    attendance_code: string;
};

export default function ChurchRegistrations({
    event,
    registrations,
}: {
    event: any;
    registrations: Registration[];
}) {
    const [isModalOpen, setIsModalOpen] = useState(false);

    const { data, setData, post, processing, reset } = useForm({
        first_name: '',
        last_name: '',
        middle_name: '',
        email: '',
        contact_number: '',
        participant_type: 'Member',
        guardian_name: '',
        guardian_contact: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/events/${event.id}/registrations`, {
            onSuccess: () => {
                setIsModalOpen(false);
                reset();
            },
        });
    };

    return (
        <>
            <Head title={`Registrations - ${event.event_name}`} />
            <div className="mx-auto max-w-6xl space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-white">
                            {event.event_name}
                        </h1>
                        <p className="text-sm text-gray-400">
                            Church Event Registration
                        </p>
                    </div>
                    <button
                        onClick={() => setIsModalOpen(true)}
                        className="flex items-center gap-2 rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-violet-700"
                    >
                        <UserPlus className="h-4 w-4" /> Add Participant
                    </button>
                </div>

                <div className="overflow-hidden rounded-xl border border-white/10 bg-[#1a1a1a]">
                    <table className="w-full text-left text-sm text-gray-300">
                        <thead className="bg-white/5 text-xs text-gray-400 uppercase">
                            <tr>
                                <th className="px-6 py-4 font-medium">Name</th>
                                <th className="px-6 py-4 font-medium">
                                    Contact
                                </th>
                                <th className="px-6 py-4 font-medium">Type</th>
                                <th className="px-6 py-4 font-medium">
                                    Guardian
                                </th>
                                <th className="px-6 py-4 font-medium">Code</th>
                                <th className="px-6 py-4 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {registrations.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-6 py-8 text-center text-gray-500"
                                    >
                                        No participants yet. Add one to get
                                        started.
                                    </td>
                                </tr>
                            ) : (
                                registrations.map((reg) => (
                                    <tr
                                        key={reg.id}
                                        className="hover:bg-white/[0.02]"
                                    >
                                        <td className="px-6 py-4 font-medium text-white">
                                            {reg.name}
                                        </td>
                                        <td className="px-6 py-4">
                                            {reg.email || reg.contact || '—'}
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className="rounded bg-violet-500/20 px-2 py-1 text-xs text-violet-300">
                                                {reg.participant_type}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4">
                                            {reg.guardian_name
                                                ? `${reg.guardian_name} (${reg.guardian_contact})`
                                                : '—'}
                                        </td>
                                        <td className="px-6 py-4 font-mono text-xs">
                                            {reg.attendance_code}
                                        </td>
                                        <td className="flex justify-end gap-2 px-6 py-4">
                                            <button className="p-1 hover:text-white">
                                                <Edit2 className="h-4 w-4" />
                                            </button>
                                            <button className="p-1 hover:text-red-400">
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {isModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
                    <form
                        onSubmit={submit}
                        className="w-full max-w-md space-y-4 rounded-xl border border-white/10 bg-[#1e1e1e] p-6"
                    >
                        <h2 className="text-lg font-semibold text-white">
                            Add Participant
                        </h2>

                        <div className="grid grid-cols-2 gap-3">
                            <input
                                type="text"
                                placeholder="First Name"
                                value={data.first_name}
                                onChange={(e) =>
                                    setData('first_name', e.target.value)
                                }
                                className="rounded border border-white/10 bg-black/50 p-2 text-sm text-white"
                            />
                            <input
                                type="text"
                                placeholder="Last Name"
                                value={data.last_name}
                                onChange={(e) =>
                                    setData('last_name', e.target.value)
                                }
                                className="rounded border border-white/10 bg-black/50 p-2 text-sm text-white"
                            />
                        </div>

                        <input
                            type="email"
                            placeholder="Email (optional)"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className="w-full rounded border border-white/10 bg-black/50 p-2 text-sm text-white"
                        />
                        <input
                            type="text"
                            placeholder="Contact Number (optional)"
                            value={data.contact_number}
                            onChange={(e) =>
                                setData('contact_number', e.target.value)
                            }
                            className="w-full rounded border border-white/10 bg-black/50 p-2 text-sm text-white"
                        />

                        <select
                            value={data.participant_type}
                            onChange={(e) =>
                                setData('participant_type', e.target.value)
                            }
                            className="w-full rounded border border-white/10 bg-black/50 p-2 text-sm text-white"
                        >
                            <option value="Member">Member</option>
                            <option value="First-Timer">First-Timer</option>
                            <option value="Guest">Guest</option>
                        </select>

                        {(event.church_category === 'Kids Ministry' ||
                            event.church_category === 'Youth Camp') && (
                            <div className="grid grid-cols-2 gap-3">
                                <input
                                    type="text"
                                    placeholder="Guardian Name"
                                    value={data.guardian_name}
                                    onChange={(e) =>
                                        setData('guardian_name', e.target.value)
                                    }
                                    className="rounded border border-white/10 bg-black/50 p-2 text-sm text-white"
                                />
                                <input
                                    type="text"
                                    placeholder="Guardian Contact"
                                    value={data.guardian_contact}
                                    onChange={(e) =>
                                        setData(
                                            'guardian_contact',
                                            e.target.value,
                                        )
                                    }
                                    className="rounded border border-white/10 bg-black/50 p-2 text-sm text-white"
                                />
                            </div>
                        )}

                        <div className="flex justify-end gap-3 pt-4">
                            <button
                                type="button"
                                onClick={() => setIsModalOpen(false)}
                                className="px-4 py-2 text-sm text-gray-400 hover:text-white"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded bg-violet-600 px-4 py-2 text-sm text-white hover:bg-violet-700 disabled:opacity-50"
                            >
                                Save
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </>
    );
}

ChurchRegistrations.layout = {
    breadcrumbs: [
        { title: 'Events', href: '/events' },
        { title: 'Church Registrations', href: '#' },
    ],
};
