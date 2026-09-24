import JobStatusActions, {
    JobTransition,
} from '@/Components/JobStatusActions';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface JobRow {
    id: number;
    job_number: string;
    customer: string;
    status: string;
    status_label: string;
    total: number;
    available_transitions: JobTransition[];
}

interface StatusOption {
    value: string;
    label: string;
}

const currency = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

function JobCard({ job }: { job: JobRow }) {
    return (
        <div className="space-y-2 rounded-md border border-gray-200 bg-white p-3 shadow-sm">
            <div className="flex items-start justify-between">
                <Link
                    href={route('jobs.show', job.id)}
                    className="text-sm font-semibold text-indigo-600 hover:text-indigo-900"
                >
                    {job.job_number}
                </Link>
                <span className="text-sm text-gray-500">
                    {currency.format(job.total)}
                </span>
            </div>
            <p className="text-sm text-gray-700">{job.customer}</p>
            <JobStatusActions
                jobId={job.id}
                transitions={job.available_transitions}
            />
        </div>
    );
}

export default function Index({
    jobs,
    statuses,
}: PageProps<{ jobs: JobRow[]; statuses: StatusOption[] }>) {
    const boardStatuses = statuses.filter((s) => s.value !== 'cancelled');
    const cancelledJobs = jobs.filter((j) => j.status === 'cancelled');

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Jobs
                </h2>
            }
        >
            <Head title="Jobs" />

            <div className="py-12">
                <div className="mx-auto max-w-full space-y-6 px-4 sm:px-6 lg:px-8">
                    {jobs.length === 0 ? (
                        <div className="rounded-lg bg-white p-6 text-gray-500 shadow-sm">
                            No jobs yet - convert a won quote to create one.
                        </div>
                    ) : (
                        <div className="flex gap-4 overflow-x-auto pb-4">
                            {boardStatuses.map((status) => {
                                const columnJobs = jobs.filter(
                                    (job) => job.status === status.value,
                                );

                                return (
                                    <div
                                        key={status.value}
                                        className="w-72 shrink-0"
                                    >
                                        <div className="mb-2 flex items-center justify-between px-1">
                                            <h3 className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                {status.label}
                                            </h3>
                                            <span className="text-xs text-gray-400">
                                                {columnJobs.length}
                                            </span>
                                        </div>
                                        <div className="space-y-3 rounded-lg bg-gray-100 p-2 min-h-[4rem]">
                                            {columnJobs.map((job) => (
                                                <JobCard
                                                    key={job.id}
                                                    job={job}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    {cancelledJobs.length > 0 && (
                        <details className="rounded-lg bg-white p-4 shadow-sm">
                            <summary className="cursor-pointer text-sm font-medium text-gray-600">
                                Cancelled jobs ({cancelledJobs.length})
                            </summary>
                            <div className="mt-3 space-y-2">
                                {cancelledJobs.map((job) => (
                                    <div
                                        key={job.id}
                                        className="flex items-center justify-between text-sm"
                                    >
                                        <Link
                                            href={route('jobs.show', job.id)}
                                            className="text-indigo-600 hover:text-indigo-900"
                                        >
                                            {job.job_number} - {job.customer}
                                        </Link>
                                        <span className="text-gray-500">
                                            {currency.format(job.total)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </details>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
