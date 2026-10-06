import Breadcrumbs from '@/Components/Breadcrumbs';
import FormErrorSummary from '@/Components/FormErrorSummary';
import PrimaryButton from '@/Components/PrimaryButton';
import ProcedureFields, {
    ProcedureFormData,
} from '@/Components/ProcedureFields';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Card } from '@/Components/ui/card';

interface Procedure {
    id: number;
    name: string;
    code: string | null;
    notes: string | null;
}

export default function Edit({
    procedure,
}: PageProps<{ procedure: Procedure }>) {
    const can = useCan();
    const readOnly = !can('procedures.update');

    const { data, setData, put, processing, errors } =
        useForm<ProcedureFormData>({
            name: procedure.name,
            code: procedure.code ?? '',
            notes: procedure.notes ?? '',
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('procedures.update', procedure.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <Breadcrumbs
                    items={[
                        { label: 'Procedures', href: route('procedures.index') },
                        { label: 'Edit procedure' },
                    ]}
                />
            }
        >
            <Head title="Edit procedure" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <Card className="p-4 sm:p-8">
                        <form onSubmit={submit}>
                            <fieldset disabled={readOnly}>
                                <FormErrorSummary errors={errors} />

                                <ProcedureFields
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
