import FormErrorSummary from '@/Components/FormErrorSummary';
import MaterialFields, {
    MaterialFormData,
} from '@/Components/MaterialFields';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Material {
    id: number;
    name: string;
    unit: string | null;
    notes: string | null;
}

export default function Edit({ material }: PageProps<{ material: Material }>) {
    const { data, setData, put, processing, errors } =
        useForm<MaterialFormData>({
            name: material.name,
            unit: material.unit ?? '',
            notes: material.notes ?? '',
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('materials.update', material.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit material
                </h2>
            }
        >
            <Head title="Edit material" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <form onSubmit={submit}>
                            <FormErrorSummary errors={errors} />

                            <MaterialFields
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
