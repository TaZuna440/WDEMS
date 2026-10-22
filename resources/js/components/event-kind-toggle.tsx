import { Link } from '@inertiajs/react';

type Kind = 'running' | 'church';

export default function EventKindToggle({
    kind,
    baseUrl,
}: {
    kind: Kind;
    baseUrl: string;
}) {
    return (
        <nav
            aria-label="Choose event type"
            className="border-border bg-card/70 flex w-fit items-center gap-1 rounded-full border p-1"
        >
            {[
                { value: 'running', label: 'Running' },
                { value: 'church', label: 'Church' },
            ].map((option) => (
                <Link
                    key={option.value}
                    href={`${baseUrl}?kind=${option.value}`}
                    aria-current={kind === option.value ? 'page' : undefined}
                    className={`inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium transition-colors ${
                        kind === option.value
                            ? 'border-lime-brand/40 bg-lime-brand/25 dark:bg-lime-brand/20 dark:text-lime-brand text-lime-700'
                            : 'text-foreground hover:bg-background border-transparent'
                    }`}
                >
                    {option.label}
                </Link>
            ))}
        </nav>
    );
}
