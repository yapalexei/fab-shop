import Breadcrumbs from '@/Components/Breadcrumbs';
import BusinessPartnerForm from '@/Components/BusinessPartnerForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import { Card } from '@/Components/ui/card';

interface BusinessPartnerDetail {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    notes: string | null;
    materials: {
        material_id: number;
        name: string;
        dimension: string | null;
        price: number;
    }[];
    services: { service_id: number; name: string; price: number }[];
}

export default function Edit({
    businessPartner,
    materials,
    services,
}: PageProps<{
    businessPartner: BusinessPartnerDetail;
    materials: { id: number; name: string; unit: string | null }[];
    services: { id: number; name: string }[];
}>) {
    const can = useCan();

    return (
        <AuthenticatedLayout
            header={
                <Breadcrumbs
                    items={[
                        { label: 'Business partners', href: route('business-partners.index') },
                        { label: 'Edit business partner' },
                    ]}
                />
            }
        >
            <Head title="Edit business partner" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <Card className="p-4 sm:p-8">
                        <BusinessPartnerForm
                            materialOptions={materials}
                            serviceOptions={services}
                            submitUrl={route(
                                'business-partners.update',
                                businessPartner.id,
                            )}
                            method="put"
                            readOnly={!can('business-partners.update')}
                            initialData={{
                                name: businessPartner.name,
                                email: businessPartner.email ?? '',
                                phone: businessPartner.phone ?? '',
                                address: businessPartner.address ?? '',
                                notes: businessPartner.notes ?? '',
                                materials: businessPartner.materials.map(
                                    (m) => ({
                                        itemId: m.material_id,
                                        dimension: m.dimension ?? '',
                                        price: m.price,
                                    }),
                                ),
                                services: businessPartner.services.map(
                                    (s) => ({
                                        itemId: s.service_id,
                                        price: s.price,
                                    }),
                                ),
                            }}
                        />
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
