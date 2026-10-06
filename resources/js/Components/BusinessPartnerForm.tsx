import AiCatalogDraftPanel from '@/Components/AiCatalogDraftPanel';
import { AiStatus } from '@/Components/AiQuoteDraftPanel';
import { LogoAvatar } from '@/Components/Avatar';
import ImageField from '@/Components/ImageField';
import CapabilityPriceTable, {
    CapabilityRow,
} from '@/Components/CapabilityPriceTable';
import FormErrorSummary from '@/Components/FormErrorSummary';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface TypeOption {
    value: string;
    label: string;
}

export interface BusinessPartnerFormData {
    name: string;
    type: string;
    email: string;
    phone: string;
    address: string;
    notes: string;
    /** A newly chosen logo, or null to keep the current one. */
    logo: File | null;
    remove_logo: boolean;
    materials: CapabilityRow[];
    services: CapabilityRow[];
}

interface PriceDraft {
    material_id: number | null;
    name: string | null;
    dimension: string | null;
    price: number | null;
    part_number: string | null;
    source_url: string | null;
}

export default function BusinessPartnerForm({
    materialOptions,
    serviceOptions,
    typeOptions,
    initialData,
    submitUrl,
    method,
    readOnly = false,
    currentLogo,
    ai,
}: {
    materialOptions: { id: number; name: string }[];
    serviceOptions: { id: number; name: string }[];
    typeOptions: TypeOption[];
    initialData: BusinessPartnerFormData;
    submitUrl: string;
    method: 'post' | 'put';
    /** View-only (no update permission): every field disabled, no save. */
    readOnly?: boolean;
    /** The logo already saved for this partner (edit page). */
    currentLogo?: string | null;
    /** Whether "Fill from a supplier page" is available (absent = hide it). */
    ai?: AiStatus;
}) {
    const { data, setData, post, processing, errors, transform } =
        useForm<BusinessPartnerFormData>(initialData);

    const errorMap = errors as Record<string, string>;

    // The capability table's rows use a generic `itemId` so the same
    // component works for both materials and services; map it back to the
    // field name the backend actually validates just before submitting.
    transform((form) => ({
        ...form,
        // Files can't travel in a PUT body, so an update is a POST that
        // spoofs PUT.
        ...(method === 'put' ? { _method: 'put' } : {}),
        materials: form.materials.map((row) => ({
            material_id: row.itemId,
            dimension: row.dimension ?? '',
            price: row.price,
            part_number: row.part_number ?? '',
            source_url: row.source_url ?? '',
            mark_checked: row.mark_checked ?? false,
        })),
        services: form.services.map((row) => ({
            service_id: row.itemId,
            price: row.price,
        })),
    }));

    // A drafted price becomes (or updates) a row in the materials table; it
    // is saved with the rest of the form. Nothing without a matching catalog
    // material can be added - a row needs one.
    const addDraftedPrice = (f: PriceDraft) => {
        if (f.material_id === null) return false;

        const dimension = (f.dimension ?? '').trim();
        const fields = {
            price: f.price !== null ? String(f.price) : '',
            part_number: f.part_number ?? '',
            source_url: f.source_url ?? '',
        };
        const index = data.materials.findIndex(
            (row) =>
                Number(row.itemId) === f.material_id &&
                (row.dimension ?? '').trim().toLowerCase() ===
                    dimension.toLowerCase(),
        );

        if (index >= 0) {
            // Same material and size: refresh it. A blank leaves what's there.
            setData(
                'materials',
                data.materials.map((row, i) =>
                    i === index
                        ? {
                              ...row,
                              price: fields.price || row.price,
                              part_number: fields.part_number || row.part_number,
                              source_url: fields.source_url || row.source_url,
                              mark_checked: fields.price !== '',
                          }
                        : row,
                ),
            );
        } else {
            setData('materials', [
                ...data.materials,
                { itemId: f.material_id, dimension, ...fields },
            ]);
        }

        return true;
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(submitUrl, { forceFormData: true });
    };

    return (
        <form onSubmit={submit}>
            <fieldset disabled={readOnly}>
                <FormErrorSummary errors={errorMap} />

                <ImageField
                    label="Logo"
                    preview={(src) => (
                        <LogoAvatar src={src} className="size-16" />
                    )}
                    current={currentLogo}
                    file={data.logo}
                    removed={data.remove_logo}
                    onChange={(file) => setData('logo', file)}
                    onRemove={(removed) => setData('remove_logo', removed)}
                    error={errors.logo}
                />

                <div className="mt-4">
                    <InputLabel htmlFor="name" value="Name" />
                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        isFocused
                        required
                    />
                    <InputError message={errors.name} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="type" value="Type" />
                    <select
                        id="type"
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.type}
                        onChange={(e) => setData('type', e.target.value)}
                    >
                        {typeOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Suppliers sell you materials; service providers do work
                        for you (anodizing, powder coating, laser cutting...).
                    </p>
                    <InputError message={errors.type} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="email" value="Email" />
                    <TextInput
                        id="email"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="phone" value="Phone" />
                    <TextInput
                        id="phone"
                        className="mt-1 block w-full"
                        value={data.phone}
                        onChange={(e) => setData('phone', e.target.value)}
                    />
                    <InputError message={errors.phone} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="address" value="Address" />
                    <textarea
                        id="address"
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                        rows={3}
                        value={data.address}
                        onChange={(e) => setData('address', e.target.value)}
                    />
                    <InputError message={errors.address} className="mt-2" />
                </div>

                {ai && !readOnly && (
                    <div className="mt-6">
                        <AiCatalogDraftPanel<PriceDraft>
                            kind="supplier-prices"
                            noun="price"
                            ai={ai}
                            withLink
                            onDraft={addDraftedPrice}
                        />
                    </div>
                )}

                <CapabilityPriceTable
                    title="Materials supplied"
                    itemLabel="Material"
                    addLabel="Add material"
                    options={materialOptions}
                    emptyState={
                        <>
                            No materials yet.{' '}
                            <Link href={route('materials.create')} className="font-medium underline">Create a material</Link> before
                            adding supplier prices.
                        </>
                    }
                    rows={data.materials}
                    withDimension
                    withSource
                    onChange={(rows) => setData('materials', rows)}
                    getError={(index, field) =>
                        errorMap[
                            `materials.${index}.${field === 'itemId' ? 'material_id' : field}`
                        ]
                    }
                />

                <CapabilityPriceTable
                    title="Services offered"
                    itemLabel="Service"
                    addLabel="Add service"
                    options={serviceOptions}
                    emptyState={
                        <>
                            No services yet.{' '}
                            <Link href={route('services.create')} className="font-medium underline">Create a service</Link> before
                            adding prices.
                        </>
                    }
                    rows={data.services}
                    onChange={(rows) => setData('services', rows)}
                    getError={(index, field) =>
                        errorMap[
                            `services.${index}.${field === 'itemId' ? 'service_id' : 'price'}`
                        ]
                    }
                />

                <div className="mt-6">
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

            </fieldset>

            {!readOnly && (
                <div className="mt-6 flex justify-end">
                    <PrimaryButton disabled={processing}>Save</PrimaryButton>
                </div>
            )}
        </form>
    );
}
