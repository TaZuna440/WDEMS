import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, Copy, Ticket } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import RegistrationRecaptcha from '@/components/registration-recaptcha';
import WizardProgress from '@/components/wizard-progress';
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
    event_kind?: 'running' | 'church';
    form_url: string;
    verification_url: string;
    email_only?: boolean;
    recaptcha_site_key?: string | null;
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
        ready?: boolean;
        attendance_code?: string | null;
        is_held?: boolean;
        is_inactive?: boolean;
    };
};

const REGISTRATION_STEPS = [
    { id: 'disclaimer', label: 'Disclaimer' },
    { id: 'personal', label: 'Personal Info' },
    { id: 'emergency', label: 'Emergency Contact' },
    { id: 'verification', label: 'Verification' },
    { id: 'confirmation', label: 'Confirmation' },
];

function RegistrationCodePanel({ code }: { code: string | null }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        if (code === null) return;

        try {
            await navigator.clipboard.writeText(code);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            // Clipboard API unavailable. The participant can still
            // select the code manually.
        }
    };

    return (
        <section className="border-lime-brand/30 bg-lime-brand/5 grid gap-4 rounded-xl border p-6">
            <div className="flex items-center gap-2">
                <Ticket className="text-lime-brand h-4 w-4" />
                <h2 className="text-lime-brand text-sm font-semibold tracking-wider uppercase">
                    Your Registration Code
                </h2>
            </div>

            {code !== null && (
                <p
                    className="text-foreground text-center font-mono text-4xl font-semibold tracking-[0.3em] select-all"
                    aria-label={`Registration Code ${code}`}
                >
                    {code}
                </p>
            )}

            {code !== null && (
                <p className="text-muted-foreground text-center text-sm">
                    Keep this code and give it to the organizer for attendance
                    lookup.
                </p>
            )}

            {code !== null && (
                <div className="flex items-center justify-center">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={copy}
                        aria-label={
                            copied
                                ? 'Registration Code copied'
                                : 'Copy Registration Code'
                        }
                    >
                        {copied ? (
                            <>
                                <Check className="text-lime-brand mr-2 h-3.5 w-3.5" />
                                Registration Code copied
                            </>
                        ) : (
                            <>
                                <Copy className="mr-2 h-3.5 w-3.5" />
                                Copy Registration Code
                            </>
                        )}
                    </Button>
                </div>
            )}

            <p
                role="status"
                className="text-muted-foreground text-center text-xs"
            >
                {code === null
                    ? 'Your registration is confirmed. A Registration Code is unavailable on this page.'
                    : copied
                      ? 'Registration Code copied to clipboard.'
                      : 'Please copy your Registration Code or take a screenshot now. Once you confirm, it will no longer be displayed on this page.'}
            </p>
        </section>
    );
}

function ContactVerification({
    state,
    url,
    now,
    captchaRequired = false,
    captchaToken = '',
    onSent,
}: {
    state: ChannelState;
    url: string;
    now: number;
    captchaRequired?: boolean;
    captchaToken?: string;
    onSent?: () => void;
}) {
    const form = useForm({ code: '' });
    const resend = useForm({});
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [cooldown, setCooldown] = useState(0);
    const [isResending, setIsResending] = useState(false);
    const [resendSuccess, setResendSuccess] = useState('');
    const [resendError, setResendError] = useState('');
    const wait = state.retry_at
        ? Math.max(0, Math.ceil((Date.parse(state.retry_at) - now) / 1000))
        : 0;
    const remainingCooldown = Math.max(cooldown, wait);
    const expired =
        state.expires_at !== null && Date.parse(state.expires_at) <= now;

    useEffect(() => {
        if (cooldown <= 0) return;
        const timer = window.setInterval(() => {
            setCooldown((remaining) => Math.max(0, remaining - 1));
        }, 1000);
        return () => window.clearInterval(timer);
    }, [cooldown]);

    if (state.status === 'optional') return null;
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`${url}/email`, {
            preserveScroll: true,
            onFinish: () => form.reset('code'),
        });
    };

    return (
        <section className="border-border grid gap-4 rounded-xl border p-6">
            <h2 className="text-lg font-semibold">Email verification</h2>
            {state.status === 'verified' ? (
                <p role="status" className="text-lime-brand">
                    Verified. This code has been used.
                </p>
            ) : state.status === 'locked' ? (
                <p role="alert">
                    {captchaRequired
                        ? 'Too many attempts. Please wait 2 hours to try again.'
                        : 'Five incorrect attempts were made. Start a new registration to verify this contact again.'}
                </p>
            ) : (
                <>
                    <p className="text-muted-foreground text-sm">
                        {state.status === 'sent'
                            ? 'Enter the six-digit code sent to your email address.'
                            : 'Complete reCAPTCHA, then send an email code.'}
                    </p>
                    {state.status === 'waiting' && (
                        <p role="status">Request an email code to continue.</p>
                    )}
                    {state.status === 'delivery_failed' && (
                        <p role="alert">
                            Email delivery failed. Your registration is still
                            pending. Retry after the cooldown.
                        </p>
                    )}
                    {(state.status === 'expired' || expired) && (
                        <p role="alert">
                            This code has expired. Request another email code.
                        </p>
                    )}
                    {state.status === 'sent' && !expired && (
                        <p role="status">
                            Email code sent. Codes expire after ten minutes.
                        </p>
                    )}
                    {(state.status === 'sent' ||
                        state.status === 'delivery_failed' ||
                        expired) && (
                        <p className="text-muted-foreground text-sm">
                            Didn't receive the code?
                        </p>
                    )}
                    <Button
                        type="button"
                        variant="outline"
                        disabled={
                            remainingCooldown > 0 ||
                            isResending ||
                            resend.processing ||
                            (captchaRequired && !captchaToken)
                        }
                        onClick={() => {
                            setResendSuccess('');
                            setResendError('');
                            setIsResending(true);
                            resend.transform(() => ({
                                recaptcha_token: captchaToken,
                            }));
                            resend.post(`${url}/email/resend`, {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setCooldown(100);
                                    setResendSuccess(
                                        'A new code has been sent!',
                                    );
                                    onSent?.();
                                },
                                onError: (responseErrors) => {
                                    setResendError(
                                        responseErrors.email ??
                                            responseErrors.verification ??
                                            'We could not resend the code. Please try again.',
                                    );
                                },
                                onFinish: () => setIsResending(false),
                            });
                        }}
                    >
                        {isResending || resend.processing
                            ? 'Sending...'
                            : remainingCooldown > 0
                              ? `Resend in ${remainingCooldown}s...`
                              : state.status === 'waiting'
                                ? 'Send code'
                                : 'Resend Code'}
                    </Button>
                    {resendSuccess && (
                        <p role="status" className="text-lime-brand text-sm">
                            {resendSuccess}
                        </p>
                    )}
                    {resendError && (
                        <p role="alert" className="text-destructive text-sm">
                            {resendError}
                        </p>
                    )}
                    {state.status === 'sent' && !expired && (
                        <form onSubmit={submit} className="grid gap-3">
                            <Label htmlFor="email-code">Email code</Label>
                            <Input
                                id="email-code"
                                value={form.data.code}
                                onChange={(event) =>
                                    form.setData('code', event.target.value)
                                }
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                pattern="[0-9]{6}"
                                maxLength={6}
                                required
                                aria-describedby="email-error"
                            />
                            <div id="email-error">
                                <InputError message={errors.email} />
                            </div>
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    state.status !== 'sent' ||
                                    expired
                                }
                            >
                                Verify email
                            </Button>
                        </form>
                    )}
                    <InputError message={errors.email} />
                    <p className="text-muted-foreground text-xs">
                        {state.attempts_remaining} attempts remaining. New codes
                        do not reset this limit.
                    </p>
                </>
            )}
        </section>
    );
}

export default function VerifyRegistration({
    event_name,
    event_kind = 'running',
    form_url,
    verification_url,
    verification,
    email_only = false,
    recaptcha_site_key = null,
}: Props) {
    const [captchaToken, setCaptchaToken] = useState('');
    const [captchaReset, setCaptchaReset] = useState(0);
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const completion = useForm({});
    const acknowledgement = useForm({});
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

    const emailChannel = verification.email;

    // Email needs work when it is required and has not yet been verified or locked.
    const emailNeedsWork =
        emailChannel !== undefined &&
        emailChannel.status !== 'optional' &&
        emailChannel.status !== 'verified' &&
        emailChannel.status !== 'locked';

    const showRecaptchaSection =
        verification.status === 'pending' &&
        !expired &&
        !false &&
        emailNeedsWork;

    return (
        <>
            <Head title={`Verify registration — ${event_name}`} />
            <div className="grid gap-6">
                {event_kind === 'running' && (
                    <WizardProgress
                        steps={REGISTRATION_STEPS}
                        currentStep={3}
                        maxReachedStep={3}
                    />
                )}
                <h1 className="text-2xl font-semibold">
                    {verification.is_inactive
                        ? 'Registration Received'
                        : verification.status === 'finalized'
                          ? 'You are registered.'
                          : 'Verify your contacts'}
                </h1>
                <p className="text-muted-foreground">{event_name}</p>
                <p role="status">
                    {verification.is_inactive
                        ? 'Your submission is not active. Please contact the organizer for its review status.'
                        : expired
                          ? messages.expired
                          : messages[verification.status]}
                </p>
                <InputError message={errors.verification} />

                {verification.status === 'finalized' && (
                    <>
                        {verification.is_inactive ? (
                            <section className="grid gap-4 rounded-xl border border-[#13253B]/30 bg-[#13253B]/5 p-6 text-center dark:border-yellow-500/30 dark:bg-yellow-500/10">
                                <h2 className="text-base font-semibold text-[#13253B] dark:text-yellow-400">
                                    Registration Status
                                </h2>
                                <p className="text-muted-foreground mx-auto max-w-md text-sm">
                                    Your submission has been received. Please
                                    contact the organizer for the review result.
                                </p>
                            </section>
                        ) : (
                            <RegistrationCodePanel
                                code={verification.attendance_code ?? null}
                            />
                        )}
                        <Button
                            type="button"
                            disabled={acknowledgement.processing}
                            onClick={() => {
                                try {
                                    const slug = new URL(
                                        form_url,
                                        window.location.origin,
                                    ).pathname
                                        .split('/')
                                        .filter(Boolean)
                                        .at(-1);
                                    window.localStorage.removeItem(
                                        event_kind === 'church'
                                            ? `church-registration:${slug}`
                                            : `wdems-registration-${slug}`,
                                    );
                                } catch {
                                    /* Storage may be unavailable. */
                                }
                                acknowledgement.transform(() => ({
                                    start_another: true,
                                }));
                                acknowledgement.post(
                                    `${verification_url}/acknowledge-code`,
                                );
                            }}
                        >
                            {acknowledgement.processing
                                ? 'Finishing…'
                                : verification.is_inactive
                                  ? 'Acknowledge'
                                  : 'Confirm'}
                        </Button>
                    </>
                )}

                {verification.status === 'pending' && !expired && (
                    <>
                        {showRecaptchaSection && !email_only && (
                            <section
                                className={
                                    true /* eslint-disable-line no-constant-condition */
                                        ? 'border-border grid gap-3 rounded-xl border p-6'
                                        : 'hidden'
                                }
                            >
                                <h2 className="text-lg font-semibold">
                                    Human verification
                                </h2>
                                <p className="text-muted-foreground text-sm">
                                    Complete the reCAPTCHA below to continue.
                                </p>
                                <div id="recaptcha-container" />
                            </section>
                        )}
                        {email_only &&
                            emailNeedsWork &&
                            (emailChannel?.status !== 'sent' ||
                                !emailChannel.retry_at ||
                                Date.parse(emailChannel.retry_at) <= now) && (
                                <section className="border-border rounded-xl border p-6">
                                    <h2 className="mb-3 text-lg font-semibold">
                                        Email verification
                                    </h2>
                                    <RegistrationRecaptcha
                                        siteKey={recaptcha_site_key}
                                        resetKey={captchaReset}
                                        onToken={setCaptchaToken}
                                    />
                                </section>
                            )}
                        {verification.email && (
                            <ContactVerification
                                state={verification.email}
                                url={verification_url}
                                now={now}
                                captchaRequired={email_only}
                                captchaToken={captchaToken}
                                onSent={() => {
                                    setCaptchaToken('');
                                    setCaptchaReset((value) => value + 1);
                                }}
                            />
                        )}
                        {!email_only && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.reload({ only: ['verification'] })
                                }
                            >
                                Refresh verification status
                            </Button>
                        )}
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
