import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Challenge = {
    id: number;
    event_name: string;
    phone: string;
    created_at: string;
    expires_at: string;
    generate_url: string;
    channel: {
        status: string;
        retry_at: string | null;
        attempts_remaining: number;
    };
};

type Props = {
    challenges: Challenge[];
    previous_url: string | null;
    next_url: string | null;
};

export default function VerificationQueue({
    challenges,
    previous_url,
    next_url,
}: Props) {
    const [busy, setBusy] = useState<number | null>(null);
    const [error, setError] = useState('');
    const [issued, setIssued] = useState<{
        code: string;
        phone: string;
        event: string;
        until: number;
    } | null>(null);
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 1000);
        const conceal = () => {
            if (document.hidden) setIssued(null);
        };
        document.addEventListener('visibilitychange', conceal);
        return () => {
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', conceal);
        };
    }, []);
    useEffect(() => {
        if (issued === null) return;
        const timer = window.setTimeout(
            () => setIssued(null),
            Math.max(0, issued.until - Date.now()),
        );
        return () => window.clearTimeout(timer);
    }, [issued]);

    const generate = async (challenge: Challenge) => {
        setBusy(challenge.id);
        setError('');
        setIssued(null);
        try {
            const xsrf = document.cookie
                .split('; ')
                .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                ?.slice('XSRF-TOKEN='.length);
            if (!xsrf)
                throw new Error(
                    'Your session needs refreshing. Reload this page before generating a code.',
                );
            const response = await fetch(challenge.generate_url, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(xsrf),
                },
                body: '{}',
            });
            if (
                response.redirected ||
                response.status === 419 ||
                response.status === 401
            )
                throw new Error(
                    'Your session expired. Reload this page and sign in again.',
                );
            if (response.status === 429)
                throw new Error(
                    'Too many requests. Wait a minute before trying again.',
                );
            if (response.status === 403)
                throw new Error(
                    'You are not authorized to generate this code.',
                );
            const body = await response.json();
            if (!response.ok)
                throw new Error(
                    body.message ||
                        'The code could not be generated. Refresh and try again.',
                );
            setIssued({
                code: body.code,
                phone: challenge.phone,
                event: challenge.event_name,
                until: Date.now() + body.expires_in_seconds * 1000,
            });
            router.reload({ only: ['challenges'] });
        } catch (failure) {
            setError(
                failure instanceof Error
                    ? failure.message
                    : 'The code could not be generated.',
            );
        } finally {
            setBusy(null);
        }
    };

    return (
        <>
            <Head title="Phone verification queue" />
            <div className="mx-auto grid w-full max-w-4xl gap-6 p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <h1 className="text-2xl font-semibold">
                        Phone verification queue
                    </h1>
                    <Button
                        variant="outline"
                        onClick={() => {
                            setIssued(null);
                            router.reload();
                        }}
                    >
                        Refresh queue
                    </Button>
                </div>
                <p className="text-muted-foreground text-sm">
                    Generate a code and communicate it to the submitted number
                    using your own phone. WDEMS does not send an SMS. Codes are
                    shown once, expire after ten minutes, and can be regenerated
                    after 60 seconds.
                </p>
                <InputError message={error} />
                {challenges.length === 0 && (
                    <p role="status">
                        No pending phone verifications for events you can
                        manage.
                    </p>
                )}
                {challenges.map((challenge) => {
                    const cooldown = challenge.channel.retry_at
                        ? Math.max(
                              0,
                              Math.ceil(
                                  (Date.parse(challenge.channel.retry_at) -
                                      now) /
                                      1000,
                              ),
                          )
                        : 0;
                    const expired = Date.parse(challenge.expires_at) <= now;
                    return (
                        <section
                            key={challenge.id}
                            className="grid gap-3 rounded-xl border border-white/10 p-5 sm:grid-cols-[1fr_auto]"
                        >
                            <div>
                                <h2 className="font-semibold">
                                    {challenge.event_name}
                                </h2>
                                <p className="font-mono">{challenge.phone}</p>
                                <p className="text-muted-foreground text-xs">
                                    Requested{' '}
                                    {new Date(
                                        challenge.created_at,
                                    ).toLocaleString()}{' '}
                                    · {challenge.channel.attempts_remaining}{' '}
                                    attempts remaining
                                </p>
                                {challenge.channel.status === 'locked' && (
                                    <p className="text-sm">
                                        Attempt limit reached. The participant
                                        must start a new registration.
                                    </p>
                                )}
                                {expired && (
                                    <p className="text-sm">
                                        Challenge expired.
                                    </p>
                                )}
                            </div>
                            <Button
                                disabled={
                                    busy !== null ||
                                    cooldown > 0 ||
                                    expired ||
                                    challenge.channel.status === 'locked'
                                }
                                onClick={() => void generate(challenge)}
                            >
                                {busy === challenge.id
                                    ? 'Generating…'
                                    : cooldown > 0
                                      ? `Wait ${cooldown}s`
                                      : 'Generate phone code'}
                            </Button>
                        </section>
                    );
                })}
                <nav aria-label="Queue pages" className="flex gap-4">
                    {previous_url && <Link href={previous_url}>Previous</Link>}
                    {next_url && <Link href={next_url}>Next</Link>}
                </nav>
            </div>
            <Dialog
                open={issued !== null}
                onOpenChange={(open) => {
                    if (!open) setIssued(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Communicate this code yourself
                        </DialogTitle>
                        <DialogDescription>
                            {issued?.event} · {issued?.phone}. WDEMS has not
                            sent an SMS.
                        </DialogDescription>
                    </DialogHeader>
                    <p
                        className="text-center font-mono text-3xl tracking-widest"
                        aria-label="Generated phone code"
                    >
                        {issued?.code}
                    </p>
                    <p className="text-muted-foreground text-sm">
                        Closing or hiding this page conceals the code. It is
                        never stored in plaintext. If you lose it, generate a
                        replacement after the cooldown; the previous code will
                        stop working.
                    </p>
                    <Button onClick={() => setIssued(null)}>Close code</Button>
                </DialogContent>
            </Dialog>
        </>
    );
}
