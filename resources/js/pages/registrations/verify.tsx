import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, Copy, Ticket } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import FirebasePhoneVerifier, {
    type PhoneStage,
} from '@/components/firebase-phone-verifier';
import InputError from '@/components/input-error';
import RegistrationRecaptcha from '@/components/registration-recaptcha';
import RegistrationBarcode from '@/components/registration-barcode';
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
        phone?: ChannelState;
        ready?: boolean;
        similarity_warning?: boolean;
        submitted_phone?: string | null;
        attendance_code?: string | null;
        chosen_channel?: 'email' | 'phone' | null;
        needs_channel_choice?: boolean;
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
        <section className="grid gap-4 rounded-xl border border-lime-brand/30 bg-lime-brand/5 p-6">
            <div className="flex items-center gap-2">
                <Ticket className="h-4 w-4 text-lime-brand" />
                <h2 className="text-sm font-semibold uppercase tracking-wider text-lime-brand">
                    Your Registration Code
                </h2>
            </div>

            {code !== null && (
                <p
                    className="select-all text-center font-mono text-4xl font-semibold tracking-[0.3em] text-foreground"
                    aria-label={`Registration Code ${code}`}
                >
                    {code}
                </p>
            )}

            {code !== null && <RegistrationBarcode code={code} />}

            {code !== null && (
                <p className="text-center text-sm text-muted-foreground">
                    Save a screenshot or print this barcode for check-in at the event.
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
                                <Check className="mr-2 h-3.5 w-3.5 text-lime-brand" />
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
                className="text-center text-xs text-muted-foreground"
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
    channel,
    state,
    url,
    now,
    captchaRequired = false,
    captchaToken = '',
    onSent,
}: {
    channel: 'email' | 'phone';
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
        <section className="grid gap-4 rounded-xl border border-border p-6">
            <h2 className="text-lg font-semibold">{label} verification</h2>
            {state.status === 'verified' ? (
                <p role="status" className="text-lime-brand">
                    Verified. This code has been used.
                </p>
            ) : state.status === 'locked' ? (
                <p role="alert">
                    {captchaRequired ? 'Too many attempts. Please wait 2 hours to try again.' : 'Five incorrect attempts were made. Start a new registration to verify this contact again.'}
                </p>
            ) : (
                <>
                    <p className="text-muted-foreground text-sm">
                        {channel === 'email'
                            ? 'Enter the six-digit code emailed to your required email address.'
                            : 'Verify your phone number with the secure Firebase SMS check. Keep this page open while you complete it.'}
                    </p>
                    {state.status === 'waiting' && (
                        <p role="status">
                            {channel === 'phone'
                                ? 'Complete the Firebase SMS check to verify your phone.'
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
                                : 'Restart the Firebase phone check.'}
                        </p>
                    )}
                    {state.status === 'sent' && !expired && (
                        <p role="status">
                            {channel === 'email'
                                ? 'Email code sent.'
                                : 'Phone verification completed.'}{' '}
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
                            disabled={wait > 0 || resend.processing || (captchaRequired && !captchaToken)}
                            onClick={() => {
                                resend.transform(() => ({ recaptcha_token: captchaToken }));
                                resend.post(`${url}/email/resend`, {
                                    preserveScroll: true,
                                    onFinish: onSent,
                                });
                            }}
                        >
                            {wait > 0
                                ? `Request another code in ${wait}s`
                                : state.status === 'waiting' ? 'Send Email' : 'Send another email code'}
                        </Button>
                    )}
                </>
            )}
        </section>
    );
}

function ChannelChoiceScreen({
    verificationUrl,
}: {
    verificationUrl: string;
}) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [busy, setBusy] = useState(false);

    const pick = (channel: 'email' | 'phone') => {
        setBusy(true);
        router.post(
            `${verificationUrl}/channel`,
            { channel },
            { preserveScroll: true, onFinish: () => setBusy(false) },
        );
    };

    return (
        <section className="grid gap-4 rounded-xl border border-border p-6">
            <h2 className="text-lg font-semibold">
                How do you want to verify?
            </h2>
            <p className="text-muted-foreground text-sm">
                Choose one contact to verify. The other stays on file but
                won't need a code.
            </p>
            <div className="grid gap-3 sm:grid-cols-2">
                <Button
                    type="button"
                    variant="outline"
                    disabled={busy}
                    onClick={() => pick('email')}
                >
                    Verify by email
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={busy}
                    onClick={() => pick('phone')}
                >
                    Verify by phone
                </Button>
            </div>
            <InputError message={errors.channel} />
        </section>
    );
}

export default function VerifyRegistration({
    event_name,
    form_url,
    verification_url,
    verification,
    email_only = false,
    recaptcha_site_key = null,
}: Props) {
    const [captchaToken, setCaptchaToken] = useState('');
    const [captchaReset, setCaptchaReset] = useState(0);
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const completion = useForm({ similarity_acknowledged: false });
    const acknowledgement = useForm({});
    const [now, setNow] = useState(() => Date.now());
    const [phoneStage, setPhoneStage] = useState<PhoneStage>('idle');
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
    const phoneChannel = verification.phone;

    // A channel "needs work" when it is required (not 'optional') and
    // has not yet been verified or locked.
    const emailNeedsWork =
        emailChannel !== undefined &&
        emailChannel.status !== 'optional' &&
        emailChannel.status !== 'verified' &&
        emailChannel.status !== 'locked';

    const phoneIsRequired =
        phoneChannel !== undefined && phoneChannel.status !== 'optional';

    const phoneNeedsWork =
        phoneChannel !== undefined &&
        phoneIsRequired &&
        phoneChannel.status !== 'verified' &&
        phoneChannel.status !== 'locked';

    // The human check is shown whenever ANY channel still requires
    // verification. When phone is required, Firebase consumes the
    // token for the SMS send. When only email is required, the
    // widget is a visual checkpoint — the token is not consumed.
    // A server-enforced gate for email-only events is a separate
    // integration, not part of this flow.
    // Stricter channel choice (W5): both required + none chosen →
    // the participant picks one first. After the choice (or on
    // single-channel events) only the chosen channel renders.
    // Legacy no-choice challenges show both, as before.
    const needsChannelChoice = verification.needs_channel_choice === true;
    const chosenChannel = verification.chosen_channel ?? null;
    const showEmailChannel =
        chosenChannel === null || chosenChannel === 'email';
    const showPhoneChannel =
        chosenChannel === null || chosenChannel === 'phone';

    const showRecaptchaSection =
        verification.status === 'pending' &&
        !expired &&
        !needsChannelChoice &&
        (emailNeedsWork || phoneNeedsWork);

    return (
        <>
            <Head title={`Verify registration — ${event_name}`} />
            <div className="grid gap-6">
                <WizardProgress
                    steps={REGISTRATION_STEPS}
                    currentStep={3}
                    maxReachedStep={3}
                />
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

                {verification.status === 'finalized' && (
                    <>
                        <RegistrationCodePanel
                            code={verification.attendance_code ?? null}
                        />
                        <Button
                            type="button"
                            disabled={acknowledgement.processing}
                            onClick={() => acknowledgement.post(
                                `${verification_url}/acknowledge-code`,
                            )}
                        >
                            {acknowledgement.processing
                                ? 'Finishing…'
                                : "I've saved my Registration Code — finish"}
                        </Button>
                    </>
                )}

                {verification.status === 'pending' && !expired && (
                    <>
                        {showRecaptchaSection && !email_only && (
                            <section
                                className={
                                    phoneStage === 'idle' || !phoneNeedsWork
                                        ? 'grid gap-3 rounded-xl border border-border p-6'
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
                        {email_only && emailNeedsWork && <section className="rounded-xl border border-border p-6">
                            <h2 className="mb-3 text-lg font-semibold">Human verification</h2>
                            <RegistrationRecaptcha siteKey={recaptcha_site_key} resetKey={captchaReset} onToken={setCaptchaToken} />
                        </section>}
                        {needsChannelChoice ? (
                            <ChannelChoiceScreen
                                verificationUrl={verification_url}
                            />
                        ) : (
                            <>
                                {showEmailChannel && verification.email && (
                                    <ContactVerification
                                        channel="email"
                                        state={verification.email}
                                        url={verification_url}
                                        now={now}
                                        captchaRequired={email_only}
                                        captchaToken={captchaToken}
                                        onSent={() => { setCaptchaToken(''); setCaptchaReset((value) => value + 1); }}
                                    />
                                )}
                                {showPhoneChannel && phoneChannel && (
                                    <FirebasePhoneVerifier
                                        submittedPhone={verification.submitted_phone ?? ""}
                                        verifyUrl={`${verification_url}/phone/firebase`}
                                        disabled={false}
                                        verified={phoneChannel.status === "verified"}
                                        phoneRequired={phoneIsRequired}
                                        recaptchaContainerId="recaptcha-container"
                                        onStageChange={setPhoneStage}
                                    />
                                )}
                            </>
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
                        {verification.similarity_warning && <div className="space-y-3 rounded-xl border border-amber-500/40 p-4 text-sm" role="alert">
                            <p>A similar registration may exist. Check that your name, birthdate, and contact are correct. You can continue if this is a separate registration.</p>
                            <label className="flex items-center gap-2"><input type="checkbox" checked={completion.data.similarity_acknowledged} onChange={e => completion.setData('similarity_acknowledged', e.target.checked)} />I reviewed my details and want to continue.</label>
                        </div>}
                        <Button
                            type="button"
                            disabled={
                                !verification.ready || (verification.similarity_warning && !completion.data.similarity_acknowledged) || completion.processing
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
