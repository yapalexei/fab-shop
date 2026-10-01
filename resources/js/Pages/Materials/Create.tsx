import Breadcrumbs from '@/Components/Breadcrumbs';
import FormErrorSummary from '@/Components/FormErrorSummary';
import MaterialFields, {
    MaterialFormData,
    MaterialTypeOption,
} from '@/Components/MaterialFields';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Create({
    materialTypes,
}: PageProps<{ materialTypes: MaterialTypeOption[] }>) {
    const { data, setData, post, processing, errors } =
        useForm<MaterialFormData>({
            name: '',
            unit: '',
            notes: '',
            material_type_id: '',
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('materials.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <Breadcrumbs
                    items={[
                        { label: 'Materials', href: route('materials.index') },
                        { label: 'Add material' },
                    ]}
                />
            }
        >
            <Head title="Add material" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <form onSubmit={submit}>
                            <FormErrorSummary errors={errors} />

                            <MaterialFields
                                data={data}
                                setData={setData}
                                errors={errors}
                                materialTypes={materialTypes}
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
