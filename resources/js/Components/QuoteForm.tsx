import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface LineItem {
    type: 'material' | 'labor';
    description: string;
    quantity: number | string;
    unit_cost: number | string;
    markup_percent: number | string;
    override_total: number | string | null;
}

export interface QuoteFormData {
    customer_id: number | string;
    status: string;
    notes: string;
    line_items: LineItem[];
}

const currency = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

const emptyLineItem: LineItem = {
    type: 'material',
    description: '',
    quantity: 1,
    unit_cost: 0,
    markup_percent: 0,
    override_total: null,
};

function lineTotal(item: LineItem): number {
    const quantity = Number(item.quantity) || 0;
    const unitCost = Number(item.unit_cost) || 0;
    const markup = Number(item.markup_percent) || 0;
    const computed = quantity * unitCost * (1 + markup / 100);

    return item.override_total !== null && item.override_total !== ''
        ? Number(item.override_total)
        : computed;
}

export default function QuoteForm({
    customers,
    initialData,
    submitUrl,
    method,
}: {
    customers: { id: number; name: string }[];
    initialData: QuoteFormData;
    submitUrl: string;
    method: 'post' | 'put';
}) {
    const { data, setData, post, put, processing, errors } =
        useForm<QuoteFormData>(initialData);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        (method === 'post' ? post : put)(submitUrl);
    };

    const updateLineItem = (
        index: number,
        field: keyof LineItem,
        value: string,
    ) => {
        const lineItems = [...data.line_items];
        lineItems[index] = { ...lineItems[index], [field]: value };
        setData('line_items', lineItems);
    };

    const addLineItem = () => {
        setData('line_items', [...data.line_items, { ...emptyLineItem }]);
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

            <div className="mt-4">
                <InputLabel htmlFor="status" value="Status" />
                <select
                    id="status"
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    value={data.status}
                    onChange={(e) => setData('status', e.target.value)}
                >
                    <option value="draft">Draft</option>
                    <option value="sent">Sent</option>
                    <option value="won">Won</option>
                    <option value="lost">Lost</option>
                </select>
                <InputError message={errors.status} className="mt-2" />
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
                                    Description
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
                                <tr key={index}>
                                    <td className="px-3 py-2">
                                        <select
                                            className="block w-28 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            value={item.type}
                                            onChange={(e) =>
                                                updateLineItem(
                                                    index,
                                                    'type',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <option value="material">
                                                Material
                                            </option>
                                            <option value="labor">
                                                Labor
                                            </option>
                                        </select>
                                    </td>
                                    <td className="px-3 py-2">
                                        <TextInput
                                            className="block w-48"
                                            value={item.description}
                                            onChange={(e) =>
                                                updateLineItem(
                                                    index,
                                                    'description',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <TextInput
                                            type="number"
                                            step="0.01"
                                            className="block w-20"
                                            value={item.quantity}
                                            onChange={(e) =>
                                                updateLineItem(
                                                    index,
                                                    'quantity',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <TextInput
                                            type="number"
                                            step="0.01"
                                            className="block w-24"
                                            value={item.unit_cost}
                                            onChange={(e) =>
                                                updateLineItem(
                                                    index,
                                                    'unit_cost',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <TextInput
                                            type="number"
                                            step="0.01"
                                            className="block w-20"
                                            value={item.markup_percent}
                                            onChange={(e) =>
                                                updateLineItem(
                                                    index,
                                                    'markup_percent',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <TextInput
                                            type="number"
                                            step="0.01"
                                            className="block w-24"
                                            placeholder="auto"
                                            value={item.override_total ?? ''}
                                            onChange={(e) =>
                                                updateLineItem(
                                                    index,
                                                    'override_total',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </td>
                                    <td className="whitespace-nowrap px-3 py-2 text-right text-sm text-gray-900">
                                        {currency.format(lineTotal(item))}
                                    </td>
                                    <td className="px-3 py-2">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                removeLineItem(index)
                                            }
                                            className="text-sm text-red-600 hover:text-red-900"
                                        >
                                            Remove
                                        </button>
                                    </td>
                                </tr>
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

            <div className="mt-6 flex justify-end">
                <PrimaryButton disabled={processing}>
                    Save quote
                </PrimaryButton>
            </div>
        </form>
    );
}

export { emptyLineItem };
