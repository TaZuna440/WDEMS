import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'staff';
    email_verified_at: string | null;
    created_at: string;
};

export default function AdminUsersIndex({ users }: { users: UserRow[] }) {
    return (
        <>
            <Head title="User Management" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6">
                <header className="flex flex-wrap items-center justify-between gap-4 pl-10">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold">
                            User Management
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Manage staff and administrator accounts.
                        </p>
                    </div>
                    <Button
                        asChild
                        className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                    >
                        <Link href="/admin/users/create">
                            <Plus className="mr-2 h-4 w-4" />
                            Create User
                        </Link>
                    </Button>
                </header>

                <div className="glass-panel overflow-x-auto rounded-xl">
                    <table className="w-full text-left text-sm">
                        <thead className="border-border text-muted-foreground border-b">
                            <tr>
                                <th className="px-5 py-3 font-medium">Name</th>
                                <th className="px-5 py-3 font-medium">Email</th>
                                <th className="px-5 py-3 font-medium">Role</th>
                                <th className="px-5 py-3 font-medium">
                                    Verified
                                </th>
                                <th className="px-5 py-3 font-medium">
                                    Created At
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-border divide-y">
                            {users.map((user) => (
                                <tr key={user.id}>
                                    <td className="text-foreground px-5 py-4 font-medium">
                                        {user.name}
                                    </td>
                                    <td className="text-muted-foreground px-5 py-4">
                                        {user.email}
                                    </td>
                                    <td className="px-5 py-4 capitalize">
                                        {user.role}
                                    </td>
                                    <td className="px-5 py-4">
                                        {user.email_verified_at
                                            ? 'Verified'
                                            : 'Pending'}
                                    </td>
                                    <td className="text-muted-foreground px-5 py-4">
                                        {new Date(
                                            user.created_at,
                                        ).toLocaleString()}
                                    </td>
                                </tr>
                            ))}
                            {users.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="text-muted-foreground px-5 py-10 text-center"
                                    >
                                        No users found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
