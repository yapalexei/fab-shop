import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import RequiresSubscription from '@/Components/RequiresSubscription';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useShopDateTime } from '@/lib/datetime';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

function SubscriptionNotice({ children }: { children: string }) {
    return (
        <p className="flex w-full items-center text-sm text-amber-700">
            <RequiresSubscription />
            <span className="ms-1">{children}</span>
        </p>
    );
}

interface Props {
    quoteId: number;
    status: string;
    isArchived: boolean;
    jobId: number | null;
    customerEmail: string | null;
    emailedAt: string | null;
    sendSkippedReason: string | null;
}

// One-click reasons for skipping the email: they fill the reason and submit.
const QUICK_SKIP_REASONS = [
    'Customer agreed verbally',
    'Gave the customer a copy in person',
];

/**
 * Renders whichever action(s) are valid for the quote's current place in the
 * draft -> sent -> won/lost -> archived lifecycle (plus won -> job). Only one
 * transition is ever valid at a time, so this is a lookup, not a form.
 */
export default function QuoteWorkflowPanel({
    quoteId,
    status,
    isArchived,
    jobId,
    customerEmail,
    emailedAt,
    sendSkippedReason,
}: Props) {
    const formatDateTime = useShopDateTime();
    const [skipping, setSkipping] = useState(false);
    const [reason, setReason] = useState('');
    // Emailing the customer can't be undone, so ask first.
    const [confirmEmail, setConfirmEmail] = useState<'send' | 'resend' | null>(
        null,
    );
    const { errors, enabledFeatures } = usePage<PageProps>()
        .props as PageProps & {
        errors: Record<string, string>;
    };
    const can = useCan();
    const quoteBuilderLocked = !enabledFeatures.includes('quote-builder');
    const jobBoardLocked = !enabledFeatures.includes('job-board');

    // Trial ended / plan lacks the quote builder: no workflow action is
    // possible, so say why instead of rendering nothing.
    if (quoteBuilderLocked && !(status === 'won' && jobId)) {
        return (
            <div className="mb-6 rounded-md bg-gray-50 p-4">
                <SubscriptionNotice>
                    Changing this quote requires a Quote builder subscription.
                </SubscriptionNotice>
            </div>
        );
    }

    // Workflow steps are part of editing a quote; converting creates a job.
    if (!can('quotes.update') && !(status === 'won' && jobId)) {
        return null;
    }

    const post = (routeName: string, data: Record<string, string> = {}) => {
        router.post(route(routeName, quoteId), data, { preserveScroll: true });
    };

    const skipSend = (text: string) => post('quotes.skip-send', { reason: text });

    const submitSkip = (event: FormEvent) => {
        event.preventDefault();
        skipSend(reason);
    };

    const canEmail = !!customerEmail;

    return (
        <div className="mb-6 flex flex-wrap items-center gap-3 rounded-md bg-gray-50 p-4">
            {errors?.status && (
                <p className="w-full text-sm text-red-600">{errors.status}</p>
            )}

            {isArchived ? (
                <>
                    <span className="text-sm text-gray-500">Archived</span>
                    <SecondaryButton onClick={() => post('quotes.reopen')}>
                        Reopen as draft
                    </SecondaryButton>
                </>
            ) : status === 'draft' ? (
                <>
                    <SecondaryButton
                        onClick={() => setConfirmEmail('send')}
                        disabled={!canEmail}
                    >
                        Send to customer
                    </SecondaryButton>
                    <a
                        href={route('quotes.preview', quoteId)}
                        className="text-sm text-indigo-600 hover:underline"
                        target="_blank"
                        rel="noreferrer"
                    >
                        Preview PDF
                    </a>
                    {!skipping && (
                        <button
                            type="button"
                            onClick={() => setSkipping(true)}
                            className="text-sm text-indigo-600 hover:underline"
                        >
                            Skip sending
                        </button>
                    )}
                    {!canEmail && (
                        <p className="w-full text-sm text-gray-500">
                            This customer has no email address on file - add
                            one to email the quote, or skip sending.
                        </p>
                    )}
                    {skipping && (
                        <form
                            onSubmit={submitSkip}
                            className="w-full space-y-2 border-t border-gray-200 pt-3"
                        >
                            <p className="text-sm text-gray-700">
                                Mark this quote as sent without emailing the
                                customer. Why?
                            </p>
                            <div className="flex flex-wrap gap-2">
                                {QUICK_SKIP_REASONS.map((quick) => (
                                    <SecondaryButton
                                        key={quick}
                                        type="button"
                                        onClick={() => skipSend(quick)}
                                    >
                                        {quick}
                                    </SecondaryButton>
                                ))}
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <TextInput
                                    className="block min-w-64 flex-1"
                                    placeholder="Or type a reason..."
                                    maxLength={255}
                                    value={reason}
                                    onChange={(e) => setReason(e.target.value)}
                                    required
                                />
                                <SecondaryButton type="submit">
                                    Skip sending
                                </SecondaryButton>
                                <button
                                    type="button"
                                    onClick={() => setSkipping(false)}
                                    className="text-sm text-gray-500 hover:text-foreground"
                                >
                                    Cancel
                                </button>
                            </div>
                            {errors?.reason && (
                                <p className="text-sm text-red-600">
                                    {errors.reason}
                                </p>
                            )}
                        </form>
                    )}
                </>
            ) : status === 'sent' ? (
                <>
                    <p className="w-full text-sm text-gray-600">
                        {emailedAt
                            ? `Emailed to ${customerEmail ?? 'the customer'} on ${formatDateTime(emailedAt)}.`
                            : sendSkippedReason
                              ? `Not emailed - ${sendSkippedReason}.`
                              : 'Email queued - it should arrive shortly.'}
                    </p>
                    {canEmail && (
                        <SecondaryButton onClick={() => setConfirmEmail('resend')}>
                            {emailedAt || !sendSkippedReason
                                ? 'Resend email'
                                : 'Email customer'}
                        </SecondaryButton>
                    )}
                    <SecondaryButton onClick={() => post('quotes.mark-won')}>
                        Mark won
                    </SecondaryButton>
                    <SecondaryButton onClick={() => post('quotes.mark-lost')}>
                        Mark lost
                    </SecondaryButton>
                    {emailedAt && (
                        <a
                            href={route('quotes.pdf', quoteId)}
                            className="text-sm text-indigo-600 hover:underline"
                            target="_blank"
                            rel="noreferrer"
                        >
                            View PDF
                        </a>
                    )}
                </>
            ) : status === 'won' ? (
                jobId ? (
                    <Link
                        href={route('jobs.show', jobId)}
                        className="text-sm text-indigo-600 hover:underline"
                    >
                        View job
                    </Link>
                ) : can('jobs.create') ? (
                    <SecondaryButton onClick={() => post('quotes.convert')}>
                        Convert to job
                    </SecondaryButton>
                ) : (
                    <>
                        <span className="text-sm text-gray-500">
                            Won - waiting to be converted into a job
                        </span>
                        {jobBoardLocked && (
                            <SubscriptionNotice>
                                Converting to a job requires a Job board
                                subscription.
                            </SubscriptionNotice>
                        )}
                    </>
                )
            ) : status === 'lost' ? (
                <SecondaryButton onClick={() => post('quotes.archive')}>
                    Archive
                </SecondaryButton>
            ) : null}

            <Modal
                show={confirmEmail !== null}
                maxWidth="md"
                onClose={() => setConfirmEmail(null)}
            >
                <div className="space-y-4 p-6">
                    <h3 className="text-lg font-semibold text-gray-900">
                        {confirmEmail === 'resend'
                            ? 'Email this quote again?'
                            : 'Send this quote to the customer?'}
                    </h3>
                    <p className="text-sm text-gray-700">
                        An email with the quote PDF will go to{' '}
                        <strong>{customerEmail}</strong> right away
                        {confirmEmail === 'send' &&
                            ', and the quote will be marked as sent'}
                        . You can't unsend it.
                    </p>
                    <p className="text-sm">
                        <a
                            href={route('quotes.preview', quoteId)}
                            className="text-indigo-600 hover:underline"
                            target="_blank"
                            rel="noreferrer"
                        >
                            Preview the PDF they'll receive
                        </a>{' '}
                        <span className="text-gray-500">
                            (the quote as last saved)
                        </span>
                    </p>
                    <div className="flex justify-end gap-3">
                        <SecondaryButton onClick={() => setConfirmEmail(null)}>
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton
                            onClick={() => {
                                post(
                                    confirmEmail === 'resend'
                                        ? 'quotes.resend'
                                        : 'quotes.send',
                                );
                                setConfirmEmail(null);
                            }}
                        >
                            {confirmEmail === 'resend'
                                ? 'Send again'
                                : 'Send quote'}
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>
        </div>
    );
}
