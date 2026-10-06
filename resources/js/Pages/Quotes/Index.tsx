import { LogoAvatar } from '@/Components/Avatar';
import PageHelp from '@/Components/PageHelp';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';

interface QuoteRow {
    id: number;
    customer: string;
    customer_logo_url: string | null;
    status: string;
    total: number;
}

const currency = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

const statusVariants = {
    draft: 'secondary',
    sent: 'info',
    won: 'success',
    lost: 'danger',
} as const;

export default function Index({ quotes }: PageProps<{ quotes: QuoteRow[] }>) {
    const can = useCan();
    const destroy = (quote: QuoteRow) => {
        if (confirm(`Delete this quote for ${quote.customer}?`)) {
            router.delete(route('quotes.destroy', quote.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Quotes
                    </h2>
                    {quotes.length > 0 && (
                        <PageHelp>
                            <p>
                                Every quote your shop has built, from draft
                                through won or lost.
                            </p>
                            <p>
                                A won quote converts into a trackable job on the
                                job board; a lost one can be archived. This is
                                the starting point for pricing new work.
                            </p>
                        </PageHelp>
                    )}
                </div>
            }
        >
            <Head title="Quotes" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {can('quotes.create') && (
                        <div className="flex justify-end">
                            <Link href={route('quotes.create')}>
                                <PrimaryButton>New quote</PrimaryButton>
                            </Link>
                        </div>
                    )}

                    <Card className="overflow-hidden">
                        {quotes.length === 0 ? (
                            <div className="p-6">
                                <PageHelp empty>
                                    <p>
                                        No quotes yet. This is every quote your
                                        shop has built, from draft through won
                                        or lost.
                                    </p>
                                    <p>
                                        A won quote converts into a trackable
                                        job on the job board; a lost one can be
                                        archived. This is the starting point for
                                        pricing new work.
                                    </p>
                                </PageHelp>
                            </div>
                        ) : (
                            <table className="min-w-full divide-y">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Customer
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Status
                                        </th>
                                        <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Total
                                        </th>
                                        <th className="px-6 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y bg-white">
                                    {quotes.map((quote) => (
                                        <tr key={quote.id}>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                                <span className="flex items-center gap-3">
                                                    <LogoAvatar
                                                        src={quote.customer_logo_url}
                                                    />
                                                    {quote.customer}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4">
                                                <Badge
                                                    variant={
                                                        statusVariants[
                                                            quote.status as keyof typeof statusVariants
                                                        ] ?? 'secondary'
                                                    }
                                                >
                                                    {quote.status}
                                                </Badge>
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-500">
                                                {currency.format(quote.total)}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                                <Link
                                                    href={route(
                                                        'quotes.edit',
                                                        quote.id,
                                                    )}
                                                    className="text-indigo-600 hover:underline"
                                                >
                                                    {can('quotes.update')
                                                        ? 'Edit'
                                                        : 'View'}
                                                </Link>
                                                {can('quotes.delete') && (
                                                    <button
                                                        onClick={() =>
                                                            destroy(quote)
                                                        }
                                                        className="ms-4 text-red-600 hover:underline"
                                                    >
                                                        Delete
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
