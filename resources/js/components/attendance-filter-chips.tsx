import { router } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export type AttendanceFilter =
    | 'all'
    | 'unmarked'
    | 'present'
    | 'absent'
    | 'late'
    | 'excused';

type Props = {
    active: AttendanceFilter;
    counts: Record<AttendanceFilter, number>;
    baseUrl: string;
    search: string;
    disabled?: boolean;
};

const ORDER: AttendanceFilter[] = [
    'all',
    'unmarked',
    'present',
    'absent',
    'late',
    'excused',
];

const LABELS: Record<AttendanceFilter, string> = {
    all: 'All',
    unmarked: 'Unmarked',
    present: 'Present',
    absent: 'Absent',
    late: 'Late',
    excused: 'Excused',
};

const ACTIVE_STYLES: Record<AttendanceFilter, string> = {
    all: 'bg-white/10 text-foreground border-white/20',
    unmarked: 'bg-secondary text-foreground border-white/20',
    present: 'bg-green-500/15 text-green-400 border-green-500/30',
    absent: 'bg-destructive/15 text-destructive border-destructive/30',
    late: 'bg-yellow-500/15 text-yellow-500 border-yellow-500/30',
    excused: 'bg-blue-500/15 text-blue-400 border-blue-500/30',
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
                                : 'border-white/10 text-muted-foreground hover:border-white/20 hover:text-foreground',
                            disabled && 'cursor-not-allowed opacity-50',
                        )}
                    >
                        <span>{LABELS[filter]}</span>
                        <span
                            className={cn(
                                'rounded-full px-1.5 py-0.5 text-[10px] tabular-nums',
                                isActive
                                    ? 'bg-black/20'
                                    : 'bg-white/5 text-muted-foreground',
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
