import QuoteForm, {
    EmployeeOption,
    EquipmentOption,
    LineItem,
    MarkupDefaults,
    MaterialOption,
    ServiceOption,
} from '@/Components/QuoteForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';

interface QuoteDetail {
    id: number;
    customer_id: number;
    status: string;
    notes: string | null;
    line_items: LineItem[];
}

export default function Edit({
    quote,
    customers,
    materials,
    services,
    equipmentList,
    employees,
    markupDefaults,
}: PageProps<{
    quote: QuoteDetail;
    customers: { id: number; name: string }[];
    materials: MaterialOption[];
    services: ServiceOption[];
    equipmentList: EquipmentOption[];
    employees: EmployeeOption[];
    markupDefaults: MarkupDefaults;
}>) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit quote
                </h2>
            }
        >
            <Head title="Edit quote" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl sm:px-6 lg:px-8">
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <QuoteForm
                            customers={customers}
                            materials={materials}
                            services={services}
                            equipmentList={equipmentList}
                            employees={employees}
                            markupDefaults={markupDefaults}
                            submitUrl={route('quotes.update', quote.id)}
                            method="put"
                            initialData={{
                                customer_id: quote.customer_id,
                                status: quote.status,
                                notes: quote.notes ?? '',
                                line_items: quote.line_items,
                            }}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
