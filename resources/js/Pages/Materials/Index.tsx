import CsvImportDialog from '@/Components/CsvImportDialog';
import PageHelp from '@/Components/PageHelp';
import PrimaryButton from '@/Components/PrimaryButton';
import SampleDataDialog from '@/Components/SampleDataDialog';
import SampleDataPrompt from '@/Components/SampleDataPrompt';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';

interface MaterialRow {
    id: number;
    name: string;
    unit: string | null;
    material_type: string | null;
}

export default function Index({
    auth,
    materials,
}: PageProps<{ materials: MaterialRow[] } & { auth: { isOwner: boolean } }>) {
    const can = useCan();
    const [sampleOpen, setSampleOpen] = useState(false);
    const destroy = (material: MaterialRow) => {
        if (confirm(`Delete ${material.name}?`)) {
            router.delete(route('materials.destroy', material.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Materials
                    </h2>
                    {materials.length > 0 && (
                        <PageHelp>
                            <p>
                                The shop's material catalog - steel, aluminum,
                                and whatever else you stock.
                            </p>
                            <p>
                                Used by the quote builder's material line items
                                to pre-fill a description, unit, and supplier
                                price - a line can still be typed freeform if
                                the material isn't in here.
                            </p>
                            {can('materials.create') && (
                                <SampleDataPrompt
                                    label="materials"
                                    onOpen={() => setSampleOpen(true)}
                                />
                            )}
                        </PageHelp>
                    )}
                </div>
            }
        >
            <Head title="Materials" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="flex justify-end gap-3">
                        {auth.isOwner && (
                            <CsvImportDialog
                                resource="materials"
                                label="Materials"
                                reloadProps={['materials']}
                            />
                        )}
                        {can('materials.create') && (
                            <Link href={route('materials.create')}>
                                <PrimaryButton>Add material</PrimaryButton>
                            </Link>
                        )}
                    </div>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        {materials.length === 0 ? (
                            <div className="p-6">
                                <PageHelp empty>
                                    <p>
                                        No materials yet. This is the shop's
                                        material catalog - steel, aluminum, and
                                        whatever else you stock.
                                    </p>
                                    <p>
                                        Used by the quote builder's material
                                        line items to pre-fill a description,
                                        unit, and supplier price - a line can
                                        still be typed freeform if the material
                                        isn't in here.
                                    </p>
                                    {can('materials.create') && (
                                        <SampleDataPrompt
                                            label="materials"
                                            onOpen={() => setSampleOpen(true)}
                                        />
                                    )}
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
                                            Type
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Unit
                                        </th>
                                        <th className="px-6 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 bg-white">
                                    {materials.map((material) => (
                                        <tr key={material.id}>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                                {material.name}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {material.material_type ?? '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {material.unit}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                                <Link
                                                    href={route(
                                                        'materials.edit',
                                                        material.id,
                                                    )}
                                                    className="text-indigo-600 hover:text-indigo-900"
                                                >
                                                    {can('materials.update')
                                                        ? 'Edit'
                                                        : 'View'}
                                                </Link>
                                                {can('materials.delete') && (
                                                    <button
                                                        onClick={() =>
                                                            destroy(material)
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

            <SampleDataDialog
                resource="materials"
                label="materials"
                note="Metals, plastics and woods common in fabrication, filed under the built-in material types."
                show={sampleOpen}
                onClose={() => setSampleOpen(false)}
            />
        </AuthenticatedLayout>
    );
}
