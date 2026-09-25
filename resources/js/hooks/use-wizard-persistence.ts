import { useCallback, useState } from 'react';

const STORAGE_VERSION = 1;
const MAX_AGE_MS = 24 * 60 * 60 * 1000; // 24 hours

type PersistedState<T> = {
    version: number;
    currentStep: number;
    data: T;
    savedAt: string;
};

export type WizardSnapshot<T> = {
    currentStep: number;
    data: T;
};

function isBrowser(): boolean {
    return (
        typeof window !== 'undefined' &&
        typeof window.localStorage !== 'undefined'
    );
}

/**
 * Read the persisted snapshot, if one exists and is still valid.
 * Pure read — no side effects during render.
 */
function readSnapshot<T>(storageKey: string): WizardSnapshot<T> | null {
    if (!isBrowser()) return null;

    try {
        const raw = window.localStorage.getItem(storageKey);
        if (!raw) return null;

        const parsed = JSON.parse(raw) as PersistedState<T>;

        if (parsed.version !== STORAGE_VERSION) {
            return null;
        }

        const age = Date.now() - new Date(parsed.savedAt).getTime();
        if (age > MAX_AGE_MS) {
            return null;
        }

        return {
            currentStep: parsed.currentStep,
            data: parsed.data,
        };
    } catch {
        return null;
    }
}

/**
 * Persistence for the wizard. Reads the snapshot once on mount,
 * exposes `save` and `clear`. No banner, no manual restore —
 * the wizard applies the snapshot automatically.
 */
export function useWizardPersistence<T extends Record<string, unknown>>(
    storageKey: string,
) {
    // Lazy initializer — runs once, pure read from localStorage
    const [snapshot] = useState<WizardSnapshot<T> | null>(() =>
        readSnapshot<T>(storageKey),
    );

    const save = useCallback(
        (currentStep: number, data: T) => {
            if (!isBrowser()) return;

            try {
                const payload: PersistedState<T> = {
                    version: STORAGE_VERSION,
                    currentStep,
                    data,
                    savedAt: new Date().toISOString(),
                };
                window.localStorage.setItem(
                    storageKey,
                    JSON.stringify(payload),
                );
            } catch {
                // Quota exceeded or storage unavailable — silent no-op
            }
        },
        [storageKey],
    );

    const clear = useCallback(() => {
        if (!isBrowser()) return;
        try {
            window.localStorage.removeItem(storageKey);
        } catch {
            // ignore
        }
    }, [storageKey]);

    return { snapshot, save, clear } as const;
}
