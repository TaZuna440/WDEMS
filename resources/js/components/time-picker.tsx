import { X } from 'lucide-react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

type Props = {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    className?: string;
    /**
     * When true, shows a small X button on the right whenever a value
     * is set. Clicking it clears the field back to its empty state.
     * Used for optional time fields (e.g. end_time in ScheduleStep).
     */
    clearable?: boolean;
};

const HOURS = Array.from({ length: 12 }, (_, i) => i + 1);

/**
 * Minutes are restricted to 15-minute intervals. Only four options are
 * offered — the user cannot pick an intermediate value, so there is no
 * snapping behavior to explain. Existing events with arbitrary minutes
 * remain valid on the server; the picker is the entry point for new
 * values only.
 */
const MINUTES = [0, 15, 30, 45];

type Period = 'AM' | 'PM';

function parse(value: string): {
    hour: number | null;
    minute: number | null;
    period: Period;
} {
    if (!value) return { hour: null, minute: null, period: 'AM' };

    const parts = value.split(':');
    if (parts.length !== 2) return { hour: null, minute: null, period: 'AM' };

    let h = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10);
    if (isNaN(h) || isNaN(m)) return { hour: null, minute: null, period: 'AM' };

    const period: Period = h >= 12 ? 'PM' : 'AM';
    if (h === 0) h = 12;
    else if (h > 12) h -= 12;

    // Snap legacy values to the nearest 15-minute interval, capped at 45.
    // New values come from the picker and are already on a 15-minute mark.
    const snapped = Math.min(45, Math.round(m / 15) * 15);

    return { hour: h, minute: snapped, period };
}

function to24(hour: number, minute: number, period: Period): string {
    let h = hour;
    if (period === 'AM' && hour === 12) h = 0;
    else if (period === 'PM' && hour !== 12) h = hour + 12;

    return `${String(h).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
}

export default function TimePicker({
    id,
    value,
    onChange,
    disabled,
    className,
    clearable = false,
}: Props) {
    const { hour, minute, period } = parse(value);

    const update = (h: number | null, m: number | null, p: Period) => {
        if (h === null || m === null) return;
        onChange(to24(h, m, p));
    };

    const hasValue = value !== '';

    return (
        <div className={cn('flex items-center gap-1', className)}>
            <Select
                value={hour !== null ? String(hour) : ''}
                onValueChange={(v) => update(parseInt(v, 10), minute ?? 0, period)}
                disabled={disabled}
            >
                <SelectTrigger id={id} className="h-9 flex-1 px-2">
                    <SelectValue placeholder="--" />
                </SelectTrigger>
                <SelectContent>
                    {HOURS.map((h) => (
                        <SelectItem key={h} value={String(h)}>
                            {h}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <span className="text-sm text-muted-foreground">:</span>

            <Select
                value={minute !== null ? String(minute) : ''}
                onValueChange={(v) => update(hour ?? 12, parseInt(v, 10), period)}
                disabled={disabled}
            >
                <SelectTrigger className="h-9 flex-1 px-2">
                    <SelectValue placeholder="--" />
                </SelectTrigger>
                <SelectContent>
                    {MINUTES.map((m) => (
                        <SelectItem key={m} value={String(m)}>
                            {String(m).padStart(2, '0')}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select
                value={period}
                onValueChange={(v) => update(hour ?? 12, minute ?? 0, v as Period)}
                disabled={disabled}
            >
                <SelectTrigger className="h-9 w-20 shrink-0 px-2">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="AM">AM</SelectItem>
                    <SelectItem value="PM">PM</SelectItem>
                </SelectContent>
            </Select>

            {clearable && hasValue && (
                <button
                    type="button"
                    onClick={() => onChange('')}
                    disabled={disabled}
                    aria-label="Clear time"
                    title="Clear time"
                    className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-white/10 hover:text-foreground disabled:pointer-events-none disabled:opacity-50"
                >
                    <X className="h-4 w-4" />
                </button>
            )}
        </div>
    );
}
