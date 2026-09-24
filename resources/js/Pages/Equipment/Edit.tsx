import EquipmentFields, {
    EquipmentFormData,
} from '@/Components/EquipmentFields';
import FormErrorSummary from '@/Components/FormErrorSummary';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Equipment {
    id: number;
    name: string;
    billing_method: 'hourly' | 'flat';
    rate: number;
    notes: string | null;
}

export default function Edit({
    equipment,
}: PageProps<{ equipment: Equipment }>) {
    const { data, setData, put, processing, errors } =
        useForm<EquipmentFormData>({
            name: equipment.name,
            billing_method: equipment.billing_method,
            rate: String(equipment.rate),
            notes: equipment.notes ?? '',
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('equipment.update', equipment.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit equipment
                </h2>
            }
        >
            <Head title="Edit equipment" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
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
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
