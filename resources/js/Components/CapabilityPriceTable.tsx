import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PriceChecked from '@/Components/PriceChecked';
import PrimaryButton from '@/Components/PrimaryButton';
import SearchableSelect from '@/Components/SearchableSelect';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useShopDateTime } from '@/lib/datetime';
import { ExternalLink } from 'lucide-react';
import { KeyboardEvent, ReactNode, useState } from 'react';

export interface CapabilityRow {
    itemId: number | string;
    price: number | string;
    /** Only for lists with `withDimension`: the size this price is for. */
    dimension?: string;
    /** Only for lists with `withSource`: where the price came from. */
    part_number?: string;
    source_url?: string;
    price_checked_at?: string | null;
    /** Confirms the price is still current although it didn't change. */
    mark_checked?: boolean;
}

type Field = 'itemId' | 'price' | 'dimension' | 'part_number' | 'source_url';

const money = (value: number | string) =>
    Number(value).toLocaleString('en-US', {
        style: 'currency',
        currency: 'USD',
    });

/**
 * A compact list of "item, price" rows (a supplier's materials, a
 * provider's services) with each row's inputs in a dialog, so a row costs one
 * line of the page instead of a wide table. Changes only edit the form's
 * rows in memory - they are saved with the rest of the form.
 *
 * The dialog is deliberately not a <form>: it is portaled out of the page's
 * own form, but React would still bubble its submit up to it.
 */
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
    getError: (index: number, field: Field) => string | undefined;
    /**
     * Adds an optional size, so the same item can be listed more than once at
     * different sizes (4 ft vs 4 in of bar stock).
     */
    withDimension?: boolean;
    /**
     * Adds a part number and product link per row, and shows when the price
     * was last checked, so it can be looked up and refreshed later.
     */
    withSource?: boolean;
    /** Shown in the dialog's picker when `options` is empty - say what to create and link to it. */
    emptyState?: ReactNode;
}) {
    const formatDate = useShopDateTime({ dateStyle: 'medium' });
    // null = closed, -1 = adding, otherwise the index being edited.
    const [editing, setEditing] = useState<number | null>(null);
    const [draft, setDraft] = useState<CapabilityRow | null>(null);
    const [problems, setProblems] = useState<Partial<Record<Field, string>>>({});

    const nameOf = (itemId: number | string) =>
        options.find((o) => String(o.id) === String(itemId))?.name ?? 'Unknown';

    const open = (index: number) => {
        setProblems({});
        setEditing(index);
        setDraft(
            index === -1
                ? {
                      itemId: '',
                      price: '',
                      ...(withDimension ? { dimension: '' } : {}),
                      ...(withSource
                          ? { part_number: '', source_url: '' }
                          : {}),
                  }
                : { ...rows[index] },
        );
    };

    const close = () => {
        setEditing(null);
        setDraft(null);
    };

    const set = (field: keyof CapabilityRow, value: string | boolean) =>
        setDraft((d) => (d ? { ...d, [field]: value } : d));

    const save = () => {
        if (!draft || editing === null) return;

        const found: Partial<Record<Field, string>> = {};
        if (draft.itemId === '') found.itemId = `Choose a ${itemLabel.toLowerCase()}.`;
        if (draft.price === '' || Number(draft.price) < 0)
            found.price = 'Enter a price.';

        if (Object.keys(found).length > 0) {
            setProblems(found);
            return;
        }

        onChange(
            editing === -1
                ? [...rows, draft]
                : rows.map((row, i) => (i === editing ? draft : row)),
        );
        close();
    };

    // Enter saves the row instead of the whole page.
    const enterSaves = (e: KeyboardEvent) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            save();
        }
    };

    const remove = (index: number) => onChange(rows.filter((_, i) => i !== index));

    return (
        <div className="mt-6">
            <div className="flex items-center justify-between gap-3">
                <h3 className="text-sm font-medium text-gray-700">{title}</h3>
                <SecondaryButton type="button" onClick={() => open(-1)}>
                    {addLabel}
                </SecondaryButton>
            </div>

            {rows.length > 0 && (
                <ul className="mt-2 divide-y rounded-md border border-gray-200">
                    {rows.map((row, index) => {
                        const fields: Field[] = [
                            'itemId',
                            ...(withDimension ? (['dimension'] as Field[]) : []),
                            'price',
                            ...(withSource
                                ? (['part_number', 'source_url'] as Field[])
                                : []),
                        ];
                        const rowErrors = [
                            ...new Set(
                                fields
                                    .map((field) => getError(index, field))
                                    .filter((m): m is string => !!m),
                            ),
                        ];

                        return (
                            <li
                                key={index}
                                className={`flex items-start gap-3 px-3 py-2 ${rowErrors.length ? 'bg-red-50' : ''}`}
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">
                                        {nameOf(row.itemId)}
                                        {row.dimension && (
                                            <span className="ms-2 font-normal text-muted-foreground">
                                                {row.dimension}
                                            </span>
                                        )}
                                    </p>
                                    {withSource &&
                                        (row.part_number ||
                                            row.price_checked_at ||
                                            row.source_url) && (
                                            <p className="mt-0.5 flex flex-wrap items-center gap-x-3 text-xs text-muted-foreground">
                                                {row.part_number && (
                                                    <span>
                                                        Part # {row.part_number}
                                                    </span>
                                                )}
                                                {row.mark_checked ? (
                                                    <span>
                                                        Will be marked checked
                                                        today
                                                    </span>
                                                ) : (
                                                    row.price_checked_at && (
                                                        <span>
                                                            Checked{' '}
                                                            {formatDate(
                                                                row.price_checked_at,
                                                            )}
                                                        </span>
                                                    )
                                                )}
                                                {row.source_url && (
                                                    <a
                                                        href={row.source_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                                                    >
                                                        Open at supplier
                                                        <ExternalLink className="size-3" />
                                                    </a>
                                                )}
                                            </p>
                                        )}
                                    {rowErrors.map((message) => (
                                        <p
                                            key={message}
                                            className="mt-0.5 text-xs text-red-600"
                                        >
                                            {message}
                                        </p>
                                    ))}
                                </div>
                                <span className="shrink-0 text-sm tabular-nums">
                                    {row.price === '' ? '—' : money(row.price)}
                                </span>
                                <div className="flex shrink-0 gap-3 text-sm">
                                    <button
                                        type="button"
                                        onClick={() => open(index)}
                                        className="text-primary hover:underline"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => remove(index)}
                                        className="text-red-600 hover:underline"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}

            {rows.length > 0 && (
                <p className="mt-1 text-xs text-muted-foreground">
                    Changes here are saved with the rest of the page.
                </p>
            )}

            <Modal
                show={editing !== null}
                onClose={close}
                maxWidth="lg"
                title={editing === -1 ? addLabel : `Edit ${itemLabel.toLowerCase()}`}
            >
                {draft && (
                    <div className="space-y-4 p-6">
                        <h2 className="text-lg font-medium text-gray-900">
                            {editing === -1
                                ? addLabel
                                : `Edit ${itemLabel.toLowerCase()}`}
                        </h2>

                        <div>
                            <InputLabel value={itemLabel} />
                            <SearchableSelect
                                wrapperClassName="mt-1 w-full"
                                className="rounded-md border-gray-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                ariaLabel={itemLabel}
                                value={String(draft.itemId ?? '')}
                                onChange={(value) => set('itemId', value)}
                                emptyLabel="Select..."
                                options={options.map((option) => ({
                                    value: String(option.id),
                                    label: option.name,
                                }))}
                                emptyState={emptyState}
                            />
                            <InputError message={problems.itemId} className="mt-1" />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            {withDimension && (
                                <div>
                                    <InputLabel
                                        htmlFor="cap-dimension"
                                        value="Size (optional)"
                                    />
                                    <TextInput
                                        id="cap-dimension"
                                        className="mt-1 block w-full"
                                        placeholder="e.g. 4 ft"
                                        maxLength={100}
                                        value={draft.dimension ?? ''}
                                        onChange={(e) =>
                                            set('dimension', e.target.value)
                                        }
                                        onKeyDown={enterSaves}
                                    />
                                </div>
                            )}
                            <div>
                                <InputLabel htmlFor="cap-price" value="Price" />
                                <TextInput
                                    id="cap-price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    className="mt-1 block w-full"
                                    value={draft.price}
                                    onChange={(e) => set('price', e.target.value)}
                                    onKeyDown={enterSaves}
                                />
                                <InputError message={problems.price} className="mt-1" />
                            </div>
                        </div>

                        {withSource && (
                            <>
                                <div>
                                    <InputLabel
                                        htmlFor="cap-part"
                                        value="Part number (optional)"
                                    />
                                    <TextInput
                                        id="cap-part"
                                        className="mt-1 block w-full"
                                        maxLength={100}
                                        value={draft.part_number ?? ''}
                                        onChange={(e) =>
                                            set('part_number', e.target.value)
                                        }
                                        onKeyDown={enterSaves}
                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        htmlFor="cap-url"
                                        value="Product link (optional)"
                                    />
                                    <TextInput
                                        id="cap-url"
                                        type="url"
                                        className="mt-1 block w-full"
                                        placeholder="https://"
                                        maxLength={2048}
                                        value={draft.source_url ?? ''}
                                        onChange={(e) =>
                                            set('source_url', e.target.value)
                                        }
                                        onKeyDown={enterSaves}
                                    />
                                </div>
                                {editing !== -1 && (
                                    <PriceChecked
                                        checkedAt={draft.price_checked_at}
                                        marked={!!draft.mark_checked}
                                        onMark={() => set('mark_checked', true)}
                                        url={draft.source_url}
                                    />
                                )}
                            </>
                        )}

                        <div className="flex justify-end gap-3 pt-2">
                            <SecondaryButton type="button" onClick={close}>
                                Cancel
                            </SecondaryButton>
                            <PrimaryButton type="button" onClick={save}>
                                {editing === -1 ? 'Add' : 'Done'}
                            </PrimaryButton>
                        </div>
                    </div>
                )}
            </Modal>
        </div>
    );
}
