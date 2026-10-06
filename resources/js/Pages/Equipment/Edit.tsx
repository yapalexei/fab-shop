import EquipmentFields, {
    EquipmentFormData,
} from '@/Components/EquipmentFields';
import Breadcrumbs from '@/Components/Breadcrumbs';
import FormErrorSummary from '@/Components/FormErrorSummary';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Card } from '@/Components/ui/card';

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
    const can = useCan();
    const readOnly = !can('equipment.update');

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
                <Breadcrumbs
                    items={[
                        { label: 'Equipment', href: route('equipment.index') },
                        { label: 'Edit equipment' },
                    ]}
                />
            }
        >
            <Head title="Edit equipment" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <Card className="p-4 sm:p-8">
                        <form onSubmit={submit}>
                            <fieldset disabled={readOnly}>
                                <FormErrorSummary errors={errors} />

                                <EquipmentFields
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
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
