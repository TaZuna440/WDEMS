import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle, Mail } from 'lucide-react';

/**
 * Staff-facing OTP challenge for deleting a single registration.
 *
 * Fork of event-deletion-otp-dialog.tsx, keyed on a registration ID
 * instead of an event ID. The two dialogs look similar but their
 * lifecycles differ — the event version is opened from a full-page
 * Danger Zone, this one from a card-level dropdown menu. AI-CONTEXT
 * §9 trap #8 forbids reusing one for the other.
 *
 * The `locked` state is detected by a case-insensitive substring
 * match on the server error message ("too many"). This is fragile by
 * design — the correct long-term fix is a structured error field,
 * but that is out of scope for Phase 9. See event-deletion.md §9
 * trap #1 for the same pattern and the same caveat.
 */
type Props = {
    registrationId: number;
    participantName: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const RESEND_COOLDOWN_SECONDS = 60;

export default function RegistrationDeletionOtpDialog({
    registrationId,
    participantName,
    open,
    onOpenChange,
}: Props) {
    const page = usePage<{ errors: Record<string, string> }>();
    const serverError = page.props.errors?.code ?? null;

    const [code, setCode] = useState('');
    const [sending, setSending] = useState(false);
    const [verifying, setVerifying] = useState(false);
    const [countdown, setCountdown] = useState(0);
    const [hasRequested, setHasRequested] = useState(false);
    const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const requestOtp = () => {
        setSending(true);
        router.post(
            `/registrations/${registrationId}/deletion/request-otp`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setCountdown(RESEND_COOLDOWN_SECONDS);
                },
                onFinish: () => {
                    setSending(false);
                },
            },
        );
    };

    // Auto-request on first open. Re-opening the dialog triggers a
    // fresh request because `hasRequested` resets when `open` flips
    // to false.
    useEffect(() => {
        if (!open) {
            setHasRequested(false);
            return;
        }
        if (hasRequested) return;
        setHasRequested(true);
        requestOtp();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    // Reset countdown when a server error appears, so the user can
    // retry immediately after a send failure. See event-deletion.md
    // trap #2 for the rationale — this is deliberate, not a bug.
    useEffect(() => {
        if (serverError) {
            setCountdown(0);
        }
    }, [serverError]);

    useEffect(() => {
        if (countdown <= 0) return;
        timerRef.current = setInterval(() => {
            setCountdown((c) => (c > 0 ? c - 1 : 0));
        }, 1000);
        return () => {
            if (timerRef.current) clearInterval(timerRef.current);
        };
    }, [countdown]);

    const onCodeChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const digits = e.target.value.replace(/\D/g, '').slice(0, 6);
        setCode(digits);
    };

    const verify = () => {
        if (code.length !== 6) return;
        setVerifying(true);
        router.post(
            `/registrations/${registrationId}/deletion/verify-otp`,
            { code },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setVerifying(false),
            },
        );
    };

    const locked = serverError?.toLowerCase().includes('too many') ?? false;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Mail className="h-4 w-4" />
                        Verify Registration Deletion
                    </DialogTitle>
                    <DialogDescription>
                        We sent a 6-digit verification code to your registered
                        email. Enter it below to permanently delete the
                        registration of <strong>{participantName}</strong>.
                    </DialogDescription>
                </DialogHeader>

                {serverError && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{serverError}</AlertDescription>
                    </Alert>
                )}

                {!locked && (
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="registration-deletion-otp">
                            Verification code
                        </Label>
                        <Input
                            id="registration-deletion-otp"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            maxLength={6}
                            pattern="[0-9]*"
                            value={code}
                            onChange={onCodeChange}
                            disabled={verifying}
                            placeholder="000000"
                            className="text-center font-mono text-lg tracking-[0.5em]"
                        />
                    </div>
                )}

                <DialogFooter className="gap-2">
                    {locked ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Dismiss
                        </Button>
                    ) : (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => onOpenChange(false)}
                                disabled={verifying}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={requestOtp}
                                disabled={sending || countdown > 0 || verifying}
                            >
                                {sending
                                    ? 'Sending…'
                                    : countdown > 0
                                      ? `Resend (${countdown}s)`
                                      : 'Resend code'}
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={verify}
                                disabled={verifying || code.length !== 6}
                            >
                                {verifying ? (
                                    <>
                                        <Spinner className="mr-2" /> Verifying…
                                    </>
                                ) : (
                                    'Verify & Delete'
                                )}
                            </Button>
                        </>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
