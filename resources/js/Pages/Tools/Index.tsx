import PrimaryButton from '@/Components/PrimaryButton';
import { MaterialOption } from '@/Components/ToolFields';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface ToolRow {
    id: number;
    name: string;
    cuts: number[];
    cost: number;
    unit: string;
}

const currency = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

export default function Index({
    tools,
    materialOptions,
}: PageProps<{ tools: ToolRow[]; materialOptions: MaterialOption[] }>) {
    const can = useCan();
    const [material, setMaterial] = useState<number | null>(null);

    const labelFor = (value: number) =>
        materialOptions.find((m) => m.value === value)?.label ?? value;
    const visible = material
        ? tools.filter((tool) => tool.cuts.includes(material))
        : tools;

    const destroy = (tool: ToolRow) => {
        if (
            confirm(`Delete ${tool.name}? Quotes that used it keep their line.`)
        ) {
            router.delete(route('tools.destroy', tool.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Tooling
                </h2>
            }
        >
            <Head title="Tooling" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div
                            className="flex flex-wrap gap-2"
                            aria-label="Filter by material"
                        >
                            {[
                                { value: null, label: 'All' },
                                ...materialOptions,
                            ].map((option) => (
                                <button
                                    key={option.value ?? 'all'}
                                    type="button"
                                    onClick={() => setMaterial(option.value)}
                                    aria-pressed={material === option.value}
                                    className={`rounded-full border px-3 py-1 text-xs font-medium ${material === option.value ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'}`}
                                >
                                    {option.label}
                                </button>
                            ))}
                        </div>
                        {can('tooling.create') && (
                            <Link href={route('tools.create')}>
                                <PrimaryButton>Add tooling</PrimaryButton>
                            </Link>
                        )}
                    </div>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        {visible.length === 0 ? (
                            <div className="p-6 text-gray-500">
                                {tools.length === 0
                                    ? 'No tooling yet - add drill bits, hole saws, cut-off wheels and the like to bill them on quotes.'
                                    : `No tooling rated for ${labelFor(material ?? 0)}.`}
                            </div>
                        ) : (
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Name
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Cuts
                                        </th>
                                        <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Cost
                                        </th>
                                        <th className="px-6 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 bg-white">
                                    {visible.map((tool) => (
                                        <tr key={tool.id}>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                                {tool.name}
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex flex-wrap gap-1">
                                                    {tool.cuts.map((value) => (
                                                        <span
                                                            key={value}
                                                            className="inline-flex rounded-full bg-gray-100 px-2 text-xs font-medium leading-5 text-gray-700"
                                                        >
                                                            {labelFor(value)}
                                                        </span>
                                                    ))}
                                                </div>
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-500">
                                                {currency.format(tool.cost)} /{' '}
                                                {tool.unit}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                                <Link
                                                    href={route(
                                                        'tools.edit',
                                                        tool.id,
                                                    )}
                                                    className="text-indigo-600 hover:text-indigo-900"
                                                >
                                                    {can('tooling.update')
                                                        ? 'Edit'
                                                        : 'View'}
                                                </Link>
                                                {can('tooling.delete') && (
                                                    <button
                                                        onClick={() =>
                                                            destroy(tool)
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
