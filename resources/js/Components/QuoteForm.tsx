import FormErrorSummary from '@/Components/FormErrorSummary';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import QuoteLineItemCard, {
    lineTotal,
} from '@/Components/QuoteLineItemCard';
import SearchableSelect from '@/Components/SearchableSelect';
import { clearDraft, readDraft, writeDraft } from '@/lib/draft';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';
import { Alert } from '@/Components/ui/alert';
import { Attachment, QuoteAttachments } from '@/Components/Attachments';
import AiQuoteDraftPanel, { AiStatus } from '@/Components/AiQuoteDraftPanel';
import { AiDraftResult, lineItemFromSuggestion } from '@/lib/aiQuoteDraft';

export interface LineItem {
    /** Set on a saved line; lets the server keep its markup for someone who can't change it. */
    id?: number;
    type: 'material' | 'labor' | 'equipment' | 'tooling';
    description: string;
    quantity: number | string;
    unit_cost: number | string;
    markup_percent: number | string;
    override_total: number | string | null;
    material_id: number | string | null;
    service_id: number | string | null;
    business_partner_id: number | string | null;
    /** The supplier size the price was taken at (see material supplier prices). */
    material_dimension: string | null;
    user_id: number | string | null;
    equipment_id: number | string | null;
    tool_id: number | string | null;
    /**
     * Client-only: set on a line the AI suggested that the owner should
     * double-check. Not a form field - the server ignores it - and cleared
     * once they edit the line.
     */
    ai_note?: string | null;
}

export interface MaterialTypeRef {
    id: number;
    name: string;
}

export interface MaterialOption {
    id: number;
    name: string;
    material_type: MaterialTypeRef | null;
    partners: {
        business_partner_id: number;
        name: string;
        dimension: string | null;
        price: number;
    }[];
}

export interface ServiceOption {
    id: number;
    name: string;
    partners: { business_partner_id: number; name: string; price: number }[];
}

export interface EquipmentOption {
    id: number;
    name: string;
    billing_method: 'hourly' | 'flat';
    rate: number;
}

export interface ToolOption {
    id: number;
    name: string;
    /** What it's rated to cut, e.g. "Carbon steel, Stainless steel". */
    cuts: string;
    cut_type_ids: number[];
    cost: number;
    unit: string;
}

export interface EmployeeOption {
    user_id: number;
    name: string;
    hourly_rate: number | null;
}

export type MarkupDefaults = Record<LineItem['type'], number>;

export interface QuoteFormData {
    customer_id: number | string;
    notes: string;
    due_date: string;
    line_items: LineItem[];
}

const currency = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

export function createEmptyLineItem(markupDefaults: MarkupDefaults): LineItem {
    return {
        type: 'material',
        description: '',
        quantity: 1,
        unit_cost: 0,
        markup_percent: markupDefaults.material,
        override_total: null,
        material_id: null,
        service_id: null,
        business_partner_id: null,
        material_dimension: null,
        user_id: null,
        equipment_id: null,
        tool_id: null,
    };
}

/**
 * A saved draft, if there is one worth restoring: shaped like a quote form
 * (and filled out to today's fields, in case the form has grown since it was
 * saved) and different from a blank start.
 */
function restoreDraft(
    key: string,
    initialData: QuoteFormData,
    markupDefaults: MarkupDefaults,
): { data: QuoteFormData; savedAt: number } | null {
    const draft = readDraft(key);
    const raw = draft?.data as Partial<QuoteFormData> | undefined;

    if (!draft || !raw || !Array.isArray(raw.line_items)) return null;

    const data: QuoteFormData = {
        customer_id: raw.customer_id ?? initialData.customer_id,
        notes: typeof raw.notes === 'string' ? raw.notes : initialData.notes,
        due_date:
            typeof raw.due_date === 'string'
                ? raw.due_date
                : initialData.due_date,
        line_items: raw.line_items.map((item) => ({
            ...createEmptyLineItem(markupDefaults),
            ...item,
        })),
    };

    return JSON.stringify(data) === JSON.stringify(initialData)
        ? null
        : { data, savedAt: draft.savedAt };
}

export default function QuoteForm({
    customers,
    materials,
    services,
    equipmentList,
    tools,
    employees,
    markupDefaults,
    initialData,
    submitUrl,
    method,
    readOnly = false,
    draftKey,
    quoteId,
    attachments,
    customerLocked = false,
    ai,
}: {
    customers: { id: number; name: string }[];
    materials: MaterialOption[];
    services: ServiceOption[];
    equipmentList: EquipmentOption[];
    tools: ToolOption[];
    employees: EmployeeOption[];
    markupDefaults: MarkupDefaults;
    initialData: QuoteFormData;
    submitUrl: string;
    method: 'post' | 'put';
    /** View-only (no update permission): every field disabled, no save. */
    readOnly?: boolean;
    /**
     * Saves the unsaved form to localStorage under this key (and restores it
     * on the next visit) - for the new-quote form, so leaving midway to
     * create a missing material doesn't lose the work.
     */
    draftKey?: string;
    /** Set when editing: attachments then upload/delete immediately. */
    quoteId?: number;
    attachments?: Attachment[];
    /** The quote has been emailed, so the customer can't be changed. */
    customerLocked?: boolean;
    /** Whether "Draft from a customer email" is on offer; omitted = hidden. */
    ai?: AiStatus;
}) {
    // Read once, before the form exists, so it starts out as the draft.
    const [restored] = useState(() =>
        draftKey ? restoreDraft(draftKey, initialData, markupDefaults) : null,
    );
    const [showRestored, setShowRestored] = useState(restored !== null);
    const { data, setData, post, put, processing, errors, transform } =
        useForm<QuoteFormData>(restored?.data ?? initialData);

    // New quotes: files picked before the quote exists. Kept out of `data` so
    // drafts (JSON in localStorage) never have to hold File objects.
    const [stagedFiles, setStagedFiles] = useState<File[]>([]);
    transform((formData) =>
        stagedFiles.length > 0
            ? { ...formData, attachments: stagedFiles }
            : formData,
    );

    // Keep the draft current (debounced); a form back at its starting values
    // isn't a draft. `submitted` stops a late write recreating it after a save.
    const submitted = useRef(false);
    useEffect(() => {
        if (!draftKey) return;

        const timer = setTimeout(() => {
            if (submitted.current) return;

            if (JSON.stringify(data) === JSON.stringify(initialData)) {
                clearDraft(draftKey);
            } else {
                writeDraft(draftKey, data);
            }
        }, 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data, draftKey]);

    const discardDraft = () => {
        if (draftKey) clearDraft(draftKey);
        setData(initialData);
        setShowRestored(false);
    };

    // Laravel validates array fields as e.g. "line_items.0.description",
    // which falls outside useForm's keyof-QuoteFormData error typing.
    const errorMap = errors as Record<string, string>;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        (method === 'post' ? post : put)(submitUrl, {
            forceFormData: stagedFiles.length > 0,
            onSuccess: () => {
                submitted.current = true;
                if (draftKey) clearDraft(draftKey);
            },
        });
    };

    const patchLineItem = (index: number, patch: Partial<LineItem>) => {
        const lineItems = [...data.line_items];
        lineItems[index] = {
            ...lineItems[index],
            ...patch,
            // Touching a suggested line means the owner has looked at it.
            ...(lineItems[index].ai_note ? { ai_note: null } : {}),
        };
        setData('line_items', lineItems);
    };

    const addLineItem = () => {
        setData('line_items', [
            ...data.line_items,
            createEmptyLineItem(markupDefaults),
        ]);
    };

    // Suggested lines are appended, never replacing what's there, and a
    // blank placeholder line (the form's starting row) is swapped out.
    const addAiLines = (result: AiDraftResult): number => {
        const suggested = result.lines.map((line) =>
            lineItemFromSuggestion(
                line,
                { materials, services, equipmentList, tools },
                markupDefaults,
            ),
        );
        if (suggested.length === 0) return 0;

        const existing = data.line_items.filter(
            (item) => item.description.trim() !== '',
        );
        setData('line_items', [...existing, ...suggested]);

        return suggested.length;
    };

    const removeLineItem = (index: number) => {
        setData(
            'line_items',
            data.line_items.filter((_, i) => i !== index),
        );
    };

    // The material types this quote works with, from its catalog-backed
    // material lines - what the tooling check compares each tool against.
    const quoteMaterialTypes = data.line_items
        .filter((item) => item.type === 'material' && item.material_id)
        .map(
            (item) =>
                materials.find((m) => m.id === Number(item.material_id))
                    ?.material_type,
        )
        .filter((type): type is MaterialTypeRef => !!type)
        .filter(
            (type, i, all) => all.findIndex((t) => t.id === type.id) === i,
        );

    const cost = data.line_items.reduce(
        (sum, item) =>
            sum + (Number(item.quantity) || 0) * (Number(item.unit_cost) || 0),
        0,
    );
    const total = data.line_items.reduce(
        (sum, item) => sum + lineTotal(item),
        0,
    );
    const margin = total - cost;
    const marginPercent = total > 0 ? (margin / total) * 100 : 0;

    return (
        <form onSubmit={submit}>
            {showRestored && restored && (
                <Alert
                    role="status"
                    variant="warning" className="mb-4 flex flex-wrap items-center justify-between gap-2 p-3 text-sm"
                >
                    <span>
                        Restored your unsaved draft from{' '}
                        {new Date(restored.savedAt).toLocaleString()}.
                    </span>
                    <button
                        type="button"
                        onClick={discardDraft}
                        className="font-medium underline hover:text-amber-700"
                    >
                        Start over
                    </button>
                </Alert>
            )}
            <fieldset disabled={readOnly}>
                <FormErrorSummary errors={errorMap} />

                <div>
                    <InputLabel htmlFor="customer_id" value="Customer" />
                    <fieldset disabled={customerLocked}>
                    <SearchableSelect
                        id="customer_id"
                        className="block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                        wrapperClassName="mt-1"
                        value={String(data.customer_id ?? '')}
                        onChange={(value) => setData('customer_id', value)}
                        emptyLabel="Select a customer"
                        required
                        options={customers.map((customer) => ({
                            value: String(customer.id),
                            label: customer.name,
                        }))}
                        emptyState={
                            <>
                                No customers yet. <Link href={route('customers.create')} className="font-medium underline">Create a customer</Link> before
                                starting a quote.
                            </>
                        }
                    />
                    </fieldset>
                    {customerLocked && (
                        <p className="mt-1 text-xs text-muted-foreground">
                            This quote has been emailed, so the customer can't
                            be changed. If the email fails to deliver, you'll be
                            able to correct it.
                        </p>
                    )}
                    <InputError message={errors.customer_id} className="mt-2" />
                </div>

                {ai && !readOnly && (
                    <div className="mt-6">
                        <AiQuoteDraftPanel ai={ai} onDraft={addAiLines} />
                    </div>
                )}

                <div className={ai && !readOnly ? '' : 'mt-6'}>
                    <InputLabel value="Line items" />

                    <div className="mt-2 space-y-3">
                        {data.line_items.map((item, index) => (
                            <QuoteLineItemCard
                                key={index}
                                item={item}
                                index={index}
                                materials={materials}
                                services={services}
                                equipmentList={equipmentList}
                                tools={tools}
                                quoteMaterialTypes={quoteMaterialTypes}
                                employees={employees}
                                markupDefaults={markupDefaults}
                                onChange={patchLineItem}
                                onRemove={removeLineItem}
                                getError={(field: keyof LineItem) =>
                                    errorMap[`line_items.${index}.${field}`]
                                }
                                readOnly={readOnly}
                            />
                        ))}
                    </div>

                    <InputError message={errors.line_items} className="mt-2" />

                    <button
                        type="button"
                        onClick={addLineItem}
                        className="mt-3 flex w-full items-center justify-center rounded-lg border-2 border-dashed px-4 py-4 text-sm font-medium text-muted-foreground transition-colors hover:border-primary hover:bg-accent hover:text-foreground focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        + Add line item
                    </button>
                </div>
                <hr className="mt-4" />
                <div className="mt-6 flex justify-end">
                    <dl className="w-64 space-y-1 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-gray-500">Cost</dt>
                            <dd className="text-gray-900">
                                {currency.format(cost)}
                            </dd>
                        </div>
                        <div className="flex justify-between font-medium">
                            <dt className="text-gray-900">Total</dt>
                            <dd className="text-gray-900">
                                {currency.format(total)}
                            </dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-gray-500">Margin</dt>
                            <dd className="text-gray-900">
                                {currency.format(margin)} (
                                {marginPercent.toFixed(1)}%)
                            </dd>
                        </div>
                    </dl>
                </div>
                <hr className="mt-7" />
                <div className="mt-4">
                    <InputLabel htmlFor="due_date" value="Due date (optional)" />
                    <input
                        id="due_date"
                        type="date"
                        className="mt-1 block rounded-md border bg-background px-3 py-2 text-sm"
                        value={data.due_date}
                        onChange={(e) => setData('due_date', e.target.value)}
                    />
                    <p className="mt-1 text-xs text-muted-foreground">
                        When the customer needs it by - carried over to the job
                        if this quote is converted.
                    </p>
                    <InputError message={errors.due_date} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="notes" value="Notes" />
                    <textarea
                        id="notes"
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                        rows={3}
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                    />
                    <InputError message={errors.notes} className="mt-2" />
                </div>

                <QuoteAttachments
                    quoteId={quoteId}
                    attachments={attachments}
                    staged={stagedFiles}
                    onStage={setStagedFiles}
                    error={
                        errorMap.attachments ??
                        Object.entries(errorMap).find(([k]) =>
                            k.startsWith('attachments.'),
                        )?.[1]
                    }
                />

            </fieldset>

            {!readOnly && (
                <div className="mt-6 flex justify-end">
                    <PrimaryButton disabled={processing}>
                        Save quote
                    </PrimaryButton>
                </div>
            )}
        </form>
    );
}
