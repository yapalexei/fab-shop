import CsvImportDialog from '@/Components/CsvImportDialog';
import ListSearch from '@/Components/ListSearch';
import PageHelp from '@/Components/PageHelp';
import PrimaryButton from '@/Components/PrimaryButton';
import SampleDataDialog from '@/Components/SampleDataDialog';
import SampleDataPrompt from '@/Components/SampleDataPrompt';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useListSearch } from '@/lib/listSearch';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Card } from '@/Components/ui/card';

interface ServiceRow {
    id: number;
    name: string;
}

export default function Index({
    auth,
    services,
}: PageProps<{ services: ServiceRow[] } & { auth: { isOwner: boolean } }>) {
    const can = useCan();
    const [sampleOpen, setSampleOpen] = useState(false);
    const destroy = (service: ServiceRow) => {
        if (confirm(`Delete ${service.name}?`)) {
            router.delete(route('services.destroy', service.id));
        }
    };

    const search = useListSearch(services, (r) => [r.name]);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Services
                    </h2>
                    {services.length > 0 && (
                        <PageHelp>
                            <p>
                                Outsourced labor types - anodizing, powder
                                coating, and the like - that a business partner
                                performs on your shop's behalf.
                            </p>
                            <p>
                                Used by the quote builder's labor line items
                                when the work is outsourced rather than done
                                in-house.
                            </p>
                            {can('services.create') && (
                                <SampleDataPrompt
                                    label="services"
                                    onOpen={() => setSampleOpen(true)}
                                />
                            )}
                        </PageHelp>
                    )}
                </div>
            }
        >
            <Head title="Services" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-end gap-3">
                        {services.length > 0 && (
                            <ListSearch
                                search={search}
                                placeholder="Search services..."
                                className="me-auto"
                            />
                        )}
                        {auth.isOwner && (
                            <CsvImportDialog
                                resource="services"
                                label="Services"
                                reloadProps={['services']}
                            />
                        )}
                        {can('services.create') && (
                            <Link href={route('services.create')}>
                                <PrimaryButton>Add service</PrimaryButton>
                            </Link>
                        )}
                    </div>

                    <Card className="overflow-hidden">
                        {services.length === 0 ? (
                            <div className="p-6">
                                <PageHelp empty>
                                    <p>
                                        No services yet. These are outsourced
                                        labor types - anodizing, powder coating,
                                        and the like - that a business partner
                                        performs on your shop's behalf.
                                    </p>
                                    <p>
                                        Used by the quote builder's labor line
                                        items when the work is outsourced rather
                                        than done in-house.
                                    </p>
                                    {can('services.create') && (
                                        <SampleDataPrompt
                                            label="services"
                                            onOpen={() => setSampleOpen(true)}
                                        />
                                    )}
                                </PageHelp>
                            </div>
                        ) : (
                            <table className="min-w-full divide-y">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Name
                                        </th>
                                        <th className="px-6 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y bg-white">
                                    {search.visible.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={99}
                                                className="px-6 py-4 text-sm text-muted-foreground"
                                            >
                                                No services match “{search.query}”.
                                            </td>
                                        </tr>
                                    )}
                                    {search.visible.map((service) => (
                                        <tr key={service.id}>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                                {service.name}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                                <Link
                                                    href={route(
                                                        'services.edit',
                                                        service.id,
                                                    )}
                                                    className="text-indigo-600 hover:underline"
                                                >
                                                    {can('services.update')
                                                        ? 'Edit'
                                                        : 'View'}
                                                </Link>
                                                {can('services.delete') && (
                                                    <button
                                                        onClick={() =>
                                                            destroy(service)
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

            <SampleDataDialog
                resource="services"
                label="services"
                show={sampleOpen}
                onClose={() => setSampleOpen(false)}
            />
        </AuthenticatedLayout>
    );
}
