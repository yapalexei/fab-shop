import Breadcrumbs from '@/Components/Breadcrumbs';
import FormErrorSummary from '@/Components/FormErrorSummary';
import PrimaryButton from '@/Components/PrimaryButton';
import ServiceFields, { ServiceFormData } from '@/Components/ServiceFields';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Card } from '@/Components/ui/card';

export default function Create() {
    const { data, setData, post, processing, errors } =
        useForm<ServiceFormData>({
            name: '',
            notes: '',
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('services.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <Breadcrumbs
                    items={[
                        { label: 'Services', href: route('services.index') },
                        { label: 'Add service' },
                    ]}
                />
            }
        >
            <Head title="Add service" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <Card className="p-4 sm:p-8">
                        <form onSubmit={submit}>
                            <FormErrorSummary errors={errors} />

                            <ServiceFields
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
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
