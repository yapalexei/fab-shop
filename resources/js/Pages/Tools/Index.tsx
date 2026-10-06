import CsvImportDialog from '@/Components/CsvImportDialog';
import PageHelp from '@/Components/PageHelp';
import PrimaryButton from '@/Components/PrimaryButton';
import { MaterialOption } from '@/Components/ToolFields';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';

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
    auth,
}: PageProps<
    { tools: ToolRow[]; materialOptions: MaterialOption[] } & {
        auth: { isOwner: boolean };
    }
>) {
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
                <div className="flex items-center gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Tooling
                    </h2>
                    {tools.length > 0 && (
                        <PageHelp>
                            <p>
                                Consumable tooling billed onto quotes - drill
                                bits, hole saws, cut-off wheels - priced per
                                unit and tagged with the material types each one
                                is rated to cut.
                            </p>
                            <p>
                                Used by the quote builder's tooling line items,
                                and by the quote's tooling-mismatch warning (a
                                tool rated for none of a quote's materials).
                            </p>
                        </PageHelp>
                    )}
                </div>
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
                                    className={`rounded-full border px-3 py-1 text-xs font-medium ${material === option.value ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-300 bg-white text-gray-600 hover:bg-accent'}`}
                                >
                                    {option.label}
                                </button>
                            ))}
                        </div>
                        <div className="flex gap-3">
                            {auth.isOwner && (
                                <CsvImportDialog
                                    resource="tools"
                                    label="Tooling"
                                    reloadProps={['tools']}
                                />
                            )}
                            {can('tooling.create') && (
                                <Link href={route('tools.create')}>
                                    <PrimaryButton>Add tooling</PrimaryButton>
                                </Link>
                            )}
                        </div>
                    </div>

                    <Card className="overflow-hidden">
                        {visible.length === 0 ? (
                            tools.length === 0 ? (
                                <div className="p-6">
                                    <PageHelp empty>
                                        <p>
                                            No tooling yet. This is consumable
                                            tooling billed onto quotes - drill
                                            bits, hole saws, cut-off wheels -
                                            priced per unit and tagged with the
                                            material types each one is rated to
                                            cut.
                                        </p>
                                        <p>
                                            Used by the quote builder's tooling
                                            line items, and by the quote's
                                            tooling-mismatch warning.
                                        </p>
                                    </PageHelp>
                                </div>
                            ) : (
                                <div className="p-6 text-gray-500">
                                    No tooling rated for{' '}
                                    {labelFor(material ?? 0)}.
                                </div>
                            )
                        ) : (
                            <table className="min-w-full divide-y">
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
                                <tbody className="divide-y bg-white">
                                    {visible.map((tool) => (
                                        <tr key={tool.id}>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                                {tool.name}
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex flex-wrap gap-1">
                                                    {tool.cuts.map((value) => (
                                                        <Badge
                                                            key={value}
                                                            variant="secondary"
                                                        >
                                                            {labelFor(value)}
                                                        </Badge>
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
                                                    className="text-indigo-600 hover:underline"
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
