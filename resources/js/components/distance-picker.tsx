import { ChevronDown } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

export type DistanceUnit = 'km' | 'mi';

type Props = {
    id?: string;
    value: number | string | null;
    unit: DistanceUnit;
    onChange: (value: number | null, unit: DistanceUnit) => void;
    disabled?: boolean;
    className?: string;
};

const KM_TO_MI = 0.621371;

const WHOLE_OPTIONS = Array.from({ length: 101 }, (_, i) => String(i));
const DECIMAL_OPTIONS = Array.from({ length: 10 }, (_, i) => String(i));

function toMiles(km: number): number {
    return Math.round(km * KM_TO_MI * 100) / 100;
}

function toKm(mi: number): number {
    return Math.round((mi / KM_TO_MI) * 100) / 100;
}

function splitValue(value: number | string | null): {
    whole: string;
    decimal: string;
} {
    if (value === null || value === undefined || value === '') {
        return { whole: '', decimal: '' };
    }
    const num = typeof value === 'string' ? parseFloat(value) : value;
    if (isNaN(num)) return { whole: '', decimal: '' };

    const rounded = Math.round(num * 100) / 100;
    const str = rounded.toFixed(2);
    const [w, d] = str.split('.');

    // Strip trailing zero from decimal (5.00 → '', 5.50 → '5', 5.55 → '55')
    const trimmed = d.replace(/0+$/, '');

    return { whole: w, decimal: trimmed };
}

type NumberComboboxProps = {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    options: string[];
    placeholder?: string;
    disabled?: boolean;
    maxDigits?: number;
    className?: string;
};

function NumberCombobox({
    id,
    value,
    onChange,
    options,
    placeholder = '--',
    disabled,
    maxDigits = 3,
    className,
}: NumberComboboxProps) {
    const [open, setOpen] = useState(false);
    const [filter, setFilter] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);

    // Close on outside click
    useEffect(() => {
        if (!open) return;
        const handler = (e: MouseEvent) => {
            if (
                containerRef.current &&
                !containerRef.current.contains(e.target as Node)
            ) {
                setOpen(false);
                setFilter('');
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [open]);

    const filtered =
        filter === ''
            ? options
            : options.filter((o) => o.startsWith(filter));

    const handleInputChange = (raw: string) => {
        const digitsOnly = raw.replace(/\D/g, '').slice(0, maxDigits);
        onChange(digitsOnly);
        setFilter(digitsOnly);
        if (!open) setOpen(true);
    };

    const handleSelect = (opt: string) => {
        onChange(opt);
        setOpen(false);
        setFilter('');
    };

    return (
        <div ref={containerRef} className={cn('relative', className)}>
            <input
                id={id}
                type="text"
                inputMode="numeric"
                value={value}
                onChange={(e) => handleInputChange(e.target.value)}
                onFocus={() => setOpen(true)}
                onKeyDown={(e) => {
                    if (e.key === 'Escape') {
                        setOpen(false);
                        setFilter('');
                    }
                    if (e.key === 'Enter' && filtered.length > 0) {
                        e.preventDefault();
                        handleSelect(filtered[0]);
                    }
                }}
                disabled={disabled}
                placeholder={placeholder}
                className="border-input bg-input placeholder:text-muted-foreground focus-visible:ring-ring h-9 w-full rounded-md border pr-7 pl-3 text-sm transition-colors focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
            />
            <ChevronDown className="pointer-events-none absolute top-1/2 right-2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />

            {open && !disabled && (
                <div className="border-input bg-popover absolute top-full left-0 z-50 mt-1 max-h-56 w-full overflow-y-auto rounded-md border shadow-lg">
                    {filtered.length === 0 ? (
                        <div className="px-3 py-2 text-xs text-muted-foreground">
                            No matches
                        </div>
                    ) : (
                        filtered.map((opt) => (
                            <button
                                key={opt}
                                type="button"
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => handleSelect(opt)}
                                className={cn(
                                    'block w-full px-3 py-1.5 text-left text-sm transition-colors hover:bg-white/5',
                                    value === opt
                                        ? 'bg-lime-brand/10 font-medium text-lime-brand'
                                        : 'text-foreground',
                                )}
                            >
                                {opt}
                            </button>
                        ))
                    )}
                </div>
            )}
        </div>
    );
}

export default function DistancePicker({
    id,
    value,
    unit,
    onChange,
    disabled,
    className,
}: Props) {
    const [whole, setWhole] = useState<string>('');
    const [decimal, setDecimal] = useState<string>('');

    // Sync when external value changes (edit prefill, restore, etc.)
    useEffect(() => {
        const split = splitValue(value);
        setWhole(split.whole);
        setDecimal(split.decimal);
    }, [value]);

    const emit = (nextWhole: string, nextDecimal: string) => {
        if (nextWhole === '') {
            onChange(null, unit);
            return;
        }
        const combined =
            nextDecimal === '' ? nextWhole : `${nextWhole}.${nextDecimal}`;
        const num = parseFloat(combined);
        if (isNaN(num)) {
            onChange(null, unit);
            return;
        }
        onChange(Math.round(num * 100) / 100, unit);
    };

    const handleWholeChange = (next: string) => {
        setWhole(next);
        emit(next, decimal);
    };

    const handleDecimalChange = (next: string) => {
        setDecimal(next);
        emit(whole, next);
    };

    const handleUnitChange = (nextUnit: DistanceUnit) => {
        if (nextUnit === unit) return;

        const combined =
            whole === ''
                ? null
                : parseFloat(decimal === '' ? whole : `${whole}.${decimal}`);

        if (combined === null || isNaN(combined)) {
            onChange(null, nextUnit);
            return;
        }

        const converted =
            nextUnit === 'mi' ? toMiles(combined) : toKm(combined);
        const split = splitValue(converted);
        setWhole(split.whole);
        setDecimal(split.decimal);
        onChange(converted, nextUnit);
    };

    return (
        <div className={cn('flex items-center gap-2', className)}>
            <div className="flex flex-1 items-center gap-0.5">
                <NumberCombobox
                    id={id}
                    value={whole}
                    onChange={handleWholeChange}
                    options={WHOLE_OPTIONS}
                    disabled={disabled}
                    maxDigits={3}
                    placeholder="0"
                    className="flex-[2]"
                />
                <span className="text-sm text-muted-foreground select-none">
                    .
                </span>
                <NumberCombobox
                    value={decimal}
                    onChange={handleDecimalChange}
                    options={DECIMAL_OPTIONS}
                    disabled={disabled}
                    maxDigits={2}
                    placeholder="0"
                    className="flex-1"
                />
            </div>

            <div className="border-input flex h-9 shrink-0 overflow-hidden rounded-md border">
                <button
                    type="button"
                    onClick={() => handleUnitChange('km')}
                    disabled={disabled}
                    className={cn(
                        'px-3 text-xs font-semibold transition-colors',
                        unit === 'km'
                            ? 'bg-lime-brand text-navy-900'
                            : 'text-muted-foreground hover:bg-white/5 hover:text-foreground',
                    )}
                >
                    KM
                </button>
                <button
                    type="button"
                    onClick={() => handleUnitChange('mi')}
                    disabled={disabled}
                    className={cn(
                        'border-input border-l px-3 text-xs font-semibold transition-colors',
                        unit === 'mi'
                            ? 'bg-lime-brand text-navy-900'
                            : 'text-muted-foreground hover:bg-white/5 hover:text-foreground',
                    )}
                >
                    Miles
                </button>
            </div>
        </div>
    );
}
