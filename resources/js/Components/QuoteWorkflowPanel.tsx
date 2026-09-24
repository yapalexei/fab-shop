import SecondaryButton from '@/Components/SecondaryButton';
import { Link, router, usePage } from '@inertiajs/react';

interface Props {
    quoteId: number;
    status: string;
    isArchived: boolean;
    jobId: number | null;
}

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
}: Props) {
    const errors = usePage().props.errors as Record<string, string>;

    const post = (routeName: string) => {
        router.post(route(routeName, quoteId), {}, { preserveScroll: true });
    };

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
                <SecondaryButton onClick={() => post('quotes.send')}>
                    Send to customer
                </SecondaryButton>
            ) : status === 'sent' ? (
                <>
                    <SecondaryButton onClick={() => post('quotes.mark-won')}>
                        Mark won
                    </SecondaryButton>
                    <SecondaryButton onClick={() => post('quotes.mark-lost')}>
                        Mark lost
                    </SecondaryButton>
                    <a
                        href={route('quotes.pdf', quoteId)}
                        className="text-sm text-indigo-600 hover:text-indigo-900"
                        target="_blank"
                        rel="noreferrer"
                    >
                        View PDF
                    </a>
                </>
            ) : status === 'won' ? (
                jobId ? (
                    <Link
                        href={route('jobs.show', jobId)}
                        className="text-sm text-indigo-600 hover:text-indigo-900"
                    >
                        View job
                    </Link>
                ) : (
                    <SecondaryButton onClick={() => post('quotes.convert')}>
                        Convert to job
                    </SecondaryButton>
                )
            ) : status === 'lost' ? (
                <SecondaryButton onClick={() => post('quotes.archive')}>
                    Archive
                </SecondaryButton>
            ) : null}
        </div>
    );
}
