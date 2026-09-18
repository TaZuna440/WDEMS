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
};

const HOURS = Array.from({ length: 12 }, (_, i) => i + 1);
const MINUTES = Array.from({ length: 12 }, (_, i) => i * 5);
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

    const snapped = Math.min(55, Math.round(m / 5) * 5);

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
}: Props) {
    const { hour, minute, period } = parse(value);

    const update = (h: number | null, m: number | null, p: Period) => {
        if (h === null || m === null) return;
        onChange(to24(h, m, p));
    };

    return (
        <div className={cn('flex items-center gap-1', className)}>
            <Select
                value={hour !== null ? String(hour) : undefined}
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
                value={minute !== null ? String(minute) : undefined}
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
        </div>
    );
}
