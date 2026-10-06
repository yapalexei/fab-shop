import RequiresSubscription from '@/Components/RequiresSubscription';
import { useShopDateTime } from '@/lib/datetime';
import { formatCents } from '@/lib/money';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';

interface Change {
    type: 'changed' | 'added' | 'removed';
    subject: string;
    from?: string | null;
    to?: string | null;
}

interface Entry {
    id: number;
    summary: string;
    changes: Change[];
    user: string | null;
    created_at: string;
}

export interface QuoteHistory {
    /** null when the shop has no claim to history (see price_cents). */
    entries: Entry[] | null;
    read_only: boolean;
    price_cents: number | null;
}

function ChangeLine({ change }: { change: Change }) {
    const value = (v?: string | null) =>
        v === null || v === undefined || v === '' ? (
            <em className="text-gray-400">empty</em>
        ) : (
            <strong className="font-medium">{v}</strong>
        );

    if (change.type === 'added') {
        return (
            <>
                Added {change.subject}: {value(change.to)}
            </>
        );
    }

    if (change.type === 'removed') {
        return (
            <>
                Removed {change.subject}: {value(change.from)}
            </>
        );
    }

    return (
        <>
            {change.subject}: {value(change.from)} &rarr; {value(change.to)}
        </>
    );
}

/**
 * Who changed a quote, when, and what - one row per save or workflow step.
 * A paid add-on (included in Pro): shops without it see a short offer
 * instead, and one that lost it keeps a read-only view of what was recorded.
 */
export default function QuoteHistoryPanel({
    history,
}: {
    history: QuoteHistory;
}) {
    const formatDateTime = useShopDateTime();
    const isOwner = usePage<PageProps>().props.auth.isOwner;

    if (history.entries === null) {
        return (
            <div className="mt-8 flex items-center gap-1 rounded-md bg-gray-50 p-4 text-sm text-gray-600">
                <RequiresSubscription />
                <span className="ms-1">
                    Quote history - see every change and who made it - is an
                    add-on
                    {history.price_cents !== null &&
                        ` (${formatCents(history.price_cents)}/mo)`}{' '}
                    and is included in Pro.
                    {isOwner && (
                        <>
                            {' '}
                            <Link
                                href={route('shop.addons.index')}
                                className="font-medium text-indigo-600 hover:underline"
                            >
                                See add-ons
                            </Link>
                        </>
                    )}
                </span>
            </div>
        );
    }

    return (
        <div className="mt-8">
            <h3 className="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500">
                History
            </h3>
            {history.read_only && (
                <p className="mb-3 text-sm text-amber-700">
                    Quote history is no longer part of your plan, so new changes
                    aren't being recorded.
                </p>
            )}
            {history.entries.length === 0 ? (
                <p className="text-sm text-gray-500">
                    No changes recorded yet.
                </p>
            ) : (
                <ul className="divide-y divide-gray-100">
                    {history.entries.map((entry) => (
                        <li key={entry.id} className="py-3 text-sm">
                            <div className="flex flex-wrap justify-between gap-x-4">
                                <span className="font-medium text-gray-900">
                                    {entry.summary}
                                </span>
                                <span className="text-gray-500">
                                    {entry.user ?? 'Someone'} &middot;{' '}
                                    {formatDateTime(entry.created_at)}
                                </span>
                            </div>
                            {entry.changes.length > 0 && (
                                <ul className="mt-1 list-disc space-y-0.5 ps-5 text-gray-600">
                                    {entry.changes.map((change, i) => (
                                        <li key={i}>
                                            <ChangeLine change={change} />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
