import CsvImportDialog from '@/Components/CsvImportDialog';
import PageHelp from '@/Components/PageHelp';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Card } from '@/Components/ui/card';

interface Customer {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
}

export default function Index({
    auth,
    customers,
}: PageProps<{ customers: Customer[] } & { auth: { isOwner: boolean } }>) {
    const can = useCan();
    const destroy = (customer: Customer) => {
        if (confirm(`Delete ${customer.name}? This cannot be undone.`)) {
            router.delete(route('customers.destroy', customer.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Customers
                    </h2>
                    {customers.length > 0 && (
                        <PageHelp>
                            <p>
                                Contact records that quotes and jobs attach to.
                            </p>
                            <p>
                                Every quote needs a customer - add one here, or
                                from the quote builder itself, before pricing
                                work for them.
                            </p>
                        </PageHelp>
                    )}
                </div>
            }
        >
            <Head title="Customers" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="flex justify-end gap-3">
                        {auth.isOwner && (
                            <CsvImportDialog
                                resource="customers"
                                label="Customers"
                                reloadProps={['customers']}
                            />
                        )}
                        {can('customers.create') && (
                            <Link href={route('customers.create')}>
                                <PrimaryButton>Add customer</PrimaryButton>
                            </Link>
                        )}
                    </div>

                    <Card className="overflow-hidden">
                        {customers.length === 0 ? (
                            <div className="p-6">
                                <PageHelp empty>
                                    <p>
                                        No customers yet. Contact records that
                                        quotes and jobs attach to.
                                    </p>
                                    <p>
                                        Every quote needs a customer - add one
                                        here, or from the quote builder itself,
                                        before pricing work for them.
                                    </p>
                                </PageHelp>
                            </div>
                        ) : (
                            <table className="min-w-full divide-y">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Name
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Email
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Phone
                                        </th>
                                        <th className="px-6 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y bg-white">
                                    {customers.map((customer) => (
                                        <tr key={customer.id}>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                                {customer.name}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {customer.email}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {customer.phone}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                                <Link
                                                    href={route(
                                                        'customers.edit',
                                                        customer.id,
                                                    )}
                                                    className="text-indigo-600 hover:underline"
                                                >
                                                    {can('customers.update')
                                                        ? 'Edit'
                                                        : 'View'}
                                                </Link>
                                                {can('customers.delete') && (
                                                    <button
                                                        onClick={() =>
                                                            destroy(customer)
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
