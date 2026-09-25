import CsvImportDialog from '@/Components/CsvImportDialog';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

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
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Customers
                </h2>
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

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        {customers.length === 0 ? (
                            <div className="p-6 text-gray-500">
                                No customers yet.
                            </div>
                        ) : (
                            <table className="min-w-full divide-y divide-gray-200">
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
                                <tbody className="divide-y divide-gray-200 bg-white">
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
                                                    className="text-indigo-600 hover:text-indigo-900"
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
