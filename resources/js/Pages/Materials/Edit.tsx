import Breadcrumbs from '@/Components/Breadcrumbs';
import FormErrorSummary from '@/Components/FormErrorSummary';
import MaterialFields, {
    MaterialFormData,
    MaterialTypeOption,
} from '@/Components/MaterialFields';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Card } from '@/Components/ui/card';

interface Material {
    id: number;
    name: string;
    unit: string | null;
    notes: string | null;
    material_type_id: number | null;
}

export default function Edit({
    material,
    materialTypes,
}: PageProps<{ material: Material; materialTypes: MaterialTypeOption[] }>) {
    const can = useCan();
    const readOnly = !can('materials.update');

    const { data, setData, put, processing, errors } =
        useForm<MaterialFormData>({
            name: material.name,
            unit: material.unit ?? '',
            notes: material.notes ?? '',
            material_type_id: material.material_type_id?.toString() ?? '',
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('materials.update', material.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <Breadcrumbs
                    items={[
                        { label: 'Materials', href: route('materials.index') },
                        { label: 'Edit material' },
                    ]}
                />
            }
        >
            <Head title="Edit material" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <Card className="p-4 sm:p-8">
                        <form onSubmit={submit}>
                            <fieldset disabled={readOnly}>
                                <FormErrorSummary errors={errors} />

                                <MaterialFields
                                    data={data}
                                    setData={setData}
                                    errors={errors}
                                    materialTypes={materialTypes}
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
