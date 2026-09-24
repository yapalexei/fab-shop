import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';

export interface EquipmentFormData {
    name: string;
    billing_method: 'hourly' | 'flat';
    rate: string;
    notes: string;
    [key: string]: string;
}

export default function EquipmentFields({
    data,
    setData,
    errors,
}: {
    data: EquipmentFormData;
    setData: (field: keyof EquipmentFormData, value: string) => void;
    errors: Partial<Record<keyof EquipmentFormData, string>>;
}) {
    return (
        <>
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
                <InputLabel htmlFor="billing_method" value="Billing method" />
                <select
                    id="billing_method"
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    value={data.billing_method}
                    onChange={(e) => setData('billing_method', e.target.value)}
                >
                    <option value="hourly">Hourly rate</option>
                    <option value="flat">Flat fee per use</option>
                </select>
                <InputError message={errors.billing_method} className="mt-2" />
            </div>

            <div className="mt-4">
                <InputLabel
                    htmlFor="rate"
                    value={
                        data.billing_method === 'flat'
                            ? 'Flat fee'
                            : 'Hourly rate'
                    }
                />
                <TextInput
                    id="rate"
                    type="number"
                    step="0.01"
                    min="0"
                    className="mt-1 block w-full"
                    value={data.rate}
                    onChange={(e) => setData('rate', e.target.value)}
                    required
                />
                <InputError message={errors.rate} className="mt-2" />
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
        </>
    );
}
