import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';
import FirebasePhoneVerifier from '@/components/firebase-phone-verifier';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ChannelState = {
    status:
        | 'optional'
        | 'waiting'
        | 'sent'
        | 'expired'
        | 'verified'
        | 'locked'
        | 'delivery_failed';
    retry_at: string | null;
    expires_at: string | null;
    attempts_remaining: number;
};

type Props = {
    event_name: string;
    form_url: string;
    verification_url: string;
    verification: {
        status:
            | 'missing'
            | 'pending'
            | 'expired'
            | 'closed'
            | 'changed'
            | 'finalized';
        expires_at?: string;
        email?: ChannelState;
        phone?: ChannelState;
        ready?: boolean;
        submitted_phone?: string | null;
    };
};

function ContactVerification({
    channel,
    state,
    url,
    now,
}: {
    channel: 'email' | 'phone';
    state: ChannelState;
    url: string;
    now: number;
}) {
    const form = useForm({ code: '' });
    const resend = useForm({});
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const wait = state.retry_at
        ? Math.max(0, Math.ceil((Date.parse(state.retry_at) - now) / 1000))
        : 0;
    const expired =
        state.expires_at !== null && Date.parse(state.expires_at) <= now;
    const label = channel === 'email' ? 'Email' : 'Phone';

    if (state.status === 'optional') return null;
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`${url}/${channel}`, {
            preserveScroll: true,
            onFinish: () => form.reset('code'),
        });
    };

    return (
        <section className="grid gap-4 rounded-xl border border-white/10 p-6">
            <h2 className="text-lg font-semibold">{label} verification</h2>
            {state.status === 'verified' ? (
                <p role="status" className="text-lime-brand">
                    Verified. This code has been used.
                </p>
            ) : state.status === 'locked' ? (
                <p role="alert">
                    Five incorrect attempts were made. Start a new registration
                    to verify this contact again.
                </p>
            ) : (
                <>
                    <p className="text-muted-foreground text-sm">
                        {channel === 'email'
                            ? 'Enter the six-digit code emailed to your required email address.'
                            : 'Event staff will communicate a six-digit code to your submitted phone number using their own phone. WDEMS does not send an SMS. Keep this page open or return in this browser.'}
                    </p>
                    {state.status === 'waiting' && (
                        <p role="status">
                            {channel === 'phone'
                                ? 'Waiting for event staff to generate your code.'
                                : 'Request an email code to continue.'}
                        </p>
                    )}
                    {state.status === 'delivery_failed' && (
                        <p role="alert">
                            Email delivery failed. Your registration is still
                            pending. Retry after the cooldown.
                        </p>
                    )}
                    {(state.status === 'expired' || expired) && (
                        <p role="alert">
                            This code has expired.{' '}
                            {channel === 'email'
                                ? 'Request another email code.'
                                : 'Ask event staff to generate a new phone code.'}
                        </p>
                    )}
                    {state.status === 'sent' && !expired && (
                        <p role="status">
                            {channel === 'email'
                                ? 'Email code sent.'
                                : 'Phone code generated. Wait for staff to communicate it.'}{' '}
                            Codes expire after ten minutes.
                        </p>
                    )}
                    <form onSubmit={submit} className="grid gap-3">
                        <Label htmlFor={`${channel}-code`}>{label} code</Label>
                        <Input
                            id={`${channel}-code`}
                            value={form.data.code}
                            onChange={(event) =>
                                form.setData('code', event.target.value)
                            }
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            pattern="[0-9]{6}"
                            maxLength={6}
                            required
                            aria-describedby={`${channel}-error`}
                        />
                        <div id={`${channel}-error`}>
                            <InputError message={errors[channel]} />
                        </div>
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                state.status !== 'sent' ||
                                expired
                            }
                        >
                            Verify {channel}
                        </Button>
                    </form>
                    <p className="text-muted-foreground text-xs">
                        {state.attempts_remaining} attempts remaining. New codes
                        do not reset this limit.
                    </p>
                    {channel === 'email' && (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={wait > 0 || resend.processing}
                            onClick={() =>
                                resend.post(`${url}/email/resend`, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            {wait > 0
                                ? `Request another code in ${wait}s`
                                : 'Send another email code'}
                        </Button>
                    )}
                </>
            )}
        </section>
    );
}

export default function VerifyRegistration({
    event_name,
    form_url,
    verification_url,
    verification,
}: Props) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const completion = useForm({});
    const [now, setNow] = useState(() => Date.now());
    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 1000);
        return () => window.clearInterval(timer);
    }, []);
    const expired =
        verification.expires_at !== undefined &&
        Date.parse(verification.expires_at) <= now;
    const messages = {
        missing:
            'No active verification was found in this browser. Start a registration to continue.',
        expired: 'This verification has expired. Start a new registration.',
        closed: 'Registration for this event is closed. Contact the organizer for assistance.',
        changed:
            'The registration requirements changed. Start a new registration using the current form.',
        finalized:
            'Your registration is confirmed. All required contacts were verified.',
        pending:
            'Verify every required contact, then complete your registration. Your place is not registered yet.',
    };

    return (
        <>
            <Head title={`Verify registration — ${event_name}`} />
            <div className="grid gap-6">
                <h1 className="text-2xl font-semibold">
                    {verification.status === 'finalized'
                        ? 'You are registered.'
                        : 'Verify your contacts'}
                </h1>
                <p className="text-muted-foreground">{event_name}</p>
                <p role="status">
                    {expired ? messages.expired : messages[verification.status]}
                </p>
                <InputError message={errors.verification} />
                {verification.status === 'pending' && !expired && (
                    <>
                        {verification.email && (
                            <ContactVerification
                                channel="email"
                                state={verification.email}
                                url={verification_url}
                                now={now}
                            />
                        )}
                        {verification.phone && (
                            <FirebasePhoneVerifier
                                submittedPhone={verification.submitted_phone ?? ""}
                                verifyUrl={`${verification_url}/phone/firebase`}
                                disabled={false}
                                verified={verification.phone.status === "verified"}
                            />
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.reload({ only: ['verification'] })
                            }
                        >
                            Refresh verification status
                        </Button>
                        <Button
                            type="button"
                            disabled={
                                !verification.ready || completion.processing
                            }
                            onClick={() =>
                                completion.post(
                                    `${verification_url}/complete`,
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {completion.processing
                                ? 'Completing…'
                                : 'Complete registration'}
                        </Button>
                        <p className="text-muted-foreground text-xs">
                            Verification must be completed within 24 hours of
                            submitting the form. Continue in this browser; no
                            secret link is needed.
                        </p>
                    </>
                )}
                {verification.status !== 'finalized' && (
                    <a
                        href={form_url}
                        className="text-lime-brand text-sm underline"
                    >
                        Return to the form / start again
                    </a>
                )}
            </div>
        </>
    );
}
