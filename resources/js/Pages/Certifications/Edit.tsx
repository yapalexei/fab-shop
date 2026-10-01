import CertificationFields, {
    CertificationFormData,
    MemberOption,
    ProcedureOption,
} from '@/Components/CertificationFields';
import Breadcrumbs from '@/Components/Breadcrumbs';
import FormErrorSummary from '@/Components/FormErrorSummary';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface CertificationDetail {
    id: number;
    user_id: number;
    procedure_id: number | null;
    cert_number: string | null;
    issuing_body: string | null;
    issued_at: string | null;
    expires_at: string | null;
    notes: string | null;
}

export default function Edit({
    certification,
    members,
    procedures,
}: PageProps<{
    certification: CertificationDetail;
    members: MemberOption[];
    procedures: ProcedureOption[];
}>) {
    const can = useCan();
    const readOnly = !can('certifications.update');

    const { data, setData, put, processing, errors } =
        useForm<CertificationFormData>({
            user_id: certification.user_id.toString(),
            procedure_id: certification.procedure_id?.toString() ?? '',
            cert_number: certification.cert_number ?? '',
            issuing_body: certification.issuing_body ?? '',
            issued_at: certification.issued_at ?? '',
            expires_at: certification.expires_at ?? '',
            notes: certification.notes ?? '',
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('certifications.update', certification.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <Breadcrumbs
                    items={[
                        { label: 'Certifications', href: route('certifications.index') },
                        { label: 'Edit certification' },
                    ]}
                />
            }
        >
            <Head title="Edit certification" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <form onSubmit={submit}>
                            <fieldset disabled={readOnly}>
                                <FormErrorSummary errors={errors} />

                                <CertificationFields
                                    data={data}
                                    setData={setData}
                                    errors={errors}
                                    members={members}
                                    procedures={procedures}
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
