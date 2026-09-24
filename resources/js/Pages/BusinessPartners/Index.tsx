import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface BusinessPartnerRow {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
}

export default function Index({
    businessPartners,
}: PageProps<{ businessPartners: BusinessPartnerRow[] }>) {
    const can = useCan();
    const destroy = (partner: BusinessPartnerRow) => {
        if (confirm(`Delete ${partner.name}?`)) {
            router.delete(route('business-partners.destroy', partner.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Business partners
                </h2>
            }
        >
            <Head title="Business partners" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {can('business-partners.create') && (
                        <div className="flex justify-end">
                            <Link href={route('business-partners.create')}>
                                <PrimaryButton>
                                    Add business partner
                                </PrimaryButton>
                            </Link>
                        </div>
                    )}

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        {businessPartners.length === 0 ? (
                            <div className="p-6 text-gray-500">
                                No business partners yet.
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
                                    {businessPartners.map((partner) => (
                                        <tr key={partner.id}>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                                {partner.name}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {partner.email}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {partner.phone}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                                <Link
                                                    href={route(
                                                        'business-partners.edit',
                                                        partner.id,
                                                    )}
                                                    className="text-indigo-600 hover:text-indigo-900"
                                                >
                                                    {can(
                                                        'business-partners.update',
                                                    )
                                                        ? 'Edit'
                                                        : 'View'}
                                                </Link>
                                                {can(
                                                    'business-partners.delete',
                                                ) && (
                                                    <button
                                                        onClick={() =>
                                                            destroy(partner)
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
