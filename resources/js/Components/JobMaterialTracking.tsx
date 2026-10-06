import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SearchableSelect from '@/Components/SearchableSelect';
import TextInput from '@/Components/TextInput';
import { useCan } from '@/lib/permissions';
import { useShopDateTime } from '@/lib/datetime';
import { Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';

export interface MaterialUsage {
    id: number;
    material_id: number | null;
    description: string;
    unit: string | null;
    quantity: number;
    unit_cost: number;
    cost: number;
    type: 'used' | 'scrap';
    notes: string | null;
    recorded_by: string | null;
    created_at: string;
}

export interface MaterialCostSummary {
    estimated: number;
    actual: number;
    scrap: number;
    variance: number;
}

export interface MaterialOption {
    id: number;
    name: string;
    unit: string | null;
    suggested_cost: number | null;
}

const currency = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

function SummaryStat({
    label,
    value,
    tone = 'default',
}: {
    label: string;
    value: string;
    tone?: 'default' | 'good' | 'bad';
}) {
    return (
        <div>
            <p className="text-xs uppercase tracking-wider text-gray-500">
                {label}
            </p>
            <p
                className={`text-lg font-semibold ${
                    tone === 'bad'
                        ? 'text-red-600'
                        : tone === 'good'
                          ? 'text-green-600'
                          : 'text-gray-900'
                }`}
            >
                {value}
            </p>
        </div>
    );
}

export default function JobMaterialTracking({
    jobId,
    readOnly = false,
    usages,
    costSummary,
    materials,
}: {
    jobId: number;
    readOnly?: boolean;
    usages: MaterialUsage[];
    costSummary: MaterialCostSummary;
    materials: MaterialOption[];
}) {
    const canEdit = useCan()('jobs.update') && !readOnly;
    const formatDateTime = useShopDateTime();
    const [showForm, setShowForm] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        material_id: '' as number | '',
        description: '',
        unit: '',
        quantity: '1',
        unit_cost: '',
        type: 'used' as 'used' | 'scrap',
        notes: '',
    });

    const selectMaterial = (materialId: string) => {
        if (materialId === '') {
            setData({ ...data, material_id: '', description: '' });
            return;
        }
        const material = materials.find((m) => m.id === Number(materialId));
        setData({
            ...data,
            material_id: Number(materialId),
            description: material?.name ?? data.description,
            unit: material?.unit ?? data.unit,
            unit_cost:
                material?.suggested_cost != null
                    ? String(material.suggested_cost)
                    : data.unit_cost,
        });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('jobs.material-usage.store', jobId), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setShowForm(false);
            },
        });
    };

    const destroy = (usageId: number) => {
        if (!confirm('Remove this material usage entry?')) return;

        router.delete(
            route('jobs.material-usage.destroy', [jobId, usageId]),
            { preserveScroll: true },
        );
    };

    const varianceTone =
        costSummary.variance > 0
            ? 'bad'
            : costSummary.variance < 0
              ? 'good'
              : 'default';

    return (
        <Card className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-500">
                    Material & scrap tracking
                </h3>
                {canEdit && (
                    <button
                        type="button"
                        onClick={() => setShowForm((v) => !v)}
                        className="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-accent"
                    >
                        {showForm ? 'Cancel' : 'Log material'}
                    </button>
                )}
            </div>

            <div className="grid grid-cols-2 gap-4 rounded-md bg-gray-50 p-4 sm:grid-cols-4">
                <SummaryStat
                    label="Estimated"
                    value={currency.format(costSummary.estimated)}
                />
                <SummaryStat
                    label="Actual used"
                    value={currency.format(costSummary.actual)}
                />
                <SummaryStat
                    label="Scrap"
                    value={currency.format(costSummary.scrap)}
                />
                <SummaryStat
                    label="Variance"
                    value={`${costSummary.variance > 0 ? '+' : ''}${currency.format(costSummary.variance)}`}
                    tone={varianceTone}
                />
            </div>

            {showForm && (
                <form
                    onSubmit={submit}
                    className="mt-4 space-y-3 border-t border-gray-100 pt-4"
                >
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <SearchableSelect
                            className="rounded-md border-gray-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                            value={String(data.material_id)}
                            onChange={selectMaterial}
                            emptyLabel="Custom (not in catalog)"
                            options={materials.map((material) => ({
                                value: String(material.id),
                                label: material.name,
                            }))}
                            emptyState={
                                <>
                                    No materials yet. <Link href={route('materials.create')} className="font-medium underline">Create a material</Link> to
                                    pick it here.
                                </>
                            }
                        />
                        <SearchableSelect
                            className="rounded-md border-gray-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.type}
                            onChange={(value) => setData('type', value as 'used' | 'scrap')}
                            options={[
                                { value: 'used', label: 'Used' },
                                { value: 'scrap', label: 'Scrap' },
                            ]}
                        />
                    </div>

                    <div>
                        <TextInput
                            placeholder="Description"
                            className="block w-full"
                            value={data.description}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                            required
                        />
                        <InputError
                            message={errors.description}
                            className="mt-1"
                        />
                    </div>

                    <div className="grid grid-cols-3 gap-3">
                        <div>
                            <TextInput
                                type="number"
                                step="0.01"
                                min="0.01"
                                placeholder="Quantity"
                                className="block w-full"
                                value={data.quantity}
                                onChange={(e) =>
                                    setData('quantity', e.target.value)
                                }
                                required
                            />
                            <InputError
                                message={errors.quantity}
                                className="mt-1"
                            />
                        </div>
                        <TextInput
                            placeholder="Unit (sheet, ft...)"
                            className="block w-full"
                            value={data.unit}
                            onChange={(e) => setData('unit', e.target.value)}
                        />
                        <div>
                            <TextInput
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="Unit cost"
                                className="block w-full"
                                value={data.unit_cost}
                                onChange={(e) =>
                                    setData('unit_cost', e.target.value)
                                }
                                required
                            />
                            <InputError
                                message={errors.unit_cost}
                                className="mt-1"
                            />
                        </div>
                    </div>

                    <textarea
                        className="block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                        rows={2}
                        placeholder="Notes (optional) - e.g. reason for scrap"
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                    />

                    <div className="flex justify-end">
                        <PrimaryButton disabled={processing}>
                            Log entry
                        </PrimaryButton>
                    </div>
                </form>
            )}

            <div className="mt-4">
                {usages.length === 0 ? (
                    <p className="text-sm text-gray-500">
                        Nothing logged yet.
                    </p>
                ) : (
                    <ul className="divide-y">
                        {usages.map((usage) => (
                            <li
                                key={usage.id}
                                className="flex items-start justify-between gap-3 py-2 text-sm"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="flex items-center gap-2 text-gray-900">
                                        <Badge
                                            variant={
                                                usage.type === 'scrap'
                                                    ? 'warning'
                                                    : 'secondary'
                                            }
                                        >
                                            {usage.type === 'scrap'
                                                ? 'Scrap'
                                                : 'Used'}
                                        </Badge>
                                        {usage.quantity}
                                        {usage.unit ? ` ${usage.unit}` : ''} of{' '}
                                        {usage.description}
                                    </p>
                                    {usage.notes && (
                                        <p className="text-xs text-gray-500">
                                            {usage.notes}
                                        </p>
                                    )}
                                    <p className="text-xs text-gray-400">
                                        {usage.recorded_by ?? 'System'} -{' '}
                                        {formatDateTime(usage.created_at)}
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-3">
                                    <span className="text-gray-500">
                                        {currency.format(usage.cost)}
                                    </span>
                                    {canEdit && (
                                        <button
                                            type="button"
                                            onClick={() => destroy(usage.id)}
                                            className="text-gray-400 hover:text-red-600"
                                            aria-label="Remove entry"
                                        >
                                            &times;
                                        </button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </Card>
    );
}
