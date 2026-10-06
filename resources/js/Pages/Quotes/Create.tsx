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
import Breadcrumbs from '@/Components/Breadcrumbs';
import { Head, usePage } from '@inertiajs/react';
import { Card } from '@/Components/ui/card';

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
    const { auth, shop } = usePage<PageProps>().props;

    return (
        <AuthenticatedLayout
            header={
                <Breadcrumbs
                    items={[
                        { label: 'Quotes', href: route('quotes.index') },
                        { label: 'New quote' },
                    ]}
                />
            }
        >
            <Head title="New quote" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl sm:px-6 lg:px-8">
                    <Card className="p-4 sm:p-8">
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
                            draftKey={`quote-draft:${shop?.id}:${auth.user.id}`}
                            initialData={{
                                customer_id: '',
                                notes: '',
                                due_date: '',
                                line_items: [createEmptyLineItem(markupDefaults)],
                            }}
                        />
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
