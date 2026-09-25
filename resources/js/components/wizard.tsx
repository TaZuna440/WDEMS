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
     * Complex rules (URL format, ranges, enums) are enforced server-side.
     */
    requiredFields?: string[];
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
    children: (stepId: string) => ReactNode;
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

    // Jump to the first step containing a server error
    useEffect(() => {
        if (!errors || Object.keys(errors).length === 0) return;

        const failingIndex = steps.findIndex((s) =>
            s.fields.some((f) => f in errors),
        );

        if (failingIndex !== -1 && failingIndex !== currentStep) {
            setCurrentStep(failingIndex);
        }
    }, [errors, steps, currentStep]);

    const validateCurrentStep = useCallback((): boolean => {
        const step = steps[currentStep];
        const required = step.requiredFields ?? [];
        const next: Record<string, string> = {};

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

        setStepErrors(next);
        return Object.keys(next).length === 0;
    }, [steps, currentStep, data]);

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
                {children(steps[currentStep].id)}
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
