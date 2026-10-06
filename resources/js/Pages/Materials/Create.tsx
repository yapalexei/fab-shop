import AiCatalogDraftPanel from '@/Components/AiCatalogDraftPanel';
import { AiStatus } from '@/Components/AiQuoteDraftPanel';
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
import { Card } from '@/Components/ui/card';

interface DraftFields {
    name: string | null;
    unit: string | null;
    material_type_id: number | null;
    notes: string | null;
}

export default function Create({
    materialTypes,
    ai,
}: PageProps<{ materialTypes: MaterialTypeOption[]; ai: AiStatus }>) {
    const { data, setData, post, processing, errors } =
        useForm<MaterialFormData>({
            name: '',
            unit: '',
            notes: '',
            material_type_id: '',
            image: null,
            remove_image: false,
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('materials.store'), { forceFormData: true });
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
                    <Card className="p-4 sm:p-8">
                        <AiCatalogDraftPanel<DraftFields>
                            kind="materials"
                            noun="material"
                            ai={ai}
                            onDraft={(f) => {
                                // Only what the page stated: a null leaves
                                // whatever is already typed.
                                if (f.name !== null) setData('name', f.name);
                                if (f.unit !== null) setData('unit', f.unit);
                                if (f.material_type_id !== null)
                                    setData(
                                        'material_type_id',
                                        String(f.material_type_id),
                                    );
                                if (f.notes !== null)
                                    setData('notes', f.notes);
                            }}
                        />

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
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
