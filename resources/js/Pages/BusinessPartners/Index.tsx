import CsvImportDialog from '@/Components/CsvImportDialog';
import PageHelp from '@/Components/PageHelp';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Card } from '@/Components/ui/card';

interface BusinessPartnerRow {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
}

export default function Index({
    businessPartners,
    auth,
}: PageProps<
    { businessPartners: BusinessPartnerRow[] } & { auth: { isOwner: boolean } }
>) {
    const can = useCan();
    const destroy = (partner: BusinessPartnerRow) => {
        if (confirm(`Delete ${partner.name}?`)) {
            router.delete(route('business-partners.destroy', partner.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Business partners
                    </h2>
                    {businessPartners.length > 0 && (
                        <PageHelp>
                            <p>
                                Outside companies your shop works with -
                                suppliers, subcontractors, or both. The same
                                business can supply materials and perform
                                services, so there's no separate vendor/supplier
                                split.
                            </p>
                            <p>
                                A partner's prices are set on its own edit page,
                                and those are exactly what the quote builder
                                offers when a line item picks that partner.
                            </p>
                        </PageHelp>
                    )}
                </div>
            }
        >
            <Head title="Business partners" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="flex justify-end gap-3">
                        {auth.isOwner && (
                            <CsvImportDialog
                                resource="business-partners"
                                label="Business partners"
                                reloadProps={['businessPartners']}
                            />
                        )}
                        {can('business-partners.create') && (
                            <Link href={route('business-partners.create')}>
                                <PrimaryButton>
                                    Add business partner
                                </PrimaryButton>
                            </Link>
                        )}
                    </div>

                    <Card className="overflow-hidden">
                        {businessPartners.length === 0 ? (
                            <div className="p-6">
                                <PageHelp empty>
                                    <p>
                                        No business partners yet. These are
                                        outside companies your shop works with -
                                        suppliers, subcontractors, or both. The
                                        same business can supply materials and
                                        perform services.
                                    </p>
                                    <p>
                                        A partner's prices, set on its own edit
                                        page, are exactly what the quote builder
                                        offers when a line item picks that
                                        partner.
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
                                                    className="text-indigo-600 hover:underline"
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
