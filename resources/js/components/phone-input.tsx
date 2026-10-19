import { useState } from 'react';
import { Check, X } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type Props = {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    disabled?: boolean;
    autoComplete?: string;
    'aria-invalid'?: boolean;
};

/**
 * Philippine phone number input with real-time validation.
 *
 * Accepts:
 * - 09XXXXXXXXX (11 digits, starts with 09)
 * - +639XXXXXXXXX (+63 followed by 9XXXXXXXXX)
 *
 * Shows live validation feedback as the user types. Empty value
 * is neutral (no error shown) — required-ness is handled by the
 * parent form validation.
 */
export function isValidPHPhone(value: string): boolean {
    const trimmed = value.trim();
    if (trimmed === '') return false;
    // 09XXXXXXXXX
    if (/^09\d{9}$/.test(trimmed)) return true;
    // +639XXXXXXXXX
    if (/^\+639\d{9}$/.test(trimmed)) return true;
    return false;
}

/**
 * Normalize to 09XXXXXXXXX format for consistent storage.
 * Returns the original value if it doesn't match either pattern.
 */
export function normalizePHPhone(value: string): string {
    const trimmed = value.trim();
    if (/^\+639\d{9}$/.test(trimmed)) {
        return '0' + trimmed.slice(3);
    }
    return trimmed;
}

export default function PhoneInput({
    id,
    value,
    onChange,
    placeholder = '09171234567',
    disabled,
    autoComplete = 'tel',
    'aria-invalid': ariaInvalid,
}: Props) {
    const [touched, setTouched] = useState(false);

    const trimmed = value.trim();
    const isEmpty = trimmed === '';
    const isValid = isEmpty || isValidPHPhone(trimmed);
    const showFeedback = touched && !isEmpty;

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        // Allow digits, +, spaces, dashes, parentheses — strip on normalize
        const raw = e.target.value;
        // Only allow characters that could be part of a phone number
        const filtered = raw.replace(/[^0-9+\s\-()]/g, '');
        onChange(filtered);
    };

    const handleBlur = () => {
        setTouched(true);
        // Normalize on blur: +639... -> 09...
        if (!isEmpty && isValidPHPhone(trimmed)) {
            const normalized = normalizePHPhone(trimmed);
            if (normalized !== value) {
                onChange(normalized);
            }
        }
    };

    return (
        <div className="relative">
            <Input
                id={id}
                type="tel"
                inputMode="tel"
                value={value}
                onChange={handleChange}
                onBlur={handleBlur}
                placeholder={placeholder}
                disabled={disabled}
                autoComplete={autoComplete}
                aria-invalid={ariaInvalid || (showFeedback && !isValid)}
                className={cn(
                    showFeedback && isValid && 'border-lime-brand/50 pr-9',
                    showFeedback && !isValid && 'border-destructive pr-9',
                )}
            />
            {showFeedback && (
                <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
                    {isValid ? (
                        <Check className="h-4 w-4 text-lime-brand" aria-label="Valid phone number" />
                    ) : (
                        <X className="h-4 w-4 text-destructive" aria-label="Invalid phone number" />
                    )}
                </span>
            )}
            {showFeedback && !isValid && (
                <p className="mt-1 text-xs text-destructive">
                    Enter a valid PH mobile number (09XXXXXXXXX or +639XXXXXXXXX).
                </p>
            )}
        </div>
    );
}
