import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export default function AdminUsersCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        role: 'staff',
        password: '',
        password_confirmation: '',
    });

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/admin/users');
    };

    return (
        <>
            <Head title="Create User" />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-6">
                <Link
                    href="/admin/users"
                    className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-2 text-sm"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to users
                </Link>
                <header className="pl-10">
                    <h1 className="text-foreground text-2xl font-semibold">
                        Create User
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Create an administrator or staff account.
                    </p>
                </header>

                <form
                    onSubmit={submit}
                    className="glass-panel flex flex-col gap-5 rounded-xl p-6"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            autoComplete="name"
                            value={data.name}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                            aria-invalid={Boolean(errors.name)}
                            required
                        />
                        {errors.name && (
                            <p className="text-destructive text-sm">
                                {errors.name}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            autoComplete="email"
                            value={data.email}
                            onChange={(event) =>
                                setData('email', event.target.value)
                            }
                            aria-invalid={Boolean(errors.email)}
                            required
                        />
                        {errors.email && (
                            <p className="text-destructive text-sm">
                                {errors.email}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="role">Role</Label>
                        <Select
                            value={data.role}
                            onValueChange={(role) => setData('role', role)}
                        >
                            <SelectTrigger id="role" aria-label="Role">
                                <SelectValue placeholder="Choose a role" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="staff">Staff</SelectItem>
                                <SelectItem value="admin">Admin</SelectItem>
                            </SelectContent>
                        </Select>
                        {errors.role && (
                            <p className="text-destructive text-sm">
                                {errors.role}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password">Password</Label>
                        <Input
                            id="password"
                            type="password"
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(event) =>
                                setData('password', event.target.value)
                            }
                            aria-invalid={Boolean(errors.password)}
                            required
                        />
                        {errors.password && (
                            <p className="text-destructive text-sm">
                                {errors.password}
                            </p>
                        )}
                        <p className="text-muted-foreground text-xs">
                            Must be at least 8 characters, include uppercase,
                            lowercase, a number, and a special character.
                        </p>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">
                            Confirm Password
                        </Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={(event) =>
                                setData(
                                    'password_confirmation',
                                    event.target.value,
                                )
                            }
                            aria-invalid={Boolean(errors.password_confirmation)}
                            required
                        />
                        {errors.password_confirmation && (
                            <p className="text-destructive text-sm">
                                {errors.password_confirmation}
                            </p>
                        )}
                    </div>
                    <p className="text-muted-foreground text-sm">
                        The user will receive an email to verify their address
                        before they can log in.
                    </p>
                    <Button
                        type="submit"
                        disabled={processing}
                        className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                    >
                        {processing ? 'Creating…' : 'Create User'}
                    </Button>
                </form>
            </div>
        </>
    );
}
