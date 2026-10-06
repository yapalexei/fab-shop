import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/Components/ui/dialog';
import { cn } from '@/lib/utils';
import { PropsWithChildren } from 'react';

const maxWidthClasses = {
    sm: 'sm:max-w-sm',
    md: 'sm:max-w-md',
    lg: 'sm:max-w-lg',
    xl: 'sm:max-w-xl',
    '2xl': 'sm:max-w-2xl',
};

export default function Modal({
    children,
    show = false,
    maxWidth = '2xl',
    closeable = true,
    onClose = () => {},
    title = 'Dialog',
}: PropsWithChildren<{
    show: boolean;
    maxWidth?: keyof typeof maxWidthClasses;
    closeable?: boolean;
    onClose: CallableFunction;
    /** Announced to screen readers; the visible heading lives in `children`. */
    title?: string;
}>) {
    const keepOpen = (event: Event) => {
        if (!closeable) event.preventDefault();
    };

    return (
        <Dialog
            open={show}
            onOpenChange={(open) => {
                if (!open && closeable) onClose();
            }}
        >
            <DialogContent
                showCloseButton={false}
                aria-describedby={undefined}
                onEscapeKeyDown={keepOpen}
                onInteractOutside={keepOpen}
                className={cn(
                    'max-h-[calc(100dvh-2rem)] gap-0 overflow-y-auto p-0',
                    maxWidthClasses[maxWidth],
                )}
            >
                <DialogTitle className="sr-only">{title}</DialogTitle>
                <DialogDescription className="sr-only">{title}</DialogDescription>
                {children}
            </DialogContent>
        </Dialog>
    );
}
