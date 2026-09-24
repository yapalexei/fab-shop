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

interface Tool {
    id: number;
    name: string;
    cuts: string[];
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
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    {readOnly ? tool.name : 'Edit tooling'}
                </h2>
            }
        >
            <Head title={tool.name} />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
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
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
