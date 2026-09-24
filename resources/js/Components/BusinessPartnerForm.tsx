import CapabilityPriceTable, {
    CapabilityRow,
} from '@/Components/CapabilityPriceTable';
import FormErrorSummary from '@/Components/FormErrorSummary';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface BusinessPartnerFormData {
    name: string;
    email: string;
    phone: string;
    address: string;
    notes: string;
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
}: {
    materialOptions: { id: number; name: string }[];
    serviceOptions: { id: number; name: string }[];
    initialData: BusinessPartnerFormData;
    submitUrl: string;
    method: 'post' | 'put';
    /** View-only (no update permission): every field disabled, no save. */
    readOnly?: boolean;
}) {
    const { data, setData, post, put, processing, errors, transform } =
        useForm<BusinessPartnerFormData>(initialData);

    const errorMap = errors as Record<string, string>;

    // The capability table's rows use a generic `itemId` so the same
    // component works for both materials and services; map it back to the
    // field name the backend actually validates just before submitting.
    transform((form) => ({
        ...form,
        materials: form.materials.map((row) => ({
            material_id: row.itemId,
            price: row.price,
        })),
        services: form.services.map((row) => ({
            service_id: row.itemId,
            price: row.price,
        })),
    }));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        (method === 'post' ? post : put)(submitUrl);
    };

    return (
        <form onSubmit={submit}>
            <fieldset disabled={readOnly}>
                <FormErrorSummary errors={errorMap} />

                <div>
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
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                    rows={data.materials}
                    onChange={(rows) => setData('materials', rows)}
                    getError={(index, field) =>
                        errorMap[
                            `materials.${index}.${field === 'itemId' ? 'material_id' : 'price'}`
                        ]
                    }
                />

                <CapabilityPriceTable
                    title="Services offered"
                    itemLabel="Service"
                    addLabel="Add service"
                    options={serviceOptions}
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
                    <PrimaryButton disabled={processing}>Save</PrimaryButton>
                </div>
            )}
        </form>
    );
}
