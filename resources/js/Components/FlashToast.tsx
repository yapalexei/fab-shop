import { Toaster } from '@/Components/ui/sonner';
import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

const DISMISS_AFTER_MS = 5000;

/**
 * Renders the shop's `flash.status`/`flash.error` (see
 * HandleInertiaRequests::share()) as auto-dismissing corner toasts instead of
 * an inline banner that pushes page content down. Flash props otherwise sit
 * in Inertia's page props indefinitely - a partial reload (e.g. the job
 * board's broadcast refetch) never re-fetches them - so each new message is
 * handed to Sonner once, which owns the dismiss timer.
 */
export default function FlashToast() {
    const { flash } = usePage<PageProps>().props;
    const status = flash?.status ?? null;
    const error = flash?.error ?? null;

    useEffect(() => {
        if (status) toast.success(status, { duration: DISMISS_AFTER_MS });
    }, [status]);

    useEffect(() => {
        if (error) toast.error(error, { duration: DISMISS_AFTER_MS });
    }, [error]);

    return <Toaster position="bottom-right" />;
}
