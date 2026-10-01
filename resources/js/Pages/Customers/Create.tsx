import CustomerFields, {
    CustomerFormData,
} from '@/Components/CustomerFields';
import Breadcrumbs from '@/Components/Breadcrumbs';
import FormErrorSummary from '@/Components/FormErrorSummary';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Create() {
    const { data, setData, post, processing, errors } =
        useForm<CustomerFormData>({
            name: '',
            email: '',
            phone: '',
            address: '',
            notes: '',
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('customers.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <Breadcrumbs
                    items={[
                        { label: 'Customers', href: route('customers.index') },
                        { label: 'Add customer' },
                    ]}
                />
            }
        >
            <Head title="Add customer" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <form onSubmit={submit}>
                            <FormErrorSummary errors={errors} />

                            <CustomerFields
                                data={data}
                                setData={setData}
                                errors={errors}
                            />

                            <div className="mt-6 flex justify-end">
                                <PrimaryButton disabled={processing}>
                                    Save
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
