import Breadcrumbs from '@/Components/Breadcrumbs';
import BusinessPartnerForm from '@/Components/BusinessPartnerForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';

export default function Create({
    materials,
    services,
}: PageProps<{
    materials: { id: number; name: string; unit: string | null }[];
    services: { id: number; name: string }[];
}>) {
    return (
        <AuthenticatedLayout
            header={
                <Breadcrumbs
                    items={[
                        { label: 'Business partners', href: route('business-partners.index') },
                        { label: 'Add business partner' },
                    ]}
                />
            }
        >
            <Head title="Add business partner" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <BusinessPartnerForm
                            materialOptions={materials}
                            serviceOptions={services}
                            submitUrl={route('business-partners.store')}
                            method="post"
                            initialData={{
                                name: '',
                                email: '',
                                phone: '',
                                address: '',
                                notes: '',
                                materials: [],
                                services: [],
                            }}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
