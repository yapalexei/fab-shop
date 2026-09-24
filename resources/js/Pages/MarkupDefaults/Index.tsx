import FormErrorSummary from '@/Components/FormErrorSummary';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface MarkupDefaultRow {
    line_item_type: 'material' | 'labor' | 'equipment' | 'tooling';
    default_markup_percent: number;
}

const typeLabels: Record<string, string> = {
    material: 'Material',
    labor: 'Labor',
    equipment: 'Equipment',
    tooling: 'Tooling',
};

export default function Index({
    defaults,
    canManage,
}: PageProps<{ defaults: MarkupDefaultRow[]; canManage: boolean }>) {
    const { data, setData, patch, processing, errors } = useForm<{
        defaults: MarkupDefaultRow[];
    }>({ defaults });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('markup-defaults.update'));
    };

    const update = (index: number, value: string) => {
        const next = [...data.defaults];
        next[index] = { ...next[index], default_markup_percent: Number(value) };
        setData('defaults', next);
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Default markup
                </h2>
            }
        >
            <Head title="Default markup" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <p className="mb-6 text-sm text-gray-500">
                            A new quote line item's markup is pre-filled from
                            these defaults based on its type - always
                            overridable per line. A type with no default here
                            starts at 0%.
                        </p>

                        <form onSubmit={submit}>
                            <FormErrorSummary
                                errors={errors as Record<string, string>}
                            />

                            <div className="space-y-4">
                                {data.defaults.map((row, index) => (
                                    <div
                                        key={row.line_item_type}
                                        className="flex items-center justify-between"
                                    >
                                        <InputLabel
                                            htmlFor={`rate-${row.line_item_type}`}
                                            value={
                                                typeLabels[row.line_item_type]
                                            }
                                        />
                                        <div className="flex items-center gap-1">
                                            <TextInput
                                                id={`rate-${row.line_item_type}`}
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                className="w-24"
                                                value={
                                                    row.default_markup_percent
                                                }
                                                onChange={(e) =>
                                                    update(
                                                        index,
                                                        e.target.value,
                                                    )
                                                }
                                                disabled={!canManage}
                                            />
                                            <span className="text-sm text-gray-500">
                                                %
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {canManage && (
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
