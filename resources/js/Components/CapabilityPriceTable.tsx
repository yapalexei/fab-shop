import { ReactNode } from 'react';
import InputError from '@/Components/InputError';
import PriceChecked from '@/Components/PriceChecked';
import SearchableSelect from '@/Components/SearchableSelect';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';

export interface CapabilityRow {
    itemId: number | string;
    price: number | string;
    /** Only for tables with `withDimension`: the size this price is for. */
    dimension?: string;
    /** Only for tables with `withSource`: where the price came from. */
    part_number?: string;
    source_url?: string;
    price_checked_at?: string | null;
    /** Confirms the price is still current although it didn't change. */
    mark_checked?: boolean;
}

export default function CapabilityPriceTable({
    title,
    itemLabel,
    addLabel,
    options,
    rows,
    onChange,
    getError,
    emptyState,
    withDimension = false,
    withSource = false,
}: {
    title: string;
    itemLabel: string;
    addLabel: string;
    options: { id: number; name: string }[];
    rows: CapabilityRow[];
    onChange: (rows: CapabilityRow[]) => void;
    getError: (
        index: number,
        field: 'itemId' | 'price' | 'dimension' | 'part_number' | 'source_url',
    ) => string | undefined;
    /**
     * Adds an optional size column, so the same item can be listed more
     * than once at different sizes (4 ft vs 4 in of bar stock).
     */
    withDimension?: boolean;
    /**
     * Adds a part number and product link per row, and shows when the price
     * was last checked, so it can be looked up and refreshed later.
     */
    withSource?: boolean;
    /** Shown under the picker when `options` is empty - say what to create and link to it. */
    emptyState?: ReactNode;
}) {
    const update = (
        index: number,
        field: keyof CapabilityRow,
        value: string | boolean,
    ) => {
        const next = [...rows];
        next[index] = { ...next[index], [field]: value };
        onChange(next);
    };

    const addRow = () => {
        onChange([
            ...rows,
            withDimension
                ? {
                      itemId: '',
                      price: '',
                      dimension: '',
                      ...(withSource ? { part_number: '', source_url: '' } : {}),
                  }
                : { itemId: '', price: '' },
        ]);
    };

    const removeRow = (index: number) => {
        onChange(rows.filter((_, i) => i !== index));
    };

    return (
        <div className="mt-6">
            <h3 className="text-sm font-medium text-gray-700">{title}</h3>

            {rows.length > 0 && (
                <div className="mt-2 overflow-x-auto rounded-md border border-gray-200">
                    <table className="min-w-full divide-y">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                    {itemLabel}
                                </th>
                                {withDimension && (
                                    <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                        Size (optional)
                                    </th>
                                )}
                                <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                    Price
                                </th>
                                {withSource && (
                                    <>
                                        <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Part # (optional)
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Product link (optional)
                                        </th>
                                    </>
                                )}
                                <th />
                            </tr>
                        </thead>
                        <tbody className="divide-y bg-white">
                            {rows.map((row, index) => (
                                <tr key={index}>
                                    <td className="px-3 py-2">
                                        <SearchableSelect
                                            wrapperClassName="w-56"
                                            className="rounded-md border-gray-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                            value={String(row.itemId ?? '')}
                                            onChange={(value) => update(index, 'itemId', value)}
                                            emptyLabel="Select..."
                                            required
                                            options={options.map((option) => ({
                                                value: String(option.id),
                                                label: option.name,
                                            }))}
                                            emptyState={emptyState}
                                        />
                                        <InputError
                                            message={getError(index, 'itemId')}
                                            className="mt-1"
                                        />
                                    </td>
                                    {withDimension && (
                                        <td className="px-3 py-2">
                                            <TextInput
                                                className="block w-36"
                                                placeholder="e.g. 4 ft"
                                                maxLength={100}
                                                value={row.dimension ?? ''}
                                                onChange={(e) =>
                                                    update(
                                                        index,
                                                        'dimension',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={getError(
                                                    index,
                                                    'dimension',
                                                )}
                                                className="mt-1"
                                            />
                                        </td>
                                    )}
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
                                    {withSource && (
                                        <>
                                            <td className="px-3 py-2">
                                                <TextInput
                                                    className="block w-36"
                                                    maxLength={100}
                                                    value={row.part_number ?? ''}
                                                    onChange={(e) =>
                                                        update(
                                                            index,
                                                            'part_number',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    message={getError(index, 'part_number')}
                                                    className="mt-1"
                                                />
                                            </td>
                                            <td className="px-3 py-2">
                                                <TextInput
                                                    type="url"
                                                    className="block w-56"
                                                    placeholder="https://"
                                                    maxLength={2048}
                                                    value={row.source_url ?? ''}
                                                    onChange={(e) =>
                                                        update(
                                                            index,
                                                            'source_url',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    message={getError(index, 'source_url')}
                                                    className="mt-1"
                                                />
                                                {row.itemId !== '' && (
                                                    <div className="mt-1">
                                                        <PriceChecked
                                                            checkedAt={row.price_checked_at}
                                                            marked={!!row.mark_checked}
                                                            onMark={() =>
                                                                update(index, 'mark_checked', true)
                                                            }
                                                            url={row.source_url}
                                                        />
                                                    </div>
                                                )}
                                            </td>
                                        </>
                                    )}
                                    <td className="px-3 py-2">
                                        <button
                                            type="button"
                                            onClick={() => removeRow(index)}
                                            className="text-sm text-red-600 hover:underline"
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
