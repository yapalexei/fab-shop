import CustomerFields, {
    CustomerFormData,
} from '@/Components/CustomerFields';
import Breadcrumbs from '@/Components/Breadcrumbs';
import FormErrorSummary from '@/Components/FormErrorSummary';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Customer {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    notes: string | null;
}

export default function Edit({ customer }: PageProps<{ customer: Customer }>) {
    const can = useCan();
    const readOnly = !can('customers.update');

    const { data, setData, put, processing, errors } =
        useForm<CustomerFormData>({
            name: customer.name,
            email: customer.email ?? '',
            phone: customer.phone ?? '',
            address: customer.address ?? '',
            notes: customer.notes ?? '',
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('customers.update', customer.id));
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
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <form onSubmit={submit}>
                            <fieldset disabled={readOnly}>
                                <FormErrorSummary errors={errors} />

                                <CustomerFields
                                    data={data}
                                    setData={setData}
                                    errors={errors}
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
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
