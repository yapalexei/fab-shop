import Checkbox from '@/Components/Checkbox';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useMemo, useState } from 'react';

export type SampleResource =
    'material-types' | 'materials' | 'services' | 'equipment';

interface SampleItem {
    name: string;
    group: string | null;
    detail: string | null;
    /** The shop already has it - listed, but never added again. */
    exists: boolean;
}

/**
 * Pick which starter items (material types, materials, services, equipment)
 * to add to the shop. Everything the shop doesn't have yet starts selected;
 * deselect what you don't want, or clear/select the lot.
 */
export default function SampleDataDialog({
    resource,
    label,
    note,
    show,
    onClose,
}: {
    resource: SampleResource;
    /** Plural noun for the titles and buttons, e.g. "services". */
    label: string;
    /** Heads-up shown above the list. */
    note?: string;
    show: boolean;
    onClose: () => void;
}) {
    const [items, setItems] = useState<SampleItem[] | null>(null);
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const [error, setError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    // Fetch fresh every time it opens: what the shop already has changes.
    useEffect(() => {
        if (!show) return;

        setItems(null);
        setError(null);
        axios
            .get<{ items: SampleItem[] }>(route('sample-data.index', resource))
            .then(({ data }) => {
                setItems(data.items);
                setSelected(
                    new Set(
                        data.items.filter((i) => !i.exists).map((i) => i.name),
                    ),
                );
            })
            .catch(() => setError('Could not load the samples. Try again.'));
    }, [show, resource]);

    const addable = useMemo(
        () => (items ?? []).filter((item) => !item.exists),
        [items],
    );
    const groups = useMemo(() => {
        const map = new Map<string | null, SampleItem[]>();
        (items ?? []).forEach((item) => {
            map.set(item.group, [...(map.get(item.group) ?? []), item]);
        });
        return [...map.entries()];
    }, [items]);

    const toggle = (names: string[], on: boolean) =>
        setSelected((prev) => {
            const next = new Set(prev);
            names.forEach((name) => (on ? next.add(name) : next.delete(name)));
            return next;
        });

    const submit = () => {
        router.post(
            route('sample-data.store', resource),
            { items: [...selected] },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onSuccess: onClose,
                onError: (errors) =>
                    setError(Object.values(errors)[0] ?? 'Could not add them.'),
            },
        );
    };

    return (
        <Modal show={show} maxWidth="2xl" onClose={onClose}>
            <div className="p-6">
                <h2 className="text-lg font-medium text-gray-900">
                    Add sample {label}
                </h2>
                <p className="mt-1 text-sm text-gray-600">
                    Pick what to add. Anything you already have is left alone.
                    {note && ` ${note}`}
                </p>

                {error && <p className="mt-3 text-sm text-red-600">{error}</p>}

                {items === null && !error && (
                    <p className="mt-6 text-sm text-gray-500">Loading...</p>
                )}

                {items !== null && (
                    <>
                        <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                            <button
                                type="button"
                                onClick={() =>
                                    toggle(
                                        addable.map((i) => i.name),
                                        true,
                                    )
                                }
                                className="font-medium text-indigo-600 hover:text-indigo-900"
                            >
                                Select all
                            </button>
                            <button
                                type="button"
                                onClick={() => setSelected(new Set())}
                                className="font-medium text-indigo-600 hover:text-indigo-900"
                            >
                                Deselect all
                            </button>
                            <span className="text-gray-500">
                                {selected.size} of {addable.length} selected
                            </span>
                        </div>

                        <div className="mt-3 max-h-96 overflow-y-auto rounded-md border border-gray-200">
                            {groups.map(([group, groupItems]) => {
                                const groupAddable = groupItems.filter(
                                    (i) => !i.exists,
                                );
                                const allOn =
                                    groupAddable.length > 0 &&
                                    groupAddable.every((i) =>
                                        selected.has(i.name),
                                    );

                                return (
                                    <div key={group ?? 'all'}>
                                        {group && (
                                            <label className="sticky top-0 flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-600">
                                                <Checkbox
                                                    checked={allOn}
                                                    disabled={
                                                        groupAddable.length ===
                                                        0
                                                    }
                                                    onChange={(e) =>
                                                        toggle(
                                                            groupAddable.map(
                                                                (i) => i.name,
                                                            ),
                                                            e.target.checked,
                                                        )
                                                    }
                                                />
                                                {group}
                                            </label>
                                        )}
                                        <ul className="divide-y divide-gray-100">
                                            {groupItems.map((item) => (
                                                <li key={item.name}>
                                                    <label className="flex cursor-pointer items-start gap-2 px-3 py-2 text-sm">
                                                        <Checkbox
                                                            className="mt-0.5"
                                                            checked={
                                                                item.exists ||
                                                                selected.has(
                                                                    item.name,
                                                                )
                                                            }
                                                            disabled={
                                                                item.exists
                                                            }
                                                            onChange={(e) =>
                                                                toggle(
                                                                    [item.name],
                                                                    e.target
                                                                        .checked,
                                                                )
                                                            }
                                                        />
                                                        <span>
                                                            <span className="font-medium text-gray-900">
                                                                {item.name}
                                                            </span>
                                                            {item.exists && (
                                                                <span className="ms-2 text-xs text-gray-400">
                                                                    already
                                                                    added
                                                                </span>
                                                            )}
                                                            {item.detail && (
                                                                <span className="block text-xs text-gray-500">
                                                                    {
                                                                        item.detail
                                                                    }
                                                                </span>
                                                            )}
                                                        </span>
                                                    </label>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                );
                            })}
                        </div>
                    </>
                )}

                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton onClick={onClose}>Cancel</SecondaryButton>
                    <PrimaryButton
                        onClick={submit}
                        disabled={saving || selected.size === 0}
                    >
                        Add {selected.size > 0 ? selected.size : ''} selected
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    );
}
