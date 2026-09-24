import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface QuoteRow {
    id: number;
    customer: string;
    status: string;
    total: number;
}

const currency = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

const statusStyles: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-800',
    sent: 'bg-blue-100 text-blue-800',
    won: 'bg-green-100 text-green-800',
    lost: 'bg-red-100 text-red-800',
};

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
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Quotes
                </h2>
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

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        {quotes.length === 0 ? (
                            <div className="p-6 text-gray-500">
                                No quotes yet.
                            </div>
                        ) : (
                            <table className="min-w-full divide-y divide-gray-200">
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
                                <tbody className="divide-y divide-gray-200 bg-white">
                                    {quotes.map((quote) => (
                                        <tr key={quote.id}>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                                {quote.customer}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4">
                                                <span
                                                    className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${statusStyles[quote.status] ?? ''}`}
                                                >
                                                    {quote.status}
                                                </span>
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
                                                    className="text-indigo-600 hover:text-indigo-900"
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
                                                        className="ms-4 text-red-600 hover:text-red-900"
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
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
