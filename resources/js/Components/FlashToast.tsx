import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const DISMISS_AFTER_MS = 5000;

/**
 * Tracks one flash slot (status or error): a new message pops it in and
 * starts the auto-dismiss timer, and the last text keeps rendering while it
 * fades out so the toast doesn't go blank before the transition finishes.
 * Needed because flash props otherwise just sit in Inertia's page props
 * indefinitely - a partial reload (e.g. the job board's broadcast refetch)
 * never re-fetches them, so without a timer here a message would never go
 * away on its own.
 */
function useFlashToast(message: string | null) {
    const [shown, setShown] = useState<string | null>(null);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        if (!message) return;

        setShown(message);
        setVisible(true);

        const timer = setTimeout(() => setVisible(false), DISMISS_AFTER_MS);
        return () => clearTimeout(timer);
    }, [message]);

    return { shown, visible };
}

/**
 * Renders the shop's `flash.status`/`flash.error` (see
 * HandleInertiaRequests::share()) as small auto-dismissing corner toasts
 * instead of an inline banner that pushes page content down.
 */
export default function FlashToast() {
    const { flash } = usePage<PageProps>().props;
    const status = useFlashToast(flash?.status ?? null);
    const error = useFlashToast(flash?.error ?? null);

    if (!status.shown && !error.shown) {
        return null;
    }

    return (
        <div className="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex flex-col items-end gap-2 sm:inset-x-auto sm:right-4">
            {error.shown && (
                <div
                    role="alert"
                    className={`pointer-events-auto w-full max-w-sm rounded-md bg-red-50 p-4 text-sm text-red-700 shadow-lg ring-1 ring-black/5 transition duration-300 ${
                        error.visible
                            ? 'translate-y-0 opacity-100'
                            : 'translate-y-2 opacity-0'
                    }`}
                >
                    {error.shown}
                </div>
            )}
            {status.shown && (
                <div
                    role="status"
                    className={`pointer-events-auto w-full max-w-sm rounded-md bg-green-50 p-4 text-sm text-green-700 shadow-lg ring-1 ring-black/5 transition duration-300 ${
                        status.visible
                            ? 'translate-y-0 opacity-100'
                            : 'translate-y-2 opacity-0'
                    }`}
                >
                    {status.shown}
                </div>
            )}
        </div>
    );
}
