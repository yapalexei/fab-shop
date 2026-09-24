import InputError from '@/Components/InputError';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';

export interface CapabilityRow {
    itemId: number | string;
    price: number | string;
}

export default function CapabilityPriceTable({
    title,
    itemLabel,
    addLabel,
    options,
    rows,
    onChange,
    getError,
}: {
    title: string;
    itemLabel: string;
    addLabel: string;
    options: { id: number; name: string }[];
    rows: CapabilityRow[];
    onChange: (rows: CapabilityRow[]) => void;
    getError: (index: number, field: 'itemId' | 'price') => string | undefined;
}) {
    const update = (index: number, field: keyof CapabilityRow, value: string) => {
        const next = [...rows];
        next[index] = { ...next[index], [field]: value };
        onChange(next);
    };

    const addRow = () => {
        onChange([...rows, { itemId: '', price: '' }]);
    };

    const removeRow = (index: number) => {
        onChange(rows.filter((_, i) => i !== index));
    };

    return (
        <div className="mt-6">
            <h3 className="text-sm font-medium text-gray-700">{title}</h3>

            {rows.length > 0 && (
                <div className="mt-2 overflow-x-auto rounded-md border border-gray-200">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                    {itemLabel}
                                </th>
                                <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                    Price
                                </th>
                                <th />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 bg-white">
                            {rows.map((row, index) => (
                                <tr key={index}>
                                    <td className="px-3 py-2">
                                        <select
                                            className="block w-56 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            value={row.itemId}
                                            onChange={(e) =>
                                                update(index, 'itemId', e.target.value)
                                            }
                                            required
                                        >
                                            <option value="">Select...</option>
                                            {options.map((option) => (
                                                <option key={option.id} value={option.id}>
                                                    {option.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={getError(index, 'itemId')}
                                            className="mt-1"
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <TextInput
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            className="block w-28"
                                            value={row.price}
                                            onChange={(e) =>
                                                update(index, 'price', e.target.value)
                                            }
                                            required
                                        />
                                        <InputError
                                            message={getError(index, 'price')}
                                            className="mt-1"
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <button
                                            type="button"
                                            onClick={() => removeRow(index)}
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
            )}

            <SecondaryButton
                type="button"
                className="mt-3"
                onClick={addRow}
            >
                {addLabel}
            </SecondaryButton>
        </div>
    );
}
