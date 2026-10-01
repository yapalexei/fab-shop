import Breadcrumbs from '@/Components/Breadcrumbs';
import FormErrorSummary from '@/Components/FormErrorSummary';
import PrimaryButton from '@/Components/PrimaryButton';
import ToolFields, {
    MaterialOption,
    ToolFormData,
} from '@/Components/ToolFields';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Create({
    materialOptions,
}: PageProps<{ materialOptions: MaterialOption[] }>) {
    const { data, setData, post, processing, errors } = useForm<ToolFormData>({
        name: '',
        cuts: [],
        cost: '',
        unit: 'each',
        notes: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('tools.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <Breadcrumbs
                    items={[
                        { label: 'Tooling', href: route('tools.index') },
                        { label: 'Add tooling' },
                    ]}
                />
            }
        >
            <Head title="Add tooling" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <form onSubmit={submit}>
                            <FormErrorSummary
                                errors={errors as Record<string, string>}
                            />

                            <ToolFields
                                data={data}
                                setData={setData}
                                errors={errors}
                                materialOptions={materialOptions}
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
