import { Head, router, usePage } from '@inertiajs/react';
import { CheckCircle2, ExternalLink, XCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';

type Props = {
    connected: boolean;
    google_email: string | null;
    connected_at: string | null;
};

export default function SettingsGoogle({ connected, google_email, connected_at }: Props) {
    const page = usePage<{ errors: Record<string, string>; flash?: { status?: string } }>();
    const error = page.props.errors?.google;
    const status = page.props.flash?.status;

    const connect = () => {
        window.location.href = '/auth/google/redirect';
    };

    const disconnect = () => {
        if (confirm('Disconnect your Google account? Future Google integrations for your events will stop working.')) {
            router.delete('/settings/google');
        }
    };

    return (
        <>
            <Head title="Google Integration" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-foreground">Google Integration</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Connect your Google account so WDEMS can create Forms and Sheets on your behalf.
                    </p>
                </div>

                {error && (
                    <Alert variant="destructive">
                        <XCircle className="h-4 w-4" />
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                {status === 'google-connected' && (
                    <Alert>
                        <CheckCircle2 className="h-4 w-4" />
                        <AlertDescription>Google account connected successfully.</AlertDescription>
                    </Alert>
                )}

                {status === 'google-disconnected' && (
                    <Alert>
                        <CheckCircle2 className="h-4 w-4" />
                        <AlertDescription>Google account disconnected.</AlertDescription>
                    </Alert>
                )}

                <div className="glass-panel rounded-xl p-6">
                    {connected ? (
                        <>
                            <div className="flex items-center gap-3">
                                <CheckCircle2 className="h-5 w-5 text-green-500" />
                                <div>
                                    <p className="text-sm font-medium text-foreground">
                                        Connected as {google_email}
                                    </p>
                                    {connected_at && (
                                        <p className="text-xs text-muted-foreground">
                                            Since {connected_at}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="mt-6">
                                <Button variant="outline" onClick={disconnect}>
                                    Disconnect Google Account
                                </Button>
                            </div>
                        </>
                    ) : (
                        <>
                            <div className="flex items-center gap-3">
                                <XCircle className="h-5 w-5 text-muted-foreground" />
                                <p className="text-sm text-foreground">No Google account connected.</p>
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                You must connect a Google account before you can create registration forms for your events.
                            </p>

                            <div className="mt-6">
                                <Button onClick={connect}>
                                    <ExternalLink className="mr-2 h-4 w-4" />
                                    Connect Google Account
                                </Button>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

SettingsGoogle.layout = {
    breadcrumbs: [
        { title: 'Settings', href: '/settings/profile' },
        { title: 'Google', href: '/settings/google' },
    ],
};
