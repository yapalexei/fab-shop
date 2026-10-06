import FormErrorSummary from '@/Components/FormErrorSummary';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import OnboardingLayout from '@/Layouts/OnboardingLayout';
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface MaterialRow {
    name: string;
    unit: string;
}

interface NamedRow {
    name: string;
}

function QuickAddList<T extends { name: string }>({
    label,
    hint,
    rows,
    onChange,
    renderExtra,
}: {
    label: string;
    hint: string;
    rows: T[];
    onChange: (rows: T[]) => void;
    renderExtra?: (row: T, update: (patch: Partial<T>) => void) => React.ReactNode;
}) {
    const update = (index: number, patch: Partial<T>) => {
        const next = [...rows];
        next[index] = { ...next[index], ...patch };
        onChange(next);
    };

    const addRow = () => onChange([...rows, { name: '' } as T]);
    const removeRow = (index: number) =>
        onChange(rows.filter((_, i) => i !== index));

    return (
        <div>
            <h2 className="text-sm font-semibold text-gray-900">{label}</h2>
            <p className="text-xs text-gray-500">{hint}</p>

            <div className="mt-2 space-y-2">
                {rows.map((row, index) => (
                    <div key={index} className="flex items-center gap-2">
                        <TextInput
                            placeholder="Name"
                            className="flex-1"
                            value={row.name}
                            onChange={(e) =>
                                update(index, { name: e.target.value } as Partial<T>)
                            }
                        />
                        {renderExtra?.(row, (patch) => update(index, patch))}
                        <button
                            type="button"
                            onClick={() => removeRow(index)}
                            className="px-2 py-2 text-sm text-gray-400 hover:text-red-600"
                            aria-label={`Remove ${label} row`}
                        >
                            &times;
                        </button>
                    </div>
                ))}
            </div>

            <button
                type="button"
                onClick={addRow}
                className="mt-2 text-sm font-medium text-indigo-600 hover:underline"
            >
                + Add {label.toLowerCase()}
            </button>
        </div>
    );
}

export default function CatalogStep({
    shopName,
}: PageProps<{ shopName: string }>) {
    const { data, setData, post, processing, errors } = useForm<{
        materials: MaterialRow[];
        services: NamedRow[];
        business_partners: NamedRow[];
    }>({ materials: [], services: [], business_partners: [] });

    const errorMap = errors as Record<string, string>;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('onboarding.catalog'));
    };

    // Explicit, literal empty payload via router.post - see TeamStep for
    // why this can't be useForm's own post() after a setData() call.
    const skip = () => {
        router.post(route('onboarding.catalog'), {
            materials: [],
            services: [],
            business_partners: [],
        });
    };

    return (
        <OnboardingLayout step="catalog">
            <Head title="Add a starter catalog" />

            <h1 className="text-xl font-semibold text-gray-900">
                Add a few things to {shopName}'s catalog
            </h1>
            <p className="mt-1 text-sm text-gray-500">
                Optional, and just the name for now - add pricing, units and
                everything else later under Setup. This is only here to give
                you something to build your first quote from.
            </p>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <FormErrorSummary errors={errorMap} />

                <QuickAddList
                    label="Materials"
                    hint="e.g. 1/4in Steel Plate, 2in Square Tube"
                    rows={data.materials}
                    onChange={(rows) => setData('materials', rows)}
                    renderExtra={(row, update) => (
                        <TextInput
                            placeholder="Unit (optional)"
                            className="w-32"
                            value={row.unit}
                            onChange={(e) =>
                                update({ unit: e.target.value })
                            }
                        />
                    )}
                />

                <QuickAddList
                    label="Services"
                    hint="e.g. Powder Coating, Anodizing"
                    rows={data.services}
                    onChange={(rows) => setData('services', rows)}
                />

                <QuickAddList
                    label="Business partners"
                    hint="Suppliers or shops you outsource to"
                    rows={data.business_partners}
                    onChange={(rows) => setData('business_partners', rows)}
                />

                <div className="flex items-center justify-between border-t border-gray-100 pt-4">
                    <SecondaryButton onClick={skip} disabled={processing}>
                        Skip for now
                    </SecondaryButton>
                    <PrimaryButton disabled={processing}>
                        Finish setup
                    </PrimaryButton>
                </div>
            </form>
        </OnboardingLayout>
    );
}
