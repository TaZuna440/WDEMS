import { AlertCircle } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
    type ReactNode,
} from 'react';
import WizardProgress, { type WizardStep } from '@/components/wizard-progress';
import { Button } from '@/components/ui/button';
import { useWizardPersistence } from '@/hooks/use-wizard-persistence';

export type WizardStepConfig = WizardStep & {
    /**
     * All field names that belong to this step. Used to jump to the
     * correct step when the server returns validation errors.
     */
    fields: string[];

    /**
     * Fields that must not be empty before advancing past this step.
     * Used as a fallback when no `validate` function is provided.
     */
    requiredFields?: string[];

    /**
     * Optional client-side validator for this step.
     *
     * Receives the full form data and returns a Record keyed by field
     * name. An empty object means the step is valid. When omitted, the
     * wizard falls back to the emptiness check on `requiredFields`.
     *
     * The server remains the source of truth. These rules exist so the
     * user sees format errors before submitting, not so we can skip
     * server validation.
     */
    validate?: (data: Record<string, unknown>) => Record<string, string>;
};

type Props<T extends Record<string, unknown>> = {
    steps: WizardStepConfig[];
    storageKey: string;
    data: T;
    setData: (key: string, value: unknown) => void;
    errors: Record<string, string>;
    processing: boolean;
    onSubmit: () => void;
    submitLabel?: string;
    clearOnSubmit?: boolean;
    /**
     * Render function for the current step.
     *
     * The second argument carries the merged errors for the current
     * step — client-side validation errors from this wizard, combined
     * with server-side errors from the parent form. Pass these down to
     * the step component so its `<InputError>` slots actually fire.
     */
    children: (stepId: string, errors: Record<string, string>) => ReactNode;
};

export default function Wizard<T extends Record<string, unknown>>({
    steps,
    storageKey,
    data,
    setData,
    errors,
    processing,
    onSubmit,
    submitLabel = 'Submit',
    clearOnSubmit = true,
    children,
}: Props<T>) {
    const { snapshot, loaded, save, clear } =
        useWizardPersistence<T>(storageKey);

    // The furthest step the user has reached (used as the checkpoint).
    // Clamped against the current steps.length in case the step list
    // shrank (e.g. a running-event draft restored as a church event,
    // which has one fewer step).
    const [maxStep, setMaxStep] = useState(() =>
        Math.min(snapshot?.currentStep ?? 0, Math.max(0, steps.length - 1)),
    );
    // The step the user is currently viewing.
    const [currentStep, setCurrentStep] = useState(() =>
        Math.min(snapshot?.currentStep ?? 0, Math.max(0, steps.length - 1)),
    );
    const [stepErrors, setStepErrors] = useState<Record<string, string>>({});
    const [isReady, setIsReady] = useState(false);
    const hasRestoredSnapshot = useRef(false);

    // Derived, render-time-safe indices. If state ever exceeds the
    // current step list's bounds — because the step list just shrank —
    // these guard every read until the sync effect writes the clamped
    // value back to state.
    const safeCurrentStep = Math.min(currentStep, steps.length - 1);
    const safeMaxStep = Math.min(maxStep, steps.length - 1);

    // A ref mirror of the safe current step. The error-jump effect reads
    // this instead of the state so its dependency array can omit the
    // step. Without this, the effect re-fires on every Next click,
    // finds the same stale server error, and bounces the user back to
    // the step that owns the error.
    const currentStepRef = useRef(safeCurrentStep);
    currentStepRef.current = safeCurrentStep;

    // Restore only after the client-side storage read completes, then
    // enable saving so the initial defaults cannot overwrite a draft.
    useEffect(() => {
        if (!loaded || hasRestoredSnapshot.current) return;
        hasRestoredSnapshot.current = true;

        if (snapshot) {
            Object.entries(snapshot.data).forEach(([key, value]) => {
                setData(key, value);
            });
            const restoredStep = Math.min(
                snapshot.currentStep,
                Math.max(0, steps.length - 1),
            );
            setCurrentStep(restoredStep);
            setMaxStep(restoredStep);
        }
        setIsReady(true);
    }, [loaded, snapshot, setData, steps.length]);

    // Persist on every step or data change — but only after restore.
    // We save `maxStep` (not `currentStep`) so going back doesn't
    // reduce the checkpoint.
    useEffect(() => {
        if (!isReady) return;
        save(safeMaxStep, data);
    }, [safeMaxStep, data, save, isReady]);

    // Sync raw state back when the step list shrinks.
    //
    // The render-time clamp above keeps the UI from crashing during the
    // one frame where state still exceeds bounds. This effect writes
    // the clamped value into state so subsequent renders, persistence,
    // and the progress bar all read a consistent value.
    useEffect(() => {
        if (maxStep > steps.length - 1) {
            setMaxStep(Math.max(0, steps.length - 1));
        }
        if (currentStep > steps.length - 1) {
            setCurrentStep(Math.max(0, steps.length - 1));
        }
    }, [steps.length, maxStep, currentStep]);

    // Jump to the first step containing a server error.
    // Uses prefix matching so `partners.0.name` routes to the step
    // that owns the `partners` field.
    //
    // Fires only when `errors` (or `steps`) changes. Reading the
    // current step through a ref means navigating between steps does
    // not re-run this effect — that was the bug that made Next
    // bounce back to a step with a stale error.
    useEffect(() => {
        if (!errors || Object.keys(errors).length === 0) return;

        const errorKeys = Object.keys(errors);

        const failingIndex = steps.findIndex((s) =>
            s.fields.some((f) =>
                errorKeys.some((k) => k === f || k.startsWith(f + '.')),
            ),
        );

        if (failingIndex !== -1 && failingIndex !== currentStepRef.current) {
            setCurrentStep(failingIndex);
        }
    }, [errors, steps]);

    const validateCurrentStep = useCallback((): boolean => {
        const step = steps[safeCurrentStep];
        const next: Record<string, string> = {};

        if (step.validate) {
            Object.assign(next, step.validate(data as Record<string, unknown>));
        } else {
            const required = step.requiredFields ?? [];

            for (const field of required) {
                const value = data[field];
                const isEmpty =
                    value === '' ||
                    value === null ||
                    value === undefined ||
                    (Array.isArray(value) && value.length === 0);

                if (isEmpty) {
                    next[field] =
                        field
                            .replace(/_/g, ' ')
                            .replace(/\b\w/g, (c) => c.toUpperCase()) +
                        ' is required.';
                }
            }
        }

        setStepErrors(next);
        return Object.keys(next).length === 0;
    }, [steps, safeCurrentStep, data]);

    // Live re-validation — clears errors as the user fixes the fields.
    //
    // Runs only when errors are already visible. A fresh form with no
    // errors does not nag on every keystroke; errors only appear after
    // the user has attempted to advance (Next or Submit) and are then
    // re-evaluated as the user edits.
    //
    // `stepErrors` and `validateCurrentStep` are intentionally not in
    // the dependency list — we want this effect to fire on `data` or
    // `safeCurrentStep` changes only, not on every render that touches
    // those references. The guard below handles the "no visible
    // errors" case.
    useEffect(() => {
        if (Object.keys(stepErrors).length === 0) return;
        validateCurrentStep();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data, safeCurrentStep]);

    const handleNext = useCallback(() => {
        if (!validateCurrentStep()) return;
        if (safeCurrentStep < steps.length - 1) {
            const next = safeCurrentStep + 1;
            setCurrentStep(next);
            // Advance the checkpoint if we've gone further than before
            if (next > maxStep) {
                setMaxStep(next);
            }
        }
    }, [validateCurrentStep, safeCurrentStep, steps.length, maxStep]);

    const handleBack = useCallback(() => {
        if (safeCurrentStep > 0) {
            setCurrentStep(safeCurrentStep - 1);
            setStepErrors({});
        }
    }, [safeCurrentStep]);

    const handleStepJump = useCallback((index: number) => {
        setCurrentStep(index);
        setStepErrors({});
    }, []);

    const handleSubmit = useCallback(() => {
        if (!validateCurrentStep()) return;
        if (clearOnSubmit) clear();
        onSubmit();
    }, [validateCurrentStep, clear, onSubmit, clearOnSubmit]);

    const mergedErrors = useMemo(
        () => ({ ...errors, ...stepErrors }),
        [stepErrors, errors],
    );

    const isLast = safeCurrentStep === steps.length - 1;
    const hasErrors = Object.keys(mergedErrors).length > 0;

    return (
        <div className="flex flex-col gap-6">
            <WizardProgress
                steps={steps}
                currentStep={safeCurrentStep}
                maxReachedStep={safeMaxStep}
                onStepClick={handleStepJump}
            />

            {hasErrors && (
                <div
                    role="alert"
                    className="border-destructive/40 bg-destructive/10 flex items-start gap-2 rounded-lg border p-3"
                >
                    <AlertCircle className="text-destructive mt-0.5 h-4 w-4 shrink-0" />
                    <p className="text-destructive text-sm font-medium">
                        Please fix the highlighted fields before continuing.
                    </p>
                </div>
            )}

            <div className="glass-panel rounded-xl p-8">
                {children(steps[safeCurrentStep].id, mergedErrors)}
            </div>

            <div className="flex items-center justify-between">
                <Button
                    type="button"
                    variant="outline"
                    onClick={handleBack}
                    disabled={safeCurrentStep === 0 || processing}
                >
                    Back
                </Button>

                {isLast ? (
                    <Button
                        type="button"
                        onClick={handleSubmit}
                        disabled={processing}
                        className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                    >
                        {processing ? 'Saving...' : submitLabel}
                    </Button>
                ) : (
                    <Button type="button" onClick={handleNext}>
                        Next
                    </Button>
                )}
            </div>
        </div>
    );
}
