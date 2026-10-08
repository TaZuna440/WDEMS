import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Props = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    baseUrl: string;
    query: Record<string, string | number>;
};

export default function Pagination({
    currentPage,
    lastPage,
    perPage,
    total,
    baseUrl,
    query,
}: Props) {
    if (lastPage <= 1) {
        return null;
    }

    const go = (page: number) => {
        if (page < 1 || page > lastPage || page === currentPage) {
            return;
        }

        router.get(
            baseUrl,
            {
                ...query,
                ...(page > 1 ? { page } : {}),
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ['rows', 'pagination'],
            },
        );
    };

    const from = (currentPage - 1) * perPage + 1;
    const to = Math.min(currentPage * perPage, total);

    return (
        <div className="flex flex-col items-center justify-between gap-3 border-t border-white/5 pt-4 sm:flex-row">
            <p className="text-xs text-muted-foreground">
                Showing{' '}
                <span className="font-medium text-foreground">{from}</span>
                {' – '}
                <span className="font-medium text-foreground">{to}</span>
                {' of '}
                <span className="font-medium text-foreground">{total}</span>
            </p>

            <div className="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => go(currentPage - 1)}
                    disabled={currentPage <= 1}
                >
                    <ChevronLeft className="mr-1 h-4 w-4" />
                    Previous
                </Button>

                <span className="text-xs text-muted-foreground">
                    Page{' '}
                    <span className="font-medium text-foreground">
                        {currentPage}
                    </span>
                    {' of '}
                    <span className="font-medium text-foreground">
                        {lastPage}
                    </span>
                </span>

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => go(currentPage + 1)}
                    disabled={currentPage >= lastPage}
                >
                    Next
                    <ChevronRight className="ml-1 h-4 w-4" />
                </Button>
            </div>
        </div>
    );
}
