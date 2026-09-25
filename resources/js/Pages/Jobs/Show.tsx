import JobMaterialTracking, {
    MaterialCostSummary,
    MaterialOption,
    MaterialUsage,
} from '@/Components/JobMaterialTracking';
import JobPhotos, { JobPhoto } from '@/Components/JobPhotos';
import JobQualificationRecords, {
    QualificationOption,
    QualificationRecord,
} from '@/Components/JobQualificationRecords';
import JobStatusActions, {
    JobTransition,
} from '@/Components/JobStatusActions';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useShopDateTime } from '@/lib/datetime';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';

interface JobLineItem {
    id: number;
    description: string;
    quantity: number;
    unit_cost: number;
    total: number;
}

interface HistoryEntry {
    id: number;
    description: string;
    causer: string | null;
    created_at: string;
}

interface JobDetail {
    id: number;
    job_number: string;
    status: string;
    status_label: string;
    available_transitions: JobTransition[];
    total: number;
    customer: { name: string; email: string | null };
    line_items: JobLineItem[];
    history: HistoryEntry[];
    photos: JobPhoto[];
}

interface MaterialTracking {
    usages: MaterialUsage[];
    cost_summary: MaterialCostSummary;
    materials: MaterialOption[];
}

interface QualificationRecords {
    records: QualificationRecord[];
    procedures: QualificationOption[];
    members: QualificationOption[];
}

const currency = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

export default function Show({
    job,
    materialTracking,
    qualificationRecords,
}: PageProps<{
    job: JobDetail;
    materialTracking: MaterialTracking | null;
    qualificationRecords: QualificationRecords | null;
}>) {
    const formatDateTime = useShopDateTime();

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Job {job.job_number}
                </h2>
            }
        >
            <Head title={`Job ${job.job_number}`} />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow sm:rounded-lg">
                        <dl className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            <div>
                                <dt className="text-sm text-gray-500">
                                    Customer
                                </dt>
                                <dd className="text-sm font-medium text-gray-900">
                                    {job.customer.name}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-gray-500">
                                    Status
                                </dt>
                                <dd className="text-sm font-medium text-gray-900">
                                    {job.status_label}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-gray-500">
                                    Total
                                </dt>
                                <dd className="text-sm font-medium text-gray-900">
                                    {currency.format(job.total)}
                                </dd>
                            </div>
                        </dl>

                        <div className="mt-4 border-t border-gray-100 pt-4">
                            <JobStatusActions
                                job={job}
                                transitions={job.available_transitions}
                            />
                        </div>
                    </div>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Description
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Qty
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Unit cost
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 bg-white">
                                {job.line_items.map((item) => (
                                    <tr key={item.id}>
                                        <td className="px-6 py-4 text-sm text-gray-900">
                                            {item.description}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-500">
                                            {item.quantity}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-500">
                                            {currency.format(item.unit_cost)}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-500">
                                            {currency.format(item.total)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <JobPhotos jobId={job.id} photos={job.photos} />

                    {materialTracking && (
                        <JobMaterialTracking
                            jobId={job.id}
                            usages={materialTracking.usages}
                            costSummary={materialTracking.cost_summary}
                            materials={materialTracking.materials}
                        />
                    )}

                    {qualificationRecords && (
                        <JobQualificationRecords
                            jobId={job.id}
                            records={qualificationRecords.records}
                            procedures={qualificationRecords.procedures}
                            members={qualificationRecords.members}
                        />
                    )}

                    <div className="bg-white p-6 shadow sm:rounded-lg">
                        <h3 className="mb-4 text-sm font-semibold uppercase tracking-wider text-gray-500">
                            History
                        </h3>
                        {job.history.length === 0 ? (
                            <p className="text-sm text-gray-500">
                                No status changes yet.
                            </p>
                        ) : (
                            <ul className="space-y-3">
                                {job.history.map((entry) => (
                                    <li
                                        key={entry.id}
                                        className="flex items-start justify-between text-sm"
                                    >
                                        <div>
                                            <p className="text-gray-900">
                                                {entry.description}
                                            </p>
                                            <p className="text-xs text-gray-500">
                                                {entry.causer ?? 'System'}
                                            </p>
                                        </div>
                                        <span className="whitespace-nowrap text-xs text-gray-400">
                                            {formatDateTime(entry.created_at)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
