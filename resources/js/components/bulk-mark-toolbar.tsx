import { CheckCheck, CheckSquare } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Props = {
    selectedCount: number;
    filterCount: number;
    filterLabel: string;
    disabled?: boolean;
    onMarkSelected: () => void;
    onMarkAllVisible: () => void;
};

/**
 * Confirm-mode toolbar. Shown only when the attendance page is in
 * Confirm mode. Two actions:
 *
 *   - Mark selected: submits the explicit list of checked rows.
 *   - Mark all visible: submits the current filter + search to the
 *     server, which marks every matching registration. "Visible"
 *     means "matching the current filter", not "on this page" — see
 *     D10 in docs/attendance-redesign.md.
 */
export default function BulkMarkToolbar({
    selectedCount,
    filterCount,
    filterLabel,
    disabled = false,
    onMarkSelected,
    onMarkAllVisible,
}: Props) {
    const hasSelection = selectedCount > 0;
    const hasVisible = filterCount > 0;

    return (
        <div className="flex flex-col gap-3 rounded-lg border border-white/10 bg-white/[0.02] p-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-3 text-sm">
                <span className="text-muted-foreground">
                    <span className="font-medium text-foreground">
                        {selectedCount}
                    </span>{' '}
                    selected
                </span>
                <span className="text-muted-foreground/40">·</span>
                <span className="text-muted-foreground">
                    <span className="font-medium text-foreground">
                        {filterCount}
                    </span>{' '}
                    match {filterLabel}
                </span>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={onMarkSelected}
                    disabled={disabled || ! hasSelection}
                >
                    <CheckSquare className="mr-2 h-3.5 w-3.5" />
                    Mark selected
                </Button>

                <Button
                    type="button"
                    size="sm"
                    onClick={onMarkAllVisible}
                    disabled={disabled || ! hasVisible}
                    className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                >
                    <CheckCheck className="mr-2 h-3.5 w-3.5" />
                    Mark all visible
                </Button>
            </div>
        </div>
    );
}
