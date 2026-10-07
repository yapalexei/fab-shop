import { useShopDateTime } from '@/lib/datetime';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useState } from 'react';

const today = () => new Date().toISOString().slice(0, 10);

function dismissedToday(key: string): boolean {
    try {
        return localStorage.getItem(key) === today();
    } catch {
        return false;
    }
}

/**
 * Reminders about security the signed-in person has to act on: the two-factor
 * requirement (dismissable, but it comes back the next day until they set it
 * up) and a shop that is scheduled for deletion (not dismissable).
 */
export default function SecurityNotices() {
    const { twoFactorNotice, shop, auth } = usePage<PageProps>().props;
    const formatDate = useShopDateTime({ dateStyle: 'medium' });
    const key = `two-factor-notice-dismissed:${twoFactorNotice?.deadline}`;
    const [hidden, setHidden] = useState(() => dismissedToday(key));

    return (
        <>
            {shop?.deletion_scheduled_for && (
                <div
                    role="alert"
                    className="bg-red-50 px-4 py-2 text-center text-sm text-red-800"
                >
                    This shop and all its data will be permanently deleted on{' '}
                    {formatDate(shop.deletion_scheduled_for)}.
                    {auth.isOwner && (
                        <>
                            {' '}
                            <Link
                                href={route('shop.settings.edit')}
                                className="font-semibold underline"
                            >
                                Export or cancel
                            </Link>
                        </>
                    )}
                </div>
            )}

            {twoFactorNotice && !twoFactorNotice.overdue && !hidden && (
                <div
                    role="status"
                    className="relative bg-amber-50 px-10 py-2 text-center text-sm text-amber-800 sm:px-12 lg:px-14"
                >
                    Your shop requires two-factor sign-in. Please{' '}
                    <Link
                        href={route('profile.edit')}
                        className="font-semibold underline"
                    >
                        set it up
                    </Link>{' '}
                    by {formatDate(twoFactorNotice.deadline)} (
                    {twoFactorNotice.days_left} day
                    {twoFactorNotice.days_left === 1 ? '' : 's'} left).
                    <button
                        type="button"
                        aria-label="Dismiss for today"
                        onClick={() => {
                            try {
                                localStorage.setItem(key, today());
                            } catch {
                                // Not remembered; it simply shows again.
                            }
                            setHidden(true);
                        }}
                        className="absolute inset-y-0 end-3 my-auto h-6 w-6 rounded p-1 text-amber-700 hover:bg-amber-100"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>
            )}
        </>
    );
}
