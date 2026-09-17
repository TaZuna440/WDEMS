import { cn } from '@/lib/utils';

type Props = {
    className?: string;
    /**
     * auto     — light parts use `currentColor` (theme-adaptive)
     * on-dark  — light parts forced white
     * on-light — light parts forced navy
     */
    variant?: 'auto' | 'on-dark' | 'on-light';
};

export default function WdemsLogo({
    className,
    variant = 'auto',
}: Props) {
    const colorClass =
        variant === 'on-dark'
            ? 'text-white'
            : variant === 'on-light'
              ? 'text-navy-900'
              : 'text-foreground';

    return (
        <svg
            viewBox="0 0 209 211"
            xmlns="http://www.w3.org/2000/svg"
            className={cn('block w-auto', colorClass, className ?? 'h-10')}
            role="img"
            aria-label="WDEMS"
        >
            {/* Top-right bar — theme color */}
            <path
                d="M208.007 0.5H145.264L111.264 79L173.264 79.5L208.007 0.5Z"
                fill="currentColor"
            />
            {/* Bottom-right lime bar */}
            <path
                d="M168.146 95.5H104.264L54.2642 210H117.764L168.146 95.5Z"
                fill="#B6FF3B"
            />
            {/* Left lime bar */}
            <path
                d="M51.2642 31L0.76416 145.5L64.2642 146L115.264 31H51.2642Z"
                fill="#B6FF3B"
            />
        </svg>
    );
}