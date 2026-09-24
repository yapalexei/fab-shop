import QuoteForm, { emptyLineItem } from '@/Components/QuoteForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';

export default function Create({
    customers,
}: PageProps<{ customers: { id: number; name: string }[] }>) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    New quote
                </h2>
            }
        >
            <Head title="New quote" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl sm:px-6 lg:px-8">
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <QuoteForm
                            customers={customers}
                            submitUrl={route('quotes.store')}
                            method="post"
                            initialData={{
                                customer_id: '',
                                status: 'draft',
                                notes: '',
                                line_items: [{ ...emptyLineItem }],
                            }}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
