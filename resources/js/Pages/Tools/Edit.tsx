import Breadcrumbs from '@/Components/Breadcrumbs';
import FormErrorSummary from '@/Components/FormErrorSummary';
import PrimaryButton from '@/Components/PrimaryButton';
import ToolFields, {
    MaterialOption,
    ToolFormData,
    ToolModelFile,
} from '@/Components/ToolFields';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Card } from '@/Components/ui/card';

interface Tool {
    id: number;
    name: string;
    cuts: number[];
    cost: number;
    unit: string;
    notes: string | null;
    part_number: string | null;
    source_url: string | null;
    price_checked_at: string | null;
    image_url: string | null;
    model: ToolModelFile | null;
}

export default function Edit({
    tool,
    materialOptions,
}: PageProps<{ tool: Tool; materialOptions: MaterialOption[] }>) {
    const readOnly = !useCan()('tooling.update');

    // Updates are a POST spoofing PUT so the multipart body (files) is parsed.
    const { data, setData, post, processing, errors } = useForm<
        ToolFormData & { _method: 'put' }
    >({
        _method: 'put',
        name: tool.name,
        cuts: tool.cuts,
        cost: tool.cost.toString(),
        unit: tool.unit,
        notes: tool.notes ?? '',
        part_number: tool.part_number ?? '',
        source_url: tool.source_url ?? '',
        mark_price_checked: false,
        image: null,
        remove_image: false,
        model: null,
        remove_model: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('tools.update', tool.id), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <Breadcrumbs
                    items={[
                        { label: 'Tooling', href: route('tools.index') },
                        { label: readOnly ? tool.name : 'Edit tooling' },
                    ]}
                />
            }
        >
            <Head title={tool.name} />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <Card className="p-4 sm:p-8">
                        <form onSubmit={submit}>
                            <fieldset disabled={readOnly} className="min-w-0">
                                <FormErrorSummary
                                    errors={errors as Record<string, string>}
                                />

                                <ToolFields
                                    data={data}
                                    setData={setData}
                                    errors={errors}
                                    materialOptions={materialOptions}
                                    priceCheckedAt={tool.price_checked_at}
                                    currentImage={tool.image_url}
                                    currentModel={tool.model}
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
