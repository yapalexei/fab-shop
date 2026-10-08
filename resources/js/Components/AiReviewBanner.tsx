import PrimaryButton from '@/Components/PrimaryButton';
import { Alert } from '@/Components/ui/alert';
import { Link, router } from '@inertiajs/react';
import { ReactNode, useState } from 'react';

/**
 * "Your AI connection wrote this - read it before it goes anywhere." Shown
 * on a quote or customer the AI created or changed, until a person confirms.
 * A quote can't be sent while it (or its customer) is waiting, because what
 * the AI wrote prints on the quote and decides where it is emailed.
 */
export default function AiReviewBanner({
    children,
    confirmUrl,
    reviewHref,
    reviewLabel,
}: {
    children: ReactNode;
    /** POST here to mark it reviewed. Omit when it must be reviewed elsewhere. */
    confirmUrl?: string;
    /** Where to go to review it instead (e.g. the customer's page). */
    reviewHref?: string;
    reviewLabel?: string;
}) {
    const [busy, setBusy] = useState(false);

    return (
        <Alert
            variant="warning"
            role="status"
            className="mb-4 flex flex-wrap items-center justify-between gap-3 p-3 text-sm"
        >
            <span className="max-w-prose">{children}</span>
            {confirmUrl && (
                <PrimaryButton
                    type="button"
                    disabled={busy}
                    onClick={() => {
                        setBusy(true);
                        router.post(
                            confirmUrl,
                            {},
                            {
                                preserveScroll: true,
                                onFinish: () => setBusy(false),
                            },
                        );
                    }}
                >
                    Mark as reviewed
                </PrimaryButton>
            )}
            {reviewHref && (
                <Link
                    href={reviewHref}
                    className="font-medium underline underline-offset-2"
                >
                    {reviewLabel ?? 'Review'}
                </Link>
            )}
        </Alert>
    );
}
