import CertificationFields, {
    CertificationFormData,
    MemberOption,
    ProcedureOption,
} from '@/Components/CertificationFields';
import Breadcrumbs from '@/Components/Breadcrumbs';
import FormErrorSummary from '@/Components/FormErrorSummary';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Create({
    members,
    procedures,
}: PageProps<{ members: MemberOption[]; procedures: ProcedureOption[] }>) {
    const { data, setData, post, processing, errors } =
        useForm<CertificationFormData>({
            user_id: '',
            procedure_id: '',
            cert_number: '',
            issuing_body: '',
            issued_at: '',
            expires_at: '',
            notes: '',
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('certifications.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <Breadcrumbs
                    items={[
                        { label: 'Certifications', href: route('certifications.index') },
                        { label: 'Add certification' },
                    ]}
                />
            }
        >
            <Head title="Add certification" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <form onSubmit={submit}>
                            <FormErrorSummary errors={errors} />

                            <CertificationFields
                                data={data}
                                setData={setData}
                                errors={errors}
                                members={members}
                                procedures={procedures}
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
