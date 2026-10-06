import Breadcrumbs from '@/Components/Breadcrumbs';
import FormErrorSummary from '@/Components/FormErrorSummary';
import PrimaryButton from '@/Components/PrimaryButton';
import ToolFields, {
    MaterialOption,
    ToolFormData,
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
}

export default function Edit({
    tool,
    materialOptions,
}: PageProps<{ tool: Tool; materialOptions: MaterialOption[] }>) {
    const readOnly = !useCan()('tooling.update');

    const { data, setData, put, processing, errors } = useForm<ToolFormData>({
        name: tool.name,
        cuts: tool.cuts,
        cost: tool.cost.toString(),
        unit: tool.unit,
        notes: tool.notes ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('tools.update', tool.id));
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
                            <fieldset disabled={readOnly}>
                                <FormErrorSummary
                                    errors={errors as Record<string, string>}
                                />

                                <ToolFields
                                    data={data}
                                    setData={setData}
                                    errors={errors}
                                    materialOptions={materialOptions}
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
