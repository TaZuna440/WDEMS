import { Check } from 'lucide-react';

export type WizardStep = {
    id: string;
    label: string;
};

type Props = {
    steps: WizardStep[];
    currentStep: number;
    maxReachedStep: number;
    onStepClick?: (index: number) => void;
};

/**
 * Wizard step indicator.
 *
 * Step pills use a mode-aware card surface so they read as solid
 * chips over the page image in both light and dark modes. The
 * active step keeps its lime fill. Visited steps keep the lime
 * checkmark. Everything else uses --card / --secondary / --border
 * so it flips with the mode rather than relying on hardcoded
 * white utilities.
 */
export default function WizardProgress({
    steps,
    currentStep,
    maxReachedStep,
    onStepClick,
}: Props) {
    return (
        <div className="flex items-center justify-center gap-1 sm:gap-2">
            {steps.map((step, index) => {
                const isVisited = index < maxReachedStep;
                const isCurrent = index === currentStep;
                const isReachable = index <= maxReachedStep;
                const isLocked = !isReachable;

                return (
                    <div
                        key={step.id}
                        className="flex items-center gap-1 sm:gap-2"
                    >
                        {index > 0 && (
                            <div
                                className={`h-px w-3 sm:w-8 ${
                                    isVisited || isCurrent
                                        ? 'bg-lime-brand'
                                        : 'bg-border'
                                }`}
                            />
                        )}

                        <button
                            type="button"
                            disabled={isLocked}
                            onClick={() => !isLocked && onStepClick?.(index)}
                            className={`flex items-center gap-2 rounded-full px-2 py-1.5 text-xs font-medium shadow-xs backdrop-blur-sm transition-all sm:px-3 ${
                                isCurrent
                                    ? 'bg-lime-brand text-navy-900'
                                    : isLocked
                                      ? 'bg-card/50 text-muted-foreground cursor-not-allowed opacity-60'
                                      : 'bg-card/80 text-foreground hover:bg-card'
                            }`}
                            aria-current={isCurrent ? 'step' : undefined}
                        >
                            <span
                                className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold ${
                                    isCurrent
                                        ? 'bg-navy-900 text-lime-brand'
                                        : isVisited
                                          ? 'bg-lime-brand text-navy-900'
                                          : 'bg-secondary text-muted-foreground'
                                }`}
                            >
                                {isVisited ? (
                                    <Check className="h-3 w-3" />
                                ) : (
                                    index + 1
                                )}
                            </span>
                            <span className="hidden sm:inline">
                                {step.label}
                            </span>
                        </button>
                    </div>
                );
            })}
        </div>
    );
}
