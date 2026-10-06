import EquipmentFields, {
    EquipmentFormData,
} from '@/Components/EquipmentFields';
import Breadcrumbs from '@/Components/Breadcrumbs';
import FormErrorSummary from '@/Components/FormErrorSummary';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Card } from '@/Components/ui/card';

export default function Create() {
    const { data, setData, post, processing, errors } =
        useForm<EquipmentFormData>({
            name: '',
            billing_method: 'hourly',
            rate: '',
            notes: '',
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('equipment.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <Breadcrumbs
                    items={[
                        { label: 'Equipment', href: route('equipment.index') },
                        { label: 'Add equipment' },
                    ]}
                />
            }
        >
            <Head title="Add equipment" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <Card className="p-4 sm:p-8">
                        <form onSubmit={submit}>
                            <FormErrorSummary errors={errors} />

                            <EquipmentFields
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
