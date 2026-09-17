import { useCallback, useState } from 'react';
import ConfirmDialog, {
    type ConfirmVariant,
} from '@/components/confirm-dialog';

export type ConfirmOptions = {
    variant?: ConfirmVariant;
    title: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    onConfirm: () => void;
};

export function useConfirmDialog() {
    const [options, setOptions] = useState<ConfirmOptions | null>(null);

    const openConfirm = useCallback((next: ConfirmOptions) => {
        setOptions(next);
    }, []);

    const close = useCallback(() => {
        setOptions(null);
    }, []);

    const handleConfirm = useCallback(() => {
        const callback = options?.onConfirm;
        setOptions(null);
        callback?.();
    }, [options]);

    const dialog = (
        <ConfirmDialog
            open={options !== null}
            onOpenChange={(open) => {
                if (!open) close();
            }}
            variant={options?.variant ?? 'primary'}
            title={options?.title ?? ''}
            description={options?.description}
            confirmLabel={options?.confirmLabel}
            cancelLabel={options?.cancelLabel}
            onConfirm={handleConfirm}
        />
    );

    return { dialog, openConfirm } as const;
}