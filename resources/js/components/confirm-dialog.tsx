import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type ConfirmVariant = 'primary' | 'warning' | 'danger';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    variant?: ConfirmVariant;
    title: string;
    description?: string;
    children?: React.ReactNode;
    confirmLabel?: string;
    cancelLabel?: string;
    onConfirm: () => void;
};

const confirmButtonStyles: Record<ConfirmVariant, string> = {
    primary: 'bg-lime-brand text-navy-900 hover:bg-lime-brand/90',
    warning: 'bg-yellow-500 text-navy-900 hover:bg-yellow-500/90',
    danger: '',
};

export default function ConfirmDialog({
    open,
    onOpenChange,
    variant = 'primary',
    title,
    description,
    children,
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    onConfirm,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>

                {children}

                <DialogFooter className="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {cancelLabel}
                    </Button>

                    {variant === 'danger' ? (
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={onConfirm}
                        >
                            {confirmLabel}
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            className={confirmButtonStyles[variant]}
                            onClick={onConfirm}
                        >
                            {confirmLabel}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
