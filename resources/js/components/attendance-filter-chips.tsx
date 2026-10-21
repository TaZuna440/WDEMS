import { router } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export type AttendanceFilter = 'all' | 'unmarked' | 'present' | 'absent';

type Props = {
    active: AttendanceFilter;
    counts: Record<AttendanceFilter, number>;
    baseUrl: string;
    search: string;
    disabled?: boolean;
};

const ORDER: AttendanceFilter[] = ['all', 'present', 'absent'];

const LABELS: Record<AttendanceFilter, string> = {
    all: 'All',
    unmarked: 'Unmarked',
    present: 'Present',
    absent: 'Absent',
};

const ACTIVE_STYLES: Record<AttendanceFilter, string> = {
    all: 'bg-secondary text-foreground border-border',
    unmarked: 'bg-secondary text-foreground border-border',
    present:
        'bg-green-500/15 text-green-700 border-green-500/30 dark:text-green-400',
    absent: 'bg-destructive/15 text-destructive border-destructive/30',
};

export default function AttendanceFilterChips({
    active,
    counts,
    baseUrl,
    search,
    disabled = false,
}: Props) {
    const go = (filter: AttendanceFilter) => {
        if (disabled || filter === active) return;

        router.get(
            baseUrl,
            {
                ...(search !== '' ? { q: search } : {}),
                ...(filter !== 'all' ? { filter } : {}),
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ['rows', 'pagination', 'counts', 'filters'],
            },
        );
    };

    return (
        <div className="flex flex-wrap items-center gap-2">
            {ORDER.map((filter) => {
                const isActive = filter === active;
                const count = counts[filter] ?? 0;

                return (
                    <button
                        key={filter}
                        type="button"
                        onClick={() => go(filter)}
                        disabled={disabled}
                        className={cn(
                            'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition-colors',
                            isActive
                                ? ACTIVE_STYLES[filter]
                                : 'text-muted-foreground hover:text-foreground border-white/10 hover:border-white/20',
                            disabled && 'cursor-not-allowed opacity-50',
                        )}
                    >
                        <span>{LABELS[filter]}</span>
                        <span
                            className={cn(
                                'rounded-full px-1.5 py-0.5 text-[10px] tabular-nums',
                                isActive
                                    ? 'bg-black/20'
                                    : 'text-muted-foreground bg-white/5',
                            )}
                        >
                            {count}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}
