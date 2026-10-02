import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

type Options = {
    /** Interval in milliseconds. */
    interval: number;
    /**
     * Inertia partial-reload keys — only these props are refreshed.
     * Pass a stable array reference (module-level const) so the
     * callback memoization holds across renders.
     */
    only: string[];
    /** Set false to disable polling entirely. */
    enabled?: boolean;
};

/**
 * Poll the current Inertia page on an interval.
 *
 * Behavior:
 *   - Fires `router.reload({ only })` every `interval` ms.
 *   - Skips the tick when `document.visibilityState === 'hidden'`.
 *   - Fires an immediate refresh when the tab regains focus.
 *   - Serializes in-flight requests — a slow reload does not stack.
 *   - Cleans up on unmount.
 *
 * Returns:
 *   - `lastRefreshed` — Date of the last successful refresh (or mount).
 *   - `isRefreshing` — true while a reload is in flight.
 *   - `refresh()` — fire a refresh manually.
 *
 * The `only` array must be a stable reference. A new array on every
 * render re-creates the refresh callback and re-runs the effect,
 * resetting the interval.
 */
export function usePolling({ interval, only, enabled = true }: Options) {
    const [lastRefreshed, setLastRefreshed] = useState<Date>(() => new Date());
    const [isRefreshing, setIsRefreshing] = useState(false);
    const inFlightRef = useRef(false);
    const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

    // Stringify `only` so the callback is stable even if the caller
    // passes a literal array. The string is split back on use.
    const onlyKey = only.join(',');

    const refresh = useCallback(() => {
        if (inFlightRef.current) return;

        inFlightRef.current = true;
        setIsRefreshing(true);

        router.reload({
            only: onlyKey.split(','),
            onFinish: () => {
                inFlightRef.current = false;
                setIsRefreshing(false);
                setLastRefreshed(new Date());
            },
        });
    }, [onlyKey]);

    useEffect(() => {
        if (!enabled) return;

        const tick = () => {
            if (document.visibilityState === 'hidden') return;
            refresh();
        };

        timerRef.current = setInterval(tick, interval);

        const onVisibility = () => {
            if (document.visibilityState === 'visible') {
                refresh();
            }
        };

        document.addEventListener('visibilitychange', onVisibility);

        return () => {
            if (timerRef.current) clearInterval(timerRef.current);
            document.removeEventListener('visibilitychange', onVisibility);
        };
    }, [interval, enabled, refresh]);

    return { lastRefreshed, isRefreshing, refresh };
}
