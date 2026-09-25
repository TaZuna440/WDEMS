import { useEffect, useRef, useState } from 'react';
import { Calendar as CalendarIcon, ChevronLeft, ChevronRight, X } from 'lucide-react';
import { cn } from '@/lib/utils';

type Props = {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    disabled?: boolean;
    className?: string;
    /**
     * Earliest selectable date (inclusive). Days before this are
     * rendered disabled. Pass a Date at local midnight.
     */
    minDate?: Date | null;
    /**
     * Latest selectable date (inclusive). Days after this are rendered
     * disabled. Pass a Date at local midnight.
     */
    maxDate?: Date | null;
};

const MONTHS = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];
const WEEKDAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

function parseISO(iso: string): Date | null {
    if (!iso) return null;
    const parts = iso.split('-');
    if (parts.length !== 3) return null;
    const [y, m, d] = parts.map(Number);
    if (!y || !m || !d) return null;
    return new Date(y, m - 1, d);
}

function toISO(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function formatDisplay(d: Date): string {
    return d.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function isBeforeDay(a: Date, b: Date): boolean {
    const a0 = new Date(a);
    const b0 = new Date(b);
    a0.setHours(0, 0, 0, 0);
    b0.setHours(0, 0, 0, 0);
    return a0 < b0;
}

function isAfterDay(a: Date, b: Date): boolean {
    const a0 = new Date(a);
    const b0 = new Date(b);
    a0.setHours(0, 0, 0, 0);
    b0.setHours(0, 0, 0, 0);
    return a0 > b0;
}

export default function DatePicker({
    id,
    value,
    onChange,
    placeholder = 'Pick a date',
    disabled,
    className,
    minDate = null,
    maxDate = null,
}: Props) {
    const [open, setOpen] = useState(false);
    const [viewDate, setViewDate] = useState<Date>(() => parseISO(value) ?? new Date());
    const containerRef = useRef<HTMLDivElement>(null);

    const selected = parseISO(value);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    useEffect(() => {
        const parsed = parseISO(value);
        if (parsed) setViewDate(parsed);
    }, [value]);

    useEffect(() => {
        if (!open) return;
        const handler = (e: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
                setOpen(false);
            }
        };
        const keyHandler = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setOpen(false);
        };
        document.addEventListener('mousedown', handler);
        document.addEventListener('keydown', keyHandler);
        return () => {
            document.removeEventListener('mousedown', handler);
            document.removeEventListener('keydown', keyHandler);
        };
    }, [open]);

    const year = viewDate.getFullYear();
    const month = viewDate.getMonth();
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrevMonth = new Date(year, month, 0).getDate();

    const cells: { date: Date; inMonth: boolean }[] = [];
    for (let i = firstDay - 1; i >= 0; i--) {
        cells.push({ date: new Date(year, month - 1, daysInPrevMonth - i), inMonth: false });
    }
    for (let d = 1; d <= daysInMonth; d++) {
        cells.push({ date: new Date(year, month, d), inMonth: true });
    }
    const remaining = 42 - cells.length;
    for (let d = 1; d <= remaining; d++) {
        cells.push({ date: new Date(year, month + 1, d), inMonth: false });
    }

    const isCellDisabled = (d: Date): boolean => {
        if (minDate && isBeforeDay(d, minDate)) return true;
        if (maxDate && isAfterDay(d, maxDate)) return true;
        return false;
    };

    const pick = (d: Date) => {
        if (isCellDisabled(d)) return;
        onChange(toISO(d));
        setOpen(false);
    };

    const clear = (e: React.MouseEvent) => {
        e.stopPropagation();
        onChange('');
    };

    const canPickToday =
        (!minDate || !isBeforeDay(today, minDate)) &&
        (!maxDate || !isAfterDay(today, maxDate));

    const goToday = () => {
        if (!canPickToday) return;
        const t = new Date();
        setViewDate(t);
        onChange(toISO(t));
        setOpen(false);
    };

    return (
        <div ref={containerRef} className={cn('relative', className)}>
            <button
                type="button"
                id={id}
                onClick={() => !disabled && setOpen((v) => !v)}
                disabled={disabled}
                aria-haspopup="dialog"
                aria-expanded={open}
                className={cn(
                    'border-input bg-input flex h-9 w-full items-center justify-between gap-2 rounded-md border px-3 py-1 text-sm shadow-xs transition-[color,box-shadow]',
                    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:outline-none',
                    'disabled:cursor-not-allowed disabled:opacity-50',
                    open && 'border-ring ring-ring/50 ring-[3px]',
                )}
            >
                <span
                    className={cn(
                        'flex min-w-0 flex-1 items-center gap-2 truncate text-left',
                        !selected && 'text-muted-foreground',
                    )}
                >
                    <CalendarIcon className="h-4 w-4 shrink-0 text-muted-foreground" />
                    {selected ? formatDisplay(selected) : placeholder}
                </span>
                {selected && (
                    <span
                        role="button"
                        tabIndex={-1}
                        onClick={clear}
                        title="Clear date"
                        className="shrink-0 rounded p-0.5 text-muted-foreground transition-colors hover:bg-white/10 hover:text-foreground"
                    >
                        <X className="h-3 w-3" />
                    </span>
                )}
            </button>

            {open && (
                <div
                    className="bg-popover text-popover-foreground absolute left-0 top-full z-50 mt-1 w-72 rounded-lg border border-white/10 p-3 shadow-2xl"
                    role="dialog"
                >
                    <div className="mb-2 flex items-center justify-between">
                        <button
                            type="button"
                            onClick={() => setViewDate(new Date(year, month - 1, 1))}
                            aria-label="Previous month"
                            className="flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-white/5 hover:text-foreground"
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </button>
                        <span className="text-sm font-medium">
                            {MONTHS[month]} {year}
                        </span>
                        <button
                            type="button"
                            onClick={() => setViewDate(new Date(year, month + 1, 1))}
                            aria-label="Next month"
                            className="flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-white/5 hover:text-foreground"
                        >
                            <ChevronRight className="h-4 w-4" />
                        </button>
                    </div>

                    <div className="mb-1 grid grid-cols-7 gap-0.5">
                        {WEEKDAYS.map((wd) => (
                            <div
                                key={wd}
                                className="flex h-7 items-center justify-center text-[10px] font-medium uppercase tracking-wider text-muted-foreground"
                            >
                                {wd}
                            </div>
                        ))}
                    </div>

                    <div className="grid grid-cols-7 gap-0.5">
                        {cells.map((cell, i) => {
                            const isSelected =
                                selected &&
                                cell.date.toDateString() === selected.toDateString();
                            const isToday =
                                cell.date.toDateString() === today.toDateString();
                            const isDisabled = isCellDisabled(cell.date);

                            return (
                                <button
                                    key={i}
                                    type="button"
                                    disabled={isDisabled}
                                    onClick={() => pick(cell.date)}
                                    className={cn(
                                        'flex h-8 items-center justify-center rounded-md text-xs transition-colors',
                                        isDisabled &&
                                            'cursor-not-allowed text-muted-foreground/30',
                                        !isDisabled && !cell.inMonth &&
                                            'text-muted-foreground/40 hover:bg-white/[0.03]',
                                        !isDisabled && cell.inMonth &&
                                            'text-foreground hover:bg-white/5',
                                        !isDisabled && isToday && !isSelected &&
                                            'border border-lime-brand/40 text-lime-brand',
                                        !isDisabled && isSelected &&
                                            'bg-lime-brand font-medium text-navy-900 hover:bg-lime-brand/90',
                                    )}
                                >
                                    {cell.date.getDate()}
                                </button>
                            );
                        })}
                    </div>

                    <div className="mt-2 flex items-center justify-between border-t border-white/5 pt-2">
                        <button
                            type="button"
                            onClick={goToday}
                            disabled={!canPickToday}
                            className={cn(
                                'text-xs font-medium transition-opacity',
                                canPickToday
                                    ? 'text-lime-brand hover:opacity-80'
                                    : 'cursor-not-allowed text-muted-foreground/40',
                            )}
                        >
                            Today
                        </button>
                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            className="text-xs text-muted-foreground transition-colors hover:text-foreground"
                        >
                            Close
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
