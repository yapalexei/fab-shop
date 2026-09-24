import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';

interface JobLineItem {
    id: number;
    description: string;
    quantity: number;
    unit_cost: number;
    total: number;
}

interface JobDetail {
    id: number;
    job_number: string;
    status: string;
    total: number;
    customer: { name: string; email: string | null };
    line_items: JobLineItem[];
}

const currency = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

export default function Show({ job }: PageProps<{ job: JobDetail }>) {
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
                                <dd className="text-sm font-medium capitalize text-gray-900">
                                    {job.status.replace('_', ' ')}
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
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
