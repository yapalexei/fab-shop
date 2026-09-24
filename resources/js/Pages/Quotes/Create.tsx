import QuoteForm, {
    createEmptyLineItem,
    EmployeeOption,
    EquipmentOption,
    MarkupDefaults,
    MaterialOption,
    ServiceOption,
    ToolOption,
} from '@/Components/QuoteForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';

export default function Create({
    customers,
    materials,
    services,
    equipmentList,
    tools,
    employees,
    markupDefaults,
}: PageProps<{
    customers: { id: number; name: string }[];
    materials: MaterialOption[];
    services: ServiceOption[];
    equipmentList: EquipmentOption[];
    tools: ToolOption[];
    employees: EmployeeOption[];
    markupDefaults: MarkupDefaults;
}>) {
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
                            materials={materials}
                            services={services}
                            equipmentList={equipmentList}
                            tools={tools}
                            employees={employees}
                            markupDefaults={markupDefaults}
                            submitUrl={route('quotes.store')}
                            method="post"
                            initialData={{
                                customer_id: '',
                                notes: '',
                                line_items: [createEmptyLineItem(markupDefaults)],
                            }}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
