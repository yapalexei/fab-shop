import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';

/**
 * Timestamps arrive from the server in UTC. Show them on the *shop's* wall
 * clock rather than the viewer's browser, so a job moved at 4pm in the shop
 * reads 4pm for everyone looking at it - including an owner checking in
 * from out of town.
 */
export function useShopDateTime(
    options: Intl.DateTimeFormatOptions = {
        dateStyle: 'medium',
        timeStyle: 'short',
    },
) {
    const timeZone = usePage<PageProps>().props.shop?.timezone;
    const key = JSON.stringify(options);

    return useMemo(() => {
        const formatter = new Intl.DateTimeFormat('en-US', {
            ...options,
            timeZone,
        });

        return (iso: string) => formatter.format(new Date(iso));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [timeZone, key]);
}
