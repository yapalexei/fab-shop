import PageHelp from '@/Components/PageHelp';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Card } from '@/Components/ui/card';

interface ProcedureRow {
    id: number;
    name: string;
    code: string | null;
    certifications_count: number;
    records_count: number;
}

export default function Index({
    procedures,
}: PageProps<{ procedures: ProcedureRow[] }>) {
    const can = useCan();
    const destroy = (procedure: ProcedureRow) => {
        if (confirm(`Delete ${procedure.name}?`)) {
            router.delete(route('procedures.destroy', procedure.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Procedures
                    </h2>
                    {procedures.length > 0 && (
                        <PageHelp>
                            <p>
                                Shop-defined processes people get qualified
                                against - a welding procedure, an NDT method,
                                equipment that needs an operator card.
                            </p>
                            <p>
                                Tied to Certifications and to a job's
                                qualification records, so the app can tell
                                whether a job's work was actually covered by
                                someone's valid certification.
                            </p>
                        </PageHelp>
                    )}
                </div>
            }
        >
            <Head title="Procedures" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="flex justify-end gap-3">
                        {can('procedures.create') && (
                            <Link href={route('procedures.create')}>
                                <PrimaryButton>Add procedure</PrimaryButton>
                            </Link>
                        )}
                    </div>

                    <Card className="overflow-hidden">
                        {procedures.length === 0 ? (
                            <div className="p-6">
                                <PageHelp empty>
                                    <p>
                                        No procedures yet. These are
                                        shop-defined processes people get
                                        qualified against - a welding procedure,
                                        an NDT method, equipment that needs an
                                        operator card.
                                    </p>
                                    <p>
                                        Tied to Certifications and to a job's
                                        qualification records, so the app can
                                        tell whether a job's work was actually
                                        covered by someone's valid
                                        certification.
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
                                            Code
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Certifications
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Job records
                                        </th>
                                        <th className="px-6 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y bg-white">
                                    {procedures.map((procedure) => (
                                        <tr key={procedure.id}>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                                {procedure.name}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {procedure.code ?? '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {procedure.certifications_count}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {procedure.records_count}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                                <Link
                                                    href={route(
                                                        'procedures.edit',
                                                        procedure.id,
                                                    )}
                                                    className="text-indigo-600 hover:underline"
                                                >
                                                    {can('procedures.update')
                                                        ? 'Edit'
                                                        : 'View'}
                                                </Link>
                                                {can('procedures.delete') && (
                                                    <button
                                                        onClick={() =>
                                                            destroy(procedure)
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
