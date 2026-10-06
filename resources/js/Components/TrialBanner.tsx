import { useShopDateTime } from '@/lib/datetime';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';

const MS_PER_DAY = 86_400_000;

/**
 * A persistent reminder of how long a trial shop has left - not dismissible,
 * since it's tracking a real monetization deadline rather than a one-off
 * notice. No in-app trial messaging existed before this (see the F0.9
 * write-up) - a shop only ever found out via a one-time "trial ended" email
 * after the fact, or vague copy with no date anywhere.
 */
export default function TrialBanner() {
    const { shop, readOnlyFeatures } = usePage<PageProps>().props;
    const formatDate = useShopDateTime({ dateStyle: 'medium' });

    // Decided server-side (trial over, never subscribed, not yet dismissed by
    // this member), so there's nothing to remember in the browser.
    if (shop?.show_trial_ended_notice && readOnlyFeatures.length > 0) {
        const dismissEnded = () =>
            router.post(
                route('trial-ended-notice.dismiss'),
                {},
                { preserveScroll: true },
            );

        return (
            <div
                role="status"
                className="relative bg-amber-50 px-10 py-2 text-center text-sm text-amber-800 sm:px-12 lg:px-14"
            >
                Your trial ended on {formatDate(shop!.trial_ends_at!)}. Your
                data is still here but read-only
                {shop!.can_upgrade && (
                    <>
                        {' '}
                        -{' '}
                        <Link
                            href={route('shop.plan.index')}
                            className="font-semibold underline"
                        >
                            upgrade to edit it
                        </Link>
                    </>
                )}
                .
                <button
                    type="button"
                    onClick={dismissEnded}
                    aria-label="Dismiss"
                    className="absolute inset-e-3 top-1/2 -translate-y-1/2 rounded-sm p-1 text-amber-700 hover:bg-amber-100"
                >
                    &times;
                </button>
            </div>
        );
    }

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
