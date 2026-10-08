import QuoteForm, {
    EmployeeOption,
    EquipmentOption,
    LineItem,
    MarkupDefaults,
    MaterialOption,
    ServiceOption,
    ToolOption,
} from '@/Components/QuoteForm';
import { AiStatus } from '@/Components/AiQuoteDraftPanel';
import { Attachment } from '@/Components/Attachments';
import Breadcrumbs from '@/Components/Breadcrumbs';
import QuoteHistoryPanel, { QuoteHistory } from '@/Components/QuoteHistoryPanel';
import QuoteWorkflowPanel from '@/Components/QuoteWorkflowPanel';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import AiReviewBanner from '@/Components/AiReviewBanner';
import { Head } from '@inertiajs/react';
import { Card } from '@/Components/ui/card';

interface QuoteDetail {
    id: number;
    customer_id: number;
    status: string;
    is_archived: boolean;
    /** The AI connection wrote this quote and no one has reviewed it yet. */
    ai_review_pending: boolean;
    /** The same, for the customer's name, address or email. */
    customer_ai_review_pending: boolean;
    job_id: number | null;
    customer_email: string | null;
    emailed_at: string | null;
    email_failed_at: string | null;
    customer_locked: boolean;
    emailing_enabled: boolean;
    send_skipped_reason: string | null;
    notes: string | null;
    due_date: string | null;
    line_items: LineItem[];
    attachments: Attachment[];
}

export default function Edit({
    quote,
    customers,
    materials,
    services,
    equipmentList,
    tools,
    employees,
    markupDefaults,
    ai,
    history,
}: PageProps<{
    quote: QuoteDetail;
    customers: { id: number; name: string }[];
    materials: MaterialOption[];
    services: ServiceOption[];
    equipmentList: EquipmentOption[];
    tools: ToolOption[];
    employees: EmployeeOption[];
    markupDefaults: MarkupDefaults;
    ai: AiStatus;
    history: QuoteHistory;
}>) {
    const can = useCan();

    return (
        <AuthenticatedLayout
            header={
                <Breadcrumbs
                    items={[
                        { label: 'Quotes', href: route('quotes.index') },
                        { label: 'Edit quote' },
                    ]}
                />
            }
        >
            <Head title="Edit quote" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl sm:px-6 lg:px-8">
                    <Card className="p-4 sm:p-8">
                        {quote.ai_review_pending && can('quotes.update') && (
                            <AiReviewBanner
                                confirmUrl={route('quotes.ai-review', quote.id)}
                            >
                                <strong>Your AI connection wrote or changed this quote.</strong>{' '}
                                Read the notes and every line item below
                                before it goes to the customer - they print on
                                the quote. It can&apos;t be sent until you mark
                                it as reviewed.
                            </AiReviewBanner>
                        )}
                        {quote.customer_ai_review_pending && (
                            <AiReviewBanner
                                reviewHref={route('customers.edit', quote.customer_id)}
                                reviewLabel="Review the customer"
                            >
                                <strong>
                                    This customer&apos;s details were set by
                                    your AI connection.
                                </strong>{' '}
                                Check their name, address and email address
                                before sending them a quote - the quote is
                                emailed to that address.
                            </AiReviewBanner>
                        )}
                        <QuoteWorkflowPanel
                            quoteId={quote.id}
                            status={quote.status}
                            isArchived={quote.is_archived}
                            jobId={quote.job_id}
                            customerEmail={quote.customer_email}
                            emailedAt={quote.emailed_at}
                            emailFailedAt={quote.email_failed_at}
                            emailingEnabled={quote.emailing_enabled}
                            sendSkippedReason={quote.send_skipped_reason}
                        />

                        <QuoteForm
                            customers={customers}
                            materials={materials}
                            services={services}
                            equipmentList={equipmentList}
                            tools={tools}
                            employees={employees}
                            markupDefaults={markupDefaults}
                            ai={ai}
                            submitUrl={route('quotes.update', quote.id)}
                            method="put"
                            quoteId={quote.id}
                            customerLocked={quote.customer_locked}
                            attachments={quote.attachments}
                            readOnly={!can('quotes.update')}
                            initialData={{
                                customer_id: quote.customer_id,
                                notes: quote.notes ?? '',
                                due_date: quote.due_date ?? '',
                                line_items: quote.line_items,
                            }}
                        />

                        <QuoteHistoryPanel history={history} />
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
