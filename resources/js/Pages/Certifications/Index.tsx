import PageHelp from '@/Components/PageHelp';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';

interface CertificationRow {
    id: number;
    user: string;
    procedure: string | null;
    cert_number: string | null;
    issuing_body: string | null;
    issued_at: string | null;
    expires_at: string | null;
    is_expired: boolean;
}

export default function Index({
    certifications,
}: PageProps<{ certifications: CertificationRow[] }>) {
    const can = useCan();
    const destroy = (certification: CertificationRow) => {
        if (confirm(`Delete this certification for ${certification.user}?`)) {
            router.delete(route('certifications.destroy', certification.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Certifications
                    </h2>
                    {certifications.length > 0 && (
                        <PageHelp>
                            <p>
                                Your team's qualifications - cert number,
                                issuing body, issued/expiry dates - optionally
                                tied to one of the shop's Procedures.
                            </p>
                            <p>
                                Checked automatically whenever someone logs a
                                qualification record on a job, to flag work
                                performed without a valid certification.
                            </p>
                        </PageHelp>
                    )}
                </div>
            }
        >
            <Head title="Certifications" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="flex justify-end gap-3">
                        {can('certifications.create') && (
                            <Link href={route('certifications.create')}>
                                <PrimaryButton>Add certification</PrimaryButton>
                            </Link>
                        )}
                    </div>

                    <Card className="overflow-hidden">
                        {certifications.length === 0 ? (
                            <div className="p-6">
                                <PageHelp empty>
                                    <p>
                                        No certifications yet. This is your
                                        team's qualifications - cert number,
                                        issuing body, issued/expiry dates -
                                        optionally tied to one of the shop's
                                        Procedures.
                                    </p>
                                    <p>
                                        Checked automatically whenever someone
                                        logs a qualification record on a job, to
                                        flag work performed without a valid
                                        certification.
                                    </p>
                                </PageHelp>
                            </div>
                        ) : (
                            <table className="min-w-full divide-y">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Team member
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Procedure
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Cert #
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Issuing body
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Expires
                                        </th>
                                        <th className="px-6 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y bg-white">
                                    {certifications.map((certification) => (
                                        <tr key={certification.id}>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                                {certification.user}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {certification.procedure ?? '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {certification.cert_number ??
                                                    '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {certification.issuing_body ??
                                                    '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm">
                                                {certification.expires_at ? (
                                                    certification.is_expired ? (
                                                        <Badge variant="danger">
                                                            {certification.expires_at} (expired)
                                                        </Badge>
                                                    ) : (
                                                        <span className="text-gray-500">
                                                            {certification.expires_at}
                                                        </span>
                                                    )
                                                ) : (
                                                    <span className="text-gray-400">
                                                        Never
                                                    </span>
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                                <Link
                                                    href={route(
                                                        'certifications.edit',
                                                        certification.id,
                                                    )}
                                                    className="text-indigo-600 hover:underline"
                                                >
                                                    {can(
                                                        'certifications.update',
                                                    )
                                                        ? 'Edit'
                                                        : 'View'}
                                                </Link>
                                                {can(
                                                    'certifications.delete',
                                                ) && (
                                                    <button
                                                        onClick={() =>
                                                            destroy(
                                                                certification,
                                                            )
                                                        }
                                                        className="ms-4 text-red-600 hover:underline"
                                                    >
                                                        Delete
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
