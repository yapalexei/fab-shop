import FormErrorSummary from '@/Components/FormErrorSummary';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import QuoteLineItemRow, {
    lineTotal,
} from '@/Components/QuoteLineItemRow';
import SecondaryButton from '@/Components/SecondaryButton';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface LineItem {
    type: 'material' | 'labor' | 'equipment' | 'tooling';
    description: string;
    quantity: number | string;
    unit_cost: number | string;
    markup_percent: number | string;
    override_total: number | string | null;
    material_id: number | string | null;
    service_id: number | string | null;
    business_partner_id: number | string | null;
    user_id: number | string | null;
    equipment_id: number | string | null;
    tool_id: number | string | null;
}

export interface MaterialOption {
    id: number;
    name: string;
    partners: { business_partner_id: number; name: string; price: number }[];
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
        user_id: null,
        equipment_id: null,
        tool_id: null,
    };
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
}) {
    const { data, setData, post, put, processing, errors } =
        useForm<QuoteFormData>(initialData);

    // Laravel validates array fields as e.g. "line_items.0.description",
    // which falls outside useForm's keyof-QuoteFormData error typing.
    const errorMap = errors as Record<string, string>;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        (method === 'post' ? post : put)(submitUrl);
    };

    const patchLineItem = (index: number, patch: Partial<LineItem>) => {
        const lineItems = [...data.line_items];
        lineItems[index] = { ...lineItems[index], ...patch };
        setData('line_items', lineItems);
    };

    const addLineItem = () => {
        setData('line_items', [
            ...data.line_items,
            createEmptyLineItem(markupDefaults),
        ]);
    };

    const removeLineItem = (index: number) => {
        setData(
            'line_items',
            data.line_items.filter((_, i) => i !== index),
        );
    };

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
            <fieldset disabled={readOnly}>
                <FormErrorSummary errors={errorMap} />

                <div>
                    <InputLabel htmlFor="customer_id" value="Customer" />
                    <select
                        id="customer_id"
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.customer_id}
                        onChange={(e) => setData('customer_id', e.target.value)}
                        required
                    >
                        <option value="">Select a customer</option>
                        {customers.map((customer) => (
                            <option key={customer.id} value={customer.id}>
                                {customer.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.customer_id} className="mt-2" />
                </div>

                <div className="mt-6">
                    <InputLabel value="Line items" />

                    <div className="mt-2 overflow-x-auto rounded-md border border-gray-200">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                        Type
                                    </th>
                                    <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                        Source / description
                                    </th>
                                    <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                        Qty
                                    </th>
                                    <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                        Unit cost
                                    </th>
                                    <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                        Markup %
                                    </th>
                                    <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                        Override total
                                    </th>
                                    <th className="px-3 py-2 text-right text-xs font-medium uppercase text-gray-500">
                                        Line total
                                    </th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 bg-white">
                                {data.line_items.map((item, index) => (
                                    <QuoteLineItemRow
                                        key={index}
                                        item={item}
                                        index={index}
                                        materials={materials}
                                        services={services}
                                        equipmentList={equipmentList}
                                        tools={tools}
                                        employees={employees}
                                        markupDefaults={markupDefaults}
                                        onChange={patchLineItem}
                                        onRemove={removeLineItem}
                                        getError={(field) =>
                                            errorMap[`line_items.${index}.${field}`]
                                        }
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <InputError message={errors.line_items} className="mt-2" />

                    <SecondaryButton className="mt-3" onClick={addLineItem}>
                        Add line item
                    </SecondaryButton>
                </div>

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

                <div className="mt-4">
                    <InputLabel htmlFor="notes" value="Notes" />
                    <textarea
                        id="notes"
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        rows={3}
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                    />
                    <InputError message={errors.notes} className="mt-2" />
                </div>

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
