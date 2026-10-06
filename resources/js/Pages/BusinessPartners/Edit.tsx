import Breadcrumbs from '@/Components/Breadcrumbs';
import BusinessPartnerForm, { TypeOption } from '@/Components/BusinessPartnerForm';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Card } from '@/Components/ui/card';

interface BusinessPartnerDetail {
    id: number;
    name: string;
    type: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    notes: string | null;
    logo_url: string | null;
    materials: {
        material_id: number;
        name: string;
        dimension: string | null;
        price: number;
        part_number: string | null;
        source_url: string | null;
        price_checked_at: string | null;
    }[];
    services: { service_id: number; name: string; price: number }[];
}

export default function Edit({
    businessPartner,
    materials,
    services,
    typeOptions,
    customerId,
}: PageProps<{
    businessPartner: BusinessPartnerDetail;
    materials: { id: number; name: string; unit: string | null }[];
    services: { id: number; name: string }[];
    typeOptions: TypeOption[];
    /** Set when this business is also a customer. */
    customerId: number | null;
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
                <div className="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
                    {customerId ? (
                        <Card className="flex items-center justify-between gap-4 p-4 text-sm">
                            <span>
                                This business is also one of your customers.
                                Its name and contact details are kept the same
                                in both places.
                            </span>
                            {can('customers.view') && (
                                <Link
                                    href={route('customers.edit', customerId)}
                                    className="shrink-0 font-medium underline"
                                >
                                    View customer
                                </Link>
                            )}
                        </Card>
                    ) : (
                        can('customers.create') && (
                            <Card className="flex items-center justify-between gap-4 p-4 text-sm">
                                <span>
                                    Do you also sell to this business? Make it a
                                    customer to quote and invoice it. Its name,
                                    contact details and logo are copied (notes
                                    aren't), and the name and contact details
                                    stay in sync afterwards.
                                </span>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="shrink-0"
                                    onClick={() =>
                                        router.post(
                                            route(
                                                'business-partners.make-customer',
                                                businessPartner.id,
                                            ),
                                        )
                                    }
                                >
                                    Make customer
                                </Button>
                            </Card>
                        )
                    )}

                    <Card className="p-4 sm:p-8">
                        <BusinessPartnerForm
                            materialOptions={materials}
                            serviceOptions={services}
                            typeOptions={typeOptions}
                            submitUrl={route(
                                'business-partners.update',
                                businessPartner.id,
                            )}
                            method="put"
                            currentLogo={businessPartner.logo_url}
                            readOnly={!can('business-partners.update')}
                            initialData={{
                                name: businessPartner.name,
                                type: businessPartner.type,
                                email: businessPartner.email ?? '',
                                phone: businessPartner.phone ?? '',
                                address: businessPartner.address ?? '',
                                notes: businessPartner.notes ?? '',
                                logo: null,
                                remove_logo: false,
                                materials: businessPartner.materials.map(
                                    (m) => ({
                                        itemId: m.material_id,
                                        dimension: m.dimension ?? '',
                                        price: m.price,
                                        part_number: m.part_number ?? '',
                                        source_url: m.source_url ?? '',
                                        price_checked_at: m.price_checked_at,
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
