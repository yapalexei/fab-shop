import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Checks the current user's effective shop permissions (e.g.
 * can('quotes.delete')), shared on every page. Only for hiding controls -
 * the server enforces the same checks on every route regardless.
 */
export function useCan() {
    const permissions = usePage<PageProps>().props.auth.permissions ?? [];

    return (permission: string) => permissions.includes(permission);
}
