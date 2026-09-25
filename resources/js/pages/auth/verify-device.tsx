import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, Check, Mail, RefreshCw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    email: string;
    autoSend: boolean;
    initialCountdown: number;
};

const RESEND_COOLDOWN_SECONDS = 60;

export default function VerifyDevice({
    email,
    autoSend,
    initialCountdown,
}: Props) {
    const page = usePage<{ errors: Record<string, string> }>();
    const serverError = page.props.errors?.code;

    const [code, setCode] = useState('');
    const [remember, setRemember] = useState(true);
    const [sending, setSending] = useState(false);
    const [verifying, setVerifying] = useState(false);
    const [countdown, setCountdown] = useState(initialCountdown);
    const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const hasRequestedRef = useRef(false);

    const sendCode = () => {
        setSending(true);
        router.post(
            '/verify-device/send-code',
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setCountdown(RESEND_COOLDOWN_SECONDS);
                },
                onFinish: () => setSending(false),
            },
        );
    };

    // Auto-request ONLY on first mount, and ONLY when the server
    // says there's no active code. The ref guard prevents React Strict
    // Mode's double-invocation from firing twice.
    useEffect(() => {
        if (!autoSend) return;
        if (hasRequestedRef.current) return;
        hasRequestedRef.current = true;
        sendCode();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [autoSend]);

    // Countdown tick
    useEffect(() => {
        if (countdown <= 0) return;
        timerRef.current = setInterval(() => {
            setCountdown((c) => (c > 0 ? c - 1 : 0));
        }, 1000);
        return () => {
            if (timerRef.current) clearInterval(timerRef.current);
        };
    }, [countdown]);

    const verify = () => {
        if (code.length !== 6) return;
        setVerifying(true);
        router.post(
            '/verify-device/verify',
            { code, remember },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setVerifying(false),
            },
        );
    };

    const onCodeChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const digits = e.target.value.replace(/\D/g, '').slice(0, 6);
        setCode(digits);
    };

    return (
        <>
            <Head title="Verify your device" />

            <div className="mx-auto flex w-full max-w-md flex-col gap-6">
                <div className="flex flex-col items-center gap-2 text-center">
                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-lime-brand/15">
                        <Mail className="h-6 w-6 text-lime-brand" />
                    </div>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Verify your device
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        We sent a 6-digit code to{' '}
                        <span className="font-medium text-foreground">
                            {email}
                        </span>
                        .
                    </p>
                </div>

                {serverError && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{serverError}</AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="code">Verification code</Label>
                        <Input
                            id="code"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            maxLength={6}
                            value={code}
                            onChange={onCodeChange}
                            disabled={verifying}
                            placeholder="000000"
                            className="text-center font-mono text-lg tracking-[0.5em]"
                            autoFocus
                        />
                    </div>

                    <div className="flex items-start gap-2 rounded-md border border-white/5 bg-white/[0.02] p-3">
                        <Checkbox
                            id="remember"
                            checked={remember}
                            onCheckedChange={(v) => setRemember(v === true)}
                            className="mt-0.5"
                        />
                        <div className="flex flex-1 flex-col">
                            <Label
                                htmlFor="remember"
                                className="cursor-pointer text-sm font-medium"
                            >
                                Remember this device for 30 days
                            </Label>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                You won&apos;t need a code on this browser for
                                the next 30 days.
                            </p>
                        </div>
                    </div>

                    <Button
                        type="button"
                        onClick={verify}
                        disabled={verifying || code.length !== 6}
                        className="w-full bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                    >
                        {verifying ? (
                            <>
                                <Spinner className="mr-2" />
                                Verifying…
                            </>
                        ) : (
                            <>
                                <Check className="mr-2 h-4 w-4" />
                                Verify
                            </>
                        )}
                    </Button>

                    <Button
                        type="button"
                        variant="ghost"
                        onClick={sendCode}
                        disabled={sending || countdown > 0 || verifying}
                        className="w-full"
                    >
                        {sending ? (
                            <>
                                <Spinner className="mr-2" />
                                Sending…
                            </>
                        ) : countdown > 0 ? (
                            `Resend code in ${countdown}s`
                        ) : (
                            <>
                                <RefreshCw className="mr-2 h-4 w-4" />
                                Resend code
                            </>
                        )}
                    </Button>
                </div>

                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    className="text-center text-xs text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="mr-1 inline h-3 w-3" />
                    Sign out
                </Link>
            </div>
        </>
    );
}

VerifyDevice.layout = {
    title: 'Verify your device',
    description: 'We sent a verification code to your email',
};
