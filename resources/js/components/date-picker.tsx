import { useEffect, useRef, useState } from 'react';
import {
    Calendar as CalendarIcon,
    ChevronLeft,
    ChevronRight,
    X,
} from 'lucide-react';
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
    /**
     * When true, the typed-entry input inside the popover receives
     * focus when the popover opens. Useful for flows where typed
     * entry is the primary interaction (e.g. date of birth).
     *
     * Default false. The trigger still opens the popover; the user
     * can click the calendar or the input.
     */
    autoFocusTyped?: boolean;
};

const MONTHS = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
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

function formatTyped(d: Date): string {
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const y = d.getFullYear();
    return `${m}/${day}/${y}`;
}

function makeDate(y: number, m: number, d: number): Date | null {
    if (m < 1 || m > 12) return null;
    if (d < 1 || d > 31) return null;
    const date = new Date(y, m - 1, d);
    if (
        date.getFullYear() !== y ||
        date.getMonth() !== m - 1 ||
        date.getDate() !== d
    ) {
        return null;
    }
    return date;
}

function parseTyped(raw: string): Date | null {
    const trimmed = raw.trim();
    if (trimmed === '') return null;

    // Accept ISO: YYYY-MM-DD
    const iso = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(trimmed);
    if (iso) {
        return makeDate(Number(iso[1]), Number(iso[2]), Number(iso[3]));
    }

    // Accept M/D/YYYY with / - . or whitespace separators.
    // Two-digit years are rejected: guessing between 1900s and
    // 2000s is unsafe on a shared picker used for both birthdates
    // and event dates.
    const mdy = /^(\d{1,2})[/\-.\s](\d{1,2})[/\-.\s](\d{4})$/.exec(trimmed);
    if (mdy) {
        return makeDate(Number(mdy[3]), Number(mdy[1]), Number(mdy[2]));
    }

    return null;
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
    autoFocusTyped = false,
}: Props) {
    const [open, setOpen] = useState(false);
    const [viewDate, setViewDate] = useState<Date>(
        () => parseISO(value) ?? new Date(),
    );
    const [view, setView] = useState<'days' | 'months' | 'years'>('days');
    const [yearPage, setYearPage] = useState(0);
    const [typedValue, setTypedValue] = useState('');
    const [typedError, setTypedError] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);
    const typedInputRef = useRef<HTMLInputElement>(null);
    const hadCommitError = useRef(false);

    const selected = parseISO(value);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    useEffect(() => {
        const parsed = parseISO(value);
        if (parsed) setViewDate(parsed);
    }, [value]);

    // Sync typed-entry field with the current value each time the
    // popover opens. On close, clear the error and the pending-error
    // flag so the next open starts clean.
    useEffect(() => {
        if (open) {
            setTypedValue(selected ? formatTyped(selected) : '');
            setTypedError('');
            hadCommitError.current = false;
            setView('days');
            setYearPage(0);
            if (autoFocusTyped) {
                requestAnimationFrame(() => typedInputRef.current?.focus());
            }
        } else {
            setTypedError('');
            hadCommitError.current = false;
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    useEffect(() => {
        if (!open) return;
        const handler = (e: MouseEvent) => {
            if (
                containerRef.current &&
                !containerRef.current.contains(e.target as Node)
            ) {
                // If the user typed something invalid and the commit
                // failed, the error is showing. Do not close — the
                // user needs to see and fix it.
                if (hadCommitError.current) return;
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
        cells.push({
            date: new Date(year, month - 1, daysInPrevMonth - i),
            inMonth: false,
        });
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

    const boundsError = (parsed: Date): string | null => {
        if (minDate && isBeforeDay(parsed, minDate)) {
            return `Date must be on or after ${formatDisplay(minDate)}.`;
        }
        if (maxDate && isAfterDay(parsed, maxDate)) {
            return `Date must be on or before ${formatDisplay(maxDate)}.`;
        }
        return null;
    };

    const commitTyped = (): boolean => {
        if (typedValue.trim() === '') {
            setTypedError('');
            hadCommitError.current = false;
            return true;
        }
        const parsed = parseTyped(typedValue);
        if (parsed === null) {
            setTypedError('Enter a valid date as MM/DD/YYYY.');
            hadCommitError.current = true;
            return false;
        }
        const rangeError = boundsError(parsed);
        if (rangeError !== null) {
            setTypedError(rangeError);
            hadCommitError.current = true;
            return false;
        }
        setTypedError('');
        hadCommitError.current = false;
        onChange(toISO(parsed));
        setOpen(false);
        return true;
    };

    const onTypedChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setTypedValue(e.target.value);
        if (typedError !== '') setTypedError('');
        hadCommitError.current = false;
    };

    const onTypedKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            commitTyped();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            setOpen(false);
        }
    };

    const onTypedBlur = (e: React.FocusEvent<HTMLInputElement>) => {
        // If focus moves to another element inside the picker
        // (calendar cell, Today button, Prev/Next), do not commit.
        // The click that follows will do the intended thing.
        const next = e.relatedTarget as Node | null;
        if (next !== null && containerRef.current?.contains(next)) {
            return;
        }
        if (typedValue.trim() !== '') {
            commitTyped();
        }
    };

    const toggleOpen = () => {
        if (disabled) return;
        if (open) {
            // If there is a pending typed value, try to commit it
            // first. On success, commitTyped closes the popover. On
            // failure, the error is shown and the popover stays open.
            if (typedValue.trim() !== '') {
                commitTyped();
                return;
            }
            setOpen(false);
            return;
        }
        setOpen(true);
    };

    return (
        <div ref={containerRef} className={cn('relative', className)}>
            <button
                type="button"
                id={id}
                onClick={toggleOpen}
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
                    <CalendarIcon className="text-muted-foreground h-4 w-4 shrink-0" />
                    {selected ? formatDisplay(selected) : placeholder}
                </span>
                {selected && (
                    <span
                        role="button"
                        tabIndex={-1}
                        onClick={clear}
                        title="Clear date"
                        className="text-muted-foreground hover:text-foreground shrink-0 rounded p-0.5 transition-colors hover:bg-white/10"
                    >
                        <X className="h-3 w-3" />
                    </span>
                )}
            </button>

            {open && (
                <div
                    className="bg-popover text-popover-foreground absolute top-full left-0 z-50 mt-1 w-72 rounded-lg border border-white/10 p-3 shadow-2xl"
                    role="dialog"
                >
                    <div className="mb-2">
                        <input
                            ref={typedInputRef}
                            type="text"
                            inputMode="numeric"
                            value={typedValue}
                            onChange={onTypedChange}
                            onKeyDown={onTypedKeyDown}
                            onBlur={onTypedBlur}
                            placeholder="MM/DD/YYYY"
                            aria-label="Enter date as MM/DD/YYYY"
                            aria-invalid={!!typedError}
                            className={cn(
                                'border-input bg-input flex h-8 w-full rounded-md border px-2 text-sm shadow-xs transition-[color,box-shadow]',
                                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:outline-none',
                                typedError !== '' && 'border-destructive',
                            )}
                        />
                        {typedError !== '' && (
                            <p
                                role="alert"
                                className="text-destructive mt-1 text-xs"
                            >
                                {typedError}
                            </p>
                        )}
                    </div>

                    {view === 'days' && (
                        <div className="mb-2 flex items-center justify-between">
                            <button
                                type="button"
                                onClick={() =>
                                    setViewDate(new Date(year, month - 1, 1))
                                }
                                aria-label="Previous month"
                                className="text-muted-foreground hover:text-foreground flex h-7 w-7 items-center justify-center rounded-md transition-colors hover:bg-white/5"
                            >
                                <ChevronLeft className="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    setView('years');
                                    setYearPage(0);
                                }}
                                aria-label="Choose year"
                                className="hover:text-lime-brand rounded-md px-2 py-1 text-sm font-medium transition-colors hover:bg-white/5"
                            >
                                {MONTHS[month]} {year}
                            </button>
                            <button
                                type="button"
                                onClick={() =>
                                    setViewDate(new Date(year, month + 1, 1))
                                }
                                aria-label="Next month"
                                className="text-muted-foreground hover:text-foreground flex h-7 w-7 items-center justify-center rounded-md transition-colors hover:bg-white/5"
                            >
                                <ChevronRight className="h-4 w-4" />
                            </button>
                        </div>
                    )}

                    {view === 'years' && (
                        <div className="mb-2">
                            <div className="mb-2 flex items-center justify-between">
                                <button
                                    type="button"
                                    onClick={() => setYearPage((p) => p - 1)}
                                    aria-label="Previous years"
                                    className="text-muted-foreground hover:text-foreground flex h-7 w-7 items-center justify-center rounded-md transition-colors hover:bg-white/5"
                                >
                                    <ChevronLeft className="h-4 w-4" />
                                </button>
                                <span className="text-sm font-medium">
                                    {year - yearPage * 12 - 11} –{' '}
                                    {year - yearPage * 12}
                                </span>
                                <button
                                    type="button"
                                    onClick={() =>
                                        setYearPage((p) => Math.max(0, p - 1))
                                    }
                                    aria-label="Next years"
                                    disabled={yearPage <= 0}
                                    className="text-muted-foreground hover:text-foreground flex h-7 w-7 items-center justify-center rounded-md transition-colors hover:bg-white/5 disabled:opacity-30"
                                >
                                    <ChevronRight className="h-4 w-4" />
                                </button>
                            </div>
                            <div className="grid grid-cols-3 gap-1">
                                {Array.from({ length: 12 }, (_, i) => {
                                    const y = year - yearPage * 12 - 11 + i;
                                    const isCurrentYear = y === year;
                                    const outOfBounds: boolean =
                                        (minDate !== null &&
                                            y < minDate.getFullYear()) ||
                                        (maxDate !== null &&
                                            y > maxDate.getFullYear());
                                    return (
                                        <button
                                            key={y}
                                            type="button"
                                            disabled={outOfBounds}
                                            onClick={() => {
                                                setViewDate(
                                                    new Date(y, month, 1),
                                                );
                                                setView('months');
                                            }}
                                            className={cn(
                                                'flex h-10 items-center justify-center rounded-md text-sm transition-colors',
                                                outOfBounds &&
                                                    'text-muted-foreground/30 cursor-not-allowed',
                                                !outOfBounds &&
                                                    'text-foreground hover:bg-white/5',
                                                !outOfBounds &&
                                                    isCurrentYear &&
                                                    'bg-lime-brand text-navy-900 hover:bg-lime-brand/90 font-medium',
                                            )}
                                        >
                                            {y}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {view === 'months' && (
                        <div className="mb-2">
                            <div className="mb-2 flex items-center justify-between">
                                <button
                                    type="button"
                                    onClick={() => setView('years')}
                                    aria-label="Back to years"
                                    className="text-muted-foreground hover:text-foreground flex h-7 w-7 items-center justify-center rounded-md transition-colors hover:bg-white/5"
                                >
                                    <ChevronLeft className="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setView('years');
                                        setYearPage(0);
                                    }}
                                    className="hover:text-lime-brand rounded-md px-2 py-1 text-sm font-medium transition-colors hover:bg-white/5"
                                >
                                    {year}
                                </button>
                                <span className="w-7" />
                            </div>
                            <div className="grid grid-cols-3 gap-1">
                                {MONTHS.map((m, i) => {
                                    const isCurrentMonth = i === month;
                                    const monthStart = new Date(year, i, 1);
                                    const monthEnd = new Date(year, i + 1, 0);
                                    const outOfBounds: boolean =
                                        (minDate !== null &&
                                            isAfterDay(minDate, monthEnd)) ||
                                        (maxDate !== null &&
                                            isBeforeDay(maxDate, monthStart));
                                    return (
                                        <button
                                            key={m}
                                            type="button"
                                            disabled={outOfBounds}
                                            onClick={() => {
                                                setViewDate(
                                                    new Date(year, i, 1),
                                                );
                                                setView('days');
                                            }}
                                            className={cn(
                                                'flex h-10 items-center justify-center rounded-md text-sm transition-colors',
                                                outOfBounds &&
                                                    'text-muted-foreground/30 cursor-not-allowed',
                                                !outOfBounds &&
                                                    'text-foreground hover:bg-white/5',
                                                !outOfBounds &&
                                                    isCurrentMonth &&
                                                    'bg-lime-brand text-navy-900 hover:bg-lime-brand/90 font-medium',
                                            )}
                                        >
                                            {m.slice(0, 3)}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {view === 'days' && (
                        <>
                            <div className="mb-1 grid grid-cols-7 gap-0.5">
                                {WEEKDAYS.map((wd) => (
                                    <div
                                        key={wd}
                                        className="text-muted-foreground flex h-7 items-center justify-center text-[10px] font-medium tracking-wider uppercase"
                                    >
                                        {wd}
                                    </div>
                                ))}
                            </div>

                            <div className="grid grid-cols-7 gap-0.5">
                                {cells.map((cell, i) => {
                                    const isSelected =
                                        selected &&
                                        cell.date.toDateString() ===
                                            selected.toDateString();
                                    const isToday =
                                        cell.date.toDateString() ===
                                        today.toDateString();
                                    const isDisabled = isCellDisabled(
                                        cell.date,
                                    );

                                    return (
                                        <button
                                            key={i}
                                            type="button"
                                            disabled={isDisabled}
                                            onClick={() => pick(cell.date)}
                                            className={cn(
                                                'flex h-8 items-center justify-center rounded-md text-xs transition-colors',
                                                isDisabled &&
                                                    'text-muted-foreground/30 cursor-not-allowed',
                                                !isDisabled &&
                                                    !cell.inMonth &&
                                                    'text-muted-foreground/40 hover:bg-white/[0.03]',
                                                !isDisabled &&
                                                    cell.inMonth &&
                                                    'text-foreground hover:bg-white/5',
                                                !isDisabled &&
                                                    isToday &&
                                                    !isSelected &&
                                                    'border-lime-brand/40 text-lime-brand border',
                                                !isDisabled &&
                                                    isSelected &&
                                                    'bg-lime-brand text-navy-900 hover:bg-lime-brand/90 font-medium',
                                            )}
                                        >
                                            {cell.date.getDate()}
                                        </button>
                                    );
                                })}
                            </div>
                        </>
                    )}
                    <div className="mt-2 flex items-center justify-between border-t border-white/5 pt-2">
                        <button
                            type="button"
                            onClick={goToday}
                            disabled={!canPickToday}
                            className={cn(
                                'text-xs font-medium transition-opacity',
                                canPickToday
                                    ? 'text-lime-brand hover:opacity-80'
                                    : 'text-muted-foreground/40 cursor-not-allowed',
                            )}
                        >
                            Today
                        </button>
                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            className="text-muted-foreground hover:text-foreground text-xs transition-colors"
                        >
                            Close
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
