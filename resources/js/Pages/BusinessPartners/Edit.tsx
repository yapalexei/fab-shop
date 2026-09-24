import BusinessPartnerForm from '@/Components/BusinessPartnerForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';

interface BusinessPartnerDetail {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    notes: string | null;
    materials: { material_id: number; name: string; price: number }[];
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
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit business partner
                </h2>
            }
        >
            <Head title="Edit business partner" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <BusinessPartnerForm
                            materialOptions={materials}
                            serviceOptions={services}
                            submitUrl={route(
                                'business-partners.update',
                                businessPartner.id,
                            )}
                            method="put"
                            initialData={{
                                name: businessPartner.name,
                                email: businessPartner.email ?? '',
                                phone: businessPartner.phone ?? '',
                                address: businessPartner.address ?? '',
                                notes: businessPartner.notes ?? '',
                                materials: businessPartner.materials.map(
                                    (m) => ({
                                        itemId: m.material_id,
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
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
