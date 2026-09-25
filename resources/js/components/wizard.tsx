import { AlertCircle } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useMemo,
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
    /**
     * Render function for the current step.
     *
     * The second argument carries the merged errors for the current
     * step — client-side validation errors from this wizard, combined
     * with server-side errors from the parent form. Pass these down to
     * the step component so its `<InputError>` slots actually fire.
     */
    children: (
        stepId: string,
        errors: Record<string, string>,
    ) => ReactNode;
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
    children,
}: Props<T>) {
    const { snapshot, save, clear } = useWizardPersistence<T>(storageKey);

    // The furthest step the user has reached (used as the checkpoint)
    const [maxStep, setMaxStep] = useState(snapshot?.currentStep ?? 0);
    // The step the user is currently viewing
    const [currentStep, setCurrentStep] = useState(snapshot?.currentStep ?? 0);
    const [stepErrors, setStepErrors] = useState<Record<string, string>>({});
    const [isReady, setIsReady] = useState(false);

    // Apply snapshot once on mount, then enable saving.
    // The guard prevents the initial save effect from clobbering the
    // stored draft with the parent form's empty defaults.
    useEffect(() => {
        if (snapshot) {
            Object.entries(snapshot.data).forEach(([key, value]) => {
                setData(key, value);
            });
        }
        setIsReady(true);
        // Intentionally run once on mount
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Persist on every step or data change — but only after restore.
    // We save `maxStep` (not `currentStep`) so going back doesn't
    // reduce the checkpoint.
    useEffect(() => {
        if (!isReady) return;
        save(maxStep, data);
    }, [maxStep, data, save, isReady]);

    // Jump to the first step containing a server error.
    // Uses prefix matching so `partners.0.name` routes to the step
    // that owns the `partners` field.
    useEffect(() => {
        if (!errors || Object.keys(errors).length === 0) return;

        const errorKeys = Object.keys(errors);

        const failingIndex = steps.findIndex((s) =>
            s.fields.some((f) =>
                errorKeys.some((k) => k === f || k.startsWith(f + '.')),
            ),
        );

        if (failingIndex !== -1 && failingIndex !== currentStep) {
            setCurrentStep(failingIndex);
        }
    }, [errors, steps, currentStep]);

    const validateCurrentStep = useCallback((): boolean => {
        const step = steps[currentStep];
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
    }, [steps, currentStep, data]);

    // Live re-validation — clears errors as the user fixes the fields.
    //
    // Runs only when errors are already visible. A fresh form with no
    // errors does not nag on every keystroke; errors only appear after
    // the user has attempted to advance (Next or Submit) and are then
    // re-evaluated as the user edits.
    //
    // `stepErrors` and `validateCurrentStep` are intentionally not in
    // the dependency list — we want this effect to fire on `data` or
    // `currentStep` changes only, not on every render that touches
    // those references. The guard below handles the "no visible
    // errors" case.
    useEffect(() => {
        if (Object.keys(stepErrors).length === 0) return;
        validateCurrentStep();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data, currentStep]);

    const handleNext = useCallback(() => {
        if (!validateCurrentStep()) return;
        if (currentStep < steps.length - 1) {
            const next = currentStep + 1;
            setCurrentStep(next);
            // Advance the checkpoint if we've gone further than before
            if (next > maxStep) {
                setMaxStep(next);
            }
        }
    }, [validateCurrentStep, currentStep, steps.length, maxStep]);

    const handleBack = useCallback(() => {
        if (currentStep > 0) {
            setCurrentStep(currentStep - 1);
            setStepErrors({});
        }
    }, [currentStep]);

    const handleStepJump = useCallback((index: number) => {
        setCurrentStep(index);
        setStepErrors({});
    }, []);

    const handleSubmit = useCallback(() => {
        if (!validateCurrentStep()) return;
        clear();
        onSubmit();
    }, [validateCurrentStep, clear, onSubmit]);

    const mergedErrors = useMemo(
        () => ({ ...stepErrors, ...errors }),
        [stepErrors, errors],
    );

    const isLast = currentStep === steps.length - 1;
    const hasErrors = Object.keys(mergedErrors).length > 0;

    return (
        <div className="flex flex-col gap-6">
            <WizardProgress
                steps={steps}
                currentStep={currentStep}
                maxReachedStep={maxStep}
                onStepClick={handleStepJump}
            />

            {hasErrors && (
                <div
                    role="alert"
                    className="flex items-start gap-2 rounded-lg border border-destructive/40 bg-destructive/10 p-3"
                >
                    <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-destructive" />
                    <p className="text-sm font-medium text-destructive">
                        Please fix the highlighted fields before continuing.
                    </p>
                </div>
            )}

            <div className="glass-panel rounded-xl p-8">
                {children(steps[currentStep].id, mergedErrors)}
            </div>

            <div className="flex items-center justify-between">
                <Button
                    type="button"
                    variant="outline"
                    onClick={handleBack}
                    disabled={currentStep === 0 || processing}
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
