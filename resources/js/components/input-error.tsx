import { AlertCircle } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * Form field error message.
 *
 * Uses --destructive so the color flips with the mode rather than
 * a hardcoded Tailwind red. Carries a small alert icon so the
 * message reads as an error even when the surrounding text is
 * muted. The color is set via text-destructive (which is the
 * codebase's semantic token) rather than text-red-600 so future
 * theme changes propagate.
 */
export default function InputError({
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    return message ? (
        <p
            {...props}
            className={cn(
                'text-destructive flex items-start gap-1.5 text-sm',
                className,
            )}
        >
            <AlertCircle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
            <span>{message}</span>
        </p>
    ) : null;
}
