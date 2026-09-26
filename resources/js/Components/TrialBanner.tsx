import { useShopDateTime } from '@/lib/datetime';
import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';

const MS_PER_DAY = 86_400_000;

/**
 * A persistent reminder of how long a trial shop has left - not dismissible,
 * since it's tracking a real monetization deadline rather than a one-off
 * notice. No in-app trial messaging existed before this (see the F0.9
 * write-up) - a shop only ever found out via a one-time "trial ended" email
 * after the fact, or vague copy with no date anywhere.
 */
export default function TrialBanner() {
    const { shop } = usePage<PageProps>().props;
    const formatDate = useShopDateTime({ dateStyle: 'medium' });

    if (!shop?.on_trial || !shop.trial_ends_at) {
        return null;
    }

    const daysLeft = Math.max(
        0,
        Math.ceil(
            (new Date(shop.trial_ends_at).getTime() - Date.now()) / MS_PER_DAY,
        ),
    );
    const urgent = daysLeft <= 3;

    return (
        <div
            role="status"
            className={`px-4 py-2 text-center text-sm sm:px-6 lg:px-8 ${
                urgent
                    ? 'bg-amber-50 text-amber-800'
                    : 'bg-indigo-50 text-indigo-800'
            }`}
        >
            {daysLeft === 0
                ? `Your trial ends today (${formatDate(shop.trial_ends_at)}).`
                : `Your trial ends in ${daysLeft} day${daysLeft === 1 ? '' : 's'} (${formatDate(shop.trial_ends_at)}).`}
        </div>
    );
}
