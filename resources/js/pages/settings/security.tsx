import { Form, Head, router } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Mail, Monitor, ShieldCheck, X } from 'lucide-react';
import { useRef } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useConfirmDialog } from '@/hooks/use-confirm-dialog';
import { edit } from '@/routes/security';

type TrustedDevice = {
    id: string;
    label: string;
    ip: string | null;
    added_at: string | null;
};

type Props = {
    passwordRules: string;
    emailTwoFactorEnabled: boolean;
    trustedDevices: TrustedDevice[];
};

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

export default function Security({
    passwordRules,
    emailTwoFactorEnabled,
    trustedDevices,
}: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const { dialog, openConfirm } = useConfirmDialog();

    const handleToggle = () => {
        if (emailTwoFactorEnabled) {
            openConfirm({
                variant: 'danger',
                title: 'Turn off email two-factor authentication?',
                description:
                    'Anyone with your password will be able to log in from a new device without a verification code. All trusted devices will be forgotten.',
                confirmLabel: 'Turn off 2FA',
                onConfirm: () => {
                    router.put('/settings/security/two-factor', {}, {
                        preserveScroll: true,
                    });
                },
            });
        } else {
            router.put('/settings/security/two-factor', {}, {
                preserveScroll: true,
            });
        }
    };

    const handleRevoke = (device: TrustedDevice) => {
        openConfirm({
            variant: 'danger',
            title: 'Revoke this device?',
            description: `"${device.label}" will need to be verified again the next time it logs in.`,
            confirmLabel: 'Revoke device',
            onConfirm: () => {
                router.delete(`/settings/security/devices/${device.id}`, {
                    preserveScroll: true,
                });
            },
        });
    };

    return (
        <>
            <Head title="Security settings" />

            <h1 className="sr-only">Security settings</h1>

            <div className="space-y-10">
                {/* Password section */}
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Update password"
                        description="Ensure your account is using a long, random password to stay secure"
                    />

                    <Form
                        {...SecurityController.update.form()}
                        options={{ preserveScroll: true }}
                        resetOnError={[
                            'password',
                            'password_confirmation',
                            'current_password',
                        ]}
                        resetOnSuccess
                        onError={(errors) => {
                            if (errors.password) passwordInput.current?.focus();
                            if (errors.current_password)
                                currentPasswordInput.current?.focus();
                        }}
                        className="space-y-6"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="current_password">
                                        Current password
                                    </Label>
                                    <PasswordInput
                                        id="current_password"
                                        ref={currentPasswordInput}
                                        name="current_password"
                                        className="mt-1 block w-full"
                                        autoComplete="current-password"
                                        placeholder="Current password"
                                    />
                                    <InputError message={errors.current_password} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password">
                                        New password
                                    </Label>
                                    <PasswordInput
                                        id="password"
                                        ref={passwordInput}
                                        name="password"
                                        className="mt-1 block w-full"
                                        autoComplete="new-password"
                                        placeholder="New password"
                                        passwordrules={passwordRules}
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">
                                        Confirm password
                                    </Label>
                                    <PasswordInput
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        className="mt-1 block w-full"
                                        autoComplete="new-password"
                                        placeholder="Confirm password"
                                        passwordrules={passwordRules}
                                    />
                                    <InputError message={errors.password_confirmation} />
                                </div>

                                <div className="flex items-center gap-4">
                                    <Button
                                        disabled={processing}
                                        data-test="update-password-button"
                                    >
                                        Save
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>

                {/* Email Two-Factor Authentication */}
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Email Two-Factor Authentication"
                        description="We'll email you a 6-digit code when you log in from a new device"
                    />

                    <div className="rounded-lg border border-white/5 bg-white/[0.02] p-4">
                        <div className="flex items-center justify-between gap-4">
                            <div className="flex flex-1 items-start gap-3">
                                {emailTwoFactorEnabled ? (
                                    <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-green-400" />
                                ) : (
                                    <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                )}
                                <div>
                                    <p className="text-sm font-medium text-foreground">
                                        {emailTwoFactorEnabled ? 'Enabled' : 'Disabled'}
                                    </p>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {emailTwoFactorEnabled
                                            ? 'You will be asked for a code when logging in from a device we don\u2019t recognize.'
                                            : 'Anyone with your password can log in from any device.'}
                                    </p>
                                </div>
                            </div>

                            <Button
                                type="button"
                                variant={emailTwoFactorEnabled ? 'outline' : 'default'}
                                onClick={handleToggle}
                                className={
                                    emailTwoFactorEnabled
                                        ? ''
                                        : 'bg-lime-brand text-navy-900 hover:bg-lime-brand/90'
                                }
                            >
                                {emailTwoFactorEnabled ? 'Turn off' : 'Turn on'}
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Trusted devices */}
                {emailTwoFactorEnabled && (
                    <div className="space-y-6">
                        <Heading
                            variant="small"
                            title="Trusted devices"
                            description="Browsers that skip the code challenge for the next 30 days"
                        />

                        {trustedDevices.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-white/10 py-8 text-center">
                                <ShieldCheck className="mb-2 h-8 w-8 text-muted-foreground/40" />
                                <p className="text-sm text-muted-foreground">
                                    No trusted devices yet.
                                </p>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    Check &ldquo;Remember this device&rdquo; on the verification
                                    screen to trust a browser.
                                </p>
                            </div>
                        ) : (
                            <ul className="flex flex-col gap-2">
                                {trustedDevices.map((device) => (
                                    <li
                                        key={device.id}
                                        className="flex items-center gap-3 rounded-lg border border-white/5 bg-white/[0.02] p-3"
                                    >
                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-white/5">
                                            <Monitor className="h-4 w-4 text-muted-foreground" />
                                        </div>

                                        <div className="flex flex-1 flex-col">
                                            <span className="text-sm font-medium text-foreground">
                                                {device.label}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {device.ip ?? 'Unknown IP'} · Added{' '}
                                                {formatDate(device.added_at)}
                                            </span>
                                        </div>

                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleRevoke(device)}
                                            className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        >
                                            <X className="mr-1 h-3.5 w-3.5" />
                                            Revoke
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}

                {/* Info panel */}
                <div className="flex items-start gap-2 rounded-lg border border-white/5 bg-white/[0.02] p-4">
                    <Mail className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                    <p className="text-xs text-muted-foreground">
                        Verification codes are sent to{' '}
                        <span className="font-medium text-foreground">
                            your registered email address
                        </span>
                        . If you lose access to your email, contact your administrator.
                    </p>
                </div>
            </div>

            {dialog}
        </>
    );
}

Security.layout = {
    breadcrumbs: [
        {
            title: 'Security settings',
            href: edit(),
        },
    ],
};
