import CsvImportDialog from '@/Components/CsvImportDialog';
import PageHelp from '@/Components/PageHelp';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface EquipmentRow {
    id: number;
    name: string;
    billing_method: 'hourly' | 'flat';
    rate: number;
}

const currency = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

export default function Index({
    auth,
    equipment,
}: PageProps<{ equipment: EquipmentRow[] } & { auth: { isOwner: boolean } }>) {
    const can = useCan();
    const destroy = (item: EquipmentRow) => {
        if (confirm(`Delete ${item.name}?`)) {
            router.delete(route('equipment.destroy', item.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Equipment
                    </h2>
                    {equipment.length > 0 && (
                        <PageHelp>
                            <p>
                                Shop equipment billed on quotes - a laser table,
                                a brake press - priced hourly or flat.
                            </p>
                            <p>
                                Used by the quote builder's equipment line
                                items.
                            </p>
                        </PageHelp>
                    )}
                </div>
            }
        >
            <Head title="Equipment" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="flex justify-end gap-3">
                        {auth.isOwner && (
                            <CsvImportDialog
                                resource="equipment"
                                label="Equipment"
                                reloadProps={['equipment']}
                            />
                        )}
                        {can('equipment.create') && (
                            <Link href={route('equipment.create')}>
                                <PrimaryButton>Add equipment</PrimaryButton>
                            </Link>
                        )}
                    </div>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        {equipment.length === 0 ? (
                            <div className="p-6">
                                <PageHelp empty>
                                    <p>
                                        No equipment yet. This is shop equipment
                                        billed on quotes - a laser table, a
                                        brake press - priced hourly or flat.
                                    </p>
                                    <p>
                                        Used by the quote builder's equipment
                                        line items.
                                    </p>
                                </PageHelp>
                            </div>
                        ) : (
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Name
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Billing
                                        </th>
                                        <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Rate
                                        </th>
                                        <th className="px-6 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 bg-white">
                                    {equipment.map((item) => (
                                        <tr key={item.id}>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                                {item.name}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {item.billing_method ===
                                                'hourly'
                                                    ? 'Hourly'
                                                    : 'Flat fee'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-500">
                                                {currency.format(item.rate)}
                                                {item.billing_method ===
                                                    'hourly' && '/hr'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                                <Link
                                                    href={route(
                                                        'equipment.edit',
                                                        item.id,
                                                    )}
                                                    className="text-indigo-600 hover:text-indigo-900"
                                                >
                                                    {can('equipment.update')
                                                        ? 'Edit'
                                                        : 'View'}
                                                </Link>
                                                {can('equipment.delete') && (
                                                    <button
                                                        onClick={() =>
                                                            destroy(item)
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
