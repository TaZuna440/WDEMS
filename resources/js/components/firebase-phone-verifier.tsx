import { useEffect, useLayoutEffect, useRef, useState, type FormEvent } from 'react';
import {
    RecaptchaVerifier,
    signInWithPhoneNumber,
    type ConfirmationResult,
} from 'firebase/auth';
import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import InputError from '@/components/input-error';
import { getFirebaseAuth } from '@/lib/firebase';

export type PhoneStage =
    | 'idle'
    | 'sending'
    | 'awaiting-code'
    | 'verifying'
    | 'verified';

type Props = {
    submittedPhone: string;
    verifyUrl: string;
    disabled: boolean;
    verified: boolean;
    /**
     * When false, this event does not require phone verification.
     * The component still initialises the Firebase reCAPTCHA into
     * the external container (id: recaptchaContainerId) so the
     * verification page can render it as a human-check, but no
     * phone-specific UI renders. The solved token is not consumed
     * — email-only events do not have an SMS to gate.
     */
    phoneRequired?: boolean;
    recaptchaContainerId?: string;
    onStageChange?: (stage: PhoneStage) => void;
};

function toE164(phone: string): string | null {
    const digits = phone.replace(/\D/g, '');
    if (/^63\d{10}$/.test(digits)) return '+' + digits;
    if (/^09\d{9}$/.test(digits)) return '+63' + digits.slice(1);
    if (/^9\d{9}$/.test(digits)) return '+63' + digits;
    return null;
}

function mapFirebaseError(error: unknown): string {
    const code = (error as { code?: string })?.code ?? '';
    switch (code) {
        case 'auth/invalid-phone-number':
            return 'The submitted phone number is not valid for verification.';
        case 'auth/too-many-requests':
        case 'auth/quota-exceeded':
            return 'Too many attempts. Please wait a moment and try again.';
        case 'auth/invalid-verification-code':
            return 'That code is not correct. Check the SMS and try again.';
        case 'auth/code-expired':
            return 'The code has expired. Request a new one.';
        case 'auth/captcha-check-failed':
            return 'reCAPTCHA verification failed. Refresh the page and try again.';
        case 'auth/missing-verification-code':
            return 'Enter the six-digit code from the SMS.';
        default:
            return 'We could not complete phone verification. Please try again.';
    }
}

export default function FirebasePhoneVerifier({
    submittedPhone,
    verifyUrl,
    disabled,
    verified,
    phoneRequired = true,
    recaptchaContainerId,
    onStageChange,
}: Props) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [stage, setStage] = useState<PhoneStage>(verified ? 'verified' : 'idle');
    const [code, setCode] = useState('');
    const [message, setMessage] = useState('');
    const [recaptchaReady, setRecaptchaReady] = useState(false);
    const [recaptchaEpoch, setRecaptchaEpoch] = useState(0);
    const confirmationRef = useRef<ConfirmationResult | null>(null);
    const recaptchaRef = useRef<RecaptchaVerifier | null>(null);
    const recaptchaContainerRef = useRef<HTMLDivElement>(null);
    const e164 = toE164(submittedPhone);

    useEffect(() => {
        onStageChange?.(stage);
    }, [stage, onStageChange]);

    useLayoutEffect(() => {
        if (disabled) return;
        // Skip init when phone is required but the submitted value
        // cannot be normalised — signInWithPhoneNumber cannot be
        // called anyway, so the widget would be decorative.
        if (phoneRequired && e164 === null) return;
        if (recaptchaRef.current !== null) return;

        const resolvedContainer: HTMLElement | null =
            recaptchaContainerId !== undefined
                ? document.getElementById(recaptchaContainerId)
                : recaptchaContainerRef.current;

        if (resolvedContainer === null) return;

        const verifier = new RecaptchaVerifier(
            getFirebaseAuth(),
            resolvedContainer,
            {
                size: 'normal',
                callback: () => setRecaptchaReady(true),
                'expired-callback': () => setRecaptchaReady(false),
            },
        );

        recaptchaRef.current = verifier;
        void verifier.render();

        return () => {
            verifier.clear();
            recaptchaRef.current = null;
            setRecaptchaReady(false);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [disabled, e164, recaptchaContainerId, recaptchaEpoch, phoneRequired]);

    const handleSend = async (event: FormEvent) => {
        event.preventDefault();
        if (e164 === null || recaptchaRef.current === null || !recaptchaReady) {
            return;
        }

        setStage('sending');
        setMessage('');

        try {
            confirmationRef.current = await signInWithPhoneNumber(
                getFirebaseAuth(),
                e164,
                recaptchaRef.current,
            );
            setStage('awaiting-code');
        } catch (error) {
            const code = (error as { code?: string })?.code ?? 'unknown';
            setMessage(`[${code}] ${mapFirebaseError(error)}`);
            setStage('idle');
            setRecaptchaEpoch((n) => n + 1);
        }
    };

    const handleVerify = async (event: FormEvent) => {
        event.preventDefault();
        if (confirmationRef.current === null || code.length !== 6) return;

        setStage('verifying');
        setMessage('');

        try {
            const credential = await confirmationRef.current.confirm(code);
            const idToken = await credential.user.getIdToken();

            router.post(
                verifyUrl,
                { id_token: idToken },
                { preserveScroll: true },
            );
        } catch (error) {
            const code = (error as { code?: string })?.code ?? 'unknown';
            setMessage(`[${code}] ${mapFirebaseError(error)}`);
            setStage('awaiting-code');
        }
    };

    useEffect(() => {
        if (stage !== 'verifying') return;
        if (errors.phone) {
            setMessage(errors.phone);
            setStage('awaiting-code');
        } else {
            setStage('verified');
        }
    }, [errors, stage]);

    useEffect(() => {
        if (verified) setStage('verified');
    }, [verified]);

    // Visual-only mode for email-required / phone-optional events.
    // The reCAPTCHA was initialised into the external container by
    // the effect above; this component contributes no visible UI.
    if (phoneRequired === false) {
        return null;
    }

    if (verified || stage === 'verified') {
        return (
            <section className="grid gap-4 rounded-xl border border-white/10 p-6">
                <h2 className="text-lg font-semibold">Phone verification</h2>
                <p role="status" className="text-lime-brand">
                    Verified. This code has been used.
                </p>
            </section>
        );
    }

    if (disabled) {
        return (
            <section className="grid gap-4 rounded-xl border border-white/10 p-6">
                <h2 className="text-lg font-semibold">Phone verification</h2>
                <p className="text-muted-foreground text-sm">
                    Phone verification is not available at this time.
                </p>
            </section>
        );
    }

    if (e164 === null) {
        return (
            <section className="grid gap-4 rounded-xl border border-white/10 p-6">
                <h2 className="text-lg font-semibold">Phone verification</h2>
                <p role="alert">
                    The submitted phone number cannot be verified. Contact the
                    event organizer for assistance.
                </p>
            </section>
        );
    }

    return (
        <section className="grid gap-4 rounded-xl border border-white/10 p-6">
            <h2 className="text-lg font-semibold">Phone verification</h2>
            <p className="text-muted-foreground text-sm">
                We will send a verification code via SMS to{' '}
                <span className="font-mono">{submittedPhone}</span>.
            </p>

            {recaptchaContainerId === undefined && (
                <div
                    ref={recaptchaContainerRef}
                    className={stage === 'idle' ? '' : 'hidden'}
                />
            )}

            {stage === 'idle' && (
                <form onSubmit={handleSend}>
                    <Button type="submit" disabled={!recaptchaReady}>
                        {recaptchaReady
                            ? 'Send verification code'
                            : 'Complete the reCAPTCHA above'}
                    </Button>
                </form>
            )}

            {stage === 'sending' && (
                <Button type="button" disabled>
                    <Spinner className="mr-2" /> Sending…
                </Button>
            )}

            {(stage === 'awaiting-code' || stage === 'verifying') && (
                <form onSubmit={handleVerify} className="grid gap-3">
                    <Label htmlFor="firebase-phone-code">SMS code</Label>
                    <Input
                        id="firebase-phone-code"
                        value={code}
                        onChange={(e) =>
                            setCode(e.target.value.replace(/\D/g, '').slice(0, 6))
                        }
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        maxLength={6}
                        required
                    />
                    <Button
                        type="submit"
                        disabled={stage === 'verifying' || code.length !== 6}
                    >
                        {stage === 'verifying' ? (
                            <>
                                <Spinner className="mr-2" /> Verifying…
                            </>
                        ) : (
                            'Verify phone'
                        )}
                    </Button>
                </form>
            )}

            {message !== '' && (
                <p role="alert" className="text-sm text-destructive">
                    {message}
                </p>
            )}
            <InputError message={errors.phone} />
        </section>
    );
}
