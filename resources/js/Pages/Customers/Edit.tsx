import CustomerFields, {
    CustomerFormData,
} from '@/Components/CustomerFields';
import Breadcrumbs from '@/Components/Breadcrumbs';
import FormErrorSummary from '@/Components/FormErrorSummary';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Card } from '@/Components/ui/card';

interface Customer {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    notes: string | null;
    logo_url: string | null;
}

export default function Edit({
    customer,
    businessPartnerId,
}: PageProps<{
    customer: Customer;
    /** Set when this business is also a business partner. */
    businessPartnerId: number | null;
}>) {
    const can = useCan();
    const readOnly = !can('customers.update');

    const { data, setData, post, processing, errors } =
        useForm<CustomerFormData>({
            _method: 'put',
            name: customer.name,
            email: customer.email ?? '',
            phone: customer.phone ?? '',
            address: customer.address ?? '',
            notes: customer.notes ?? '',
            logo: null,
            remove_logo: false,
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        // Files can't travel in a PUT body, so it's a POST spoofing PUT.
        post(route('customers.update', customer.id), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <Breadcrumbs
                    items={[
                        { label: 'Customers', href: route('customers.index') },
                        { label: 'Edit customer' },
                    ]}
                />
            }
        >
            <Head title="Edit customer" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
                    {businessPartnerId && (
                        <Card className="flex items-center justify-between gap-4 p-4 text-sm">
                            <span>
                                This business is also one of your business
                                partners. Its name and contact details are kept
                                the same in both places.
                            </span>
                            {can('business-partners.view') && (
                                <Link
                                    href={route(
                                        'business-partners.edit',
                                        businessPartnerId,
                                    )}
                                    className="shrink-0 font-medium underline"
                                >
                                    View business partner
                                </Link>
                            )}
                        </Card>
                    )}

                    <Card className="p-4 sm:p-8">
                        <form onSubmit={submit}>
                            <fieldset disabled={readOnly}>
                                <FormErrorSummary errors={errors} />

                                <CustomerFields
                                    data={data}
                                    setData={setData}
                                    errors={errors}
                                    currentLogo={customer.logo_url}
                                />
                            </fieldset>

                            {!readOnly && (
                                <div className="mt-6 flex justify-end">
                                    <PrimaryButton disabled={processing}>
                                        Save
                                    </PrimaryButton>
                                </div>
                            )}
                        </form>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
