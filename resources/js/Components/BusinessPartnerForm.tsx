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

export interface BusinessPartnerFormData {
    name: string;
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

export default function BusinessPartnerForm({
    materialOptions,
    serviceOptions,
    initialData,
    submitUrl,
    method,
    readOnly = false,
    currentLogo,
}: {
    materialOptions: { id: number; name: string }[];
    serviceOptions: { id: number; name: string }[];
    initialData: BusinessPartnerFormData;
    submitUrl: string;
    method: 'post' | 'put';
    /** View-only (no update permission): every field disabled, no save. */
    readOnly?: boolean;
    /** The logo already saved for this partner (edit page). */
    currentLogo?: string | null;
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
        })),
        services: form.services.map((row) => ({
            service_id: row.itemId,
            price: row.price,
        })),
    }));

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
