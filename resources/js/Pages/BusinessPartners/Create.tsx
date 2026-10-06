import Breadcrumbs from '@/Components/Breadcrumbs';
import BusinessPartnerForm from '@/Components/BusinessPartnerForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import { Card } from '@/Components/ui/card';

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
                    <Card className="p-4 sm:p-8">
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
                                logo: null,
                                remove_logo: false,
                                materials: [],
                                services: [],
                            }}
                        />
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
