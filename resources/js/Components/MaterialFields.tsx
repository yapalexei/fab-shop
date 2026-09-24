import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';

export interface MaterialFormData {
    name: string;
    unit: string;
    notes: string;
    material_type_id: string;
    [key: string]: string;
}

export interface MaterialTypeOption {
    id: number;
    name: string;
}

export default function MaterialFields({
    data,
    setData,
    errors,
    materialTypes,
}: {
    data: MaterialFormData;
    setData: (field: keyof MaterialFormData, value: string) => void;
    errors: Partial<Record<keyof MaterialFormData, string>>;
    materialTypes: MaterialTypeOption[];
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
                <InputLabel htmlFor="material_type_id" value="Material type" />
                <select
                    id="material_type_id"
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    value={data.material_type_id}
                    onChange={(e) =>
                        setData('material_type_id', e.target.value)
                    }
                >
                    <option value="">Not set</option>
                    {materialTypes.map((type) => (
                        <option key={type.id} value={type.id}>
                            {type.name}
                        </option>
                    ))}
                </select>
                <p className="mt-1 text-xs text-gray-500">
                    Lets quotes check their tooling is rated to cut this
                    material.
                </p>
                <InputError message={errors.material_type_id} className="mt-2" />
            </div>

            <div className="mt-4">
                <InputLabel htmlFor="unit" value="Unit of measure" />
                <TextInput
                    id="unit"
                    className="mt-1 block w-full"
                    placeholder="each, ft, lb, sheet..."
                    value={data.unit}
                    onChange={(e) => setData('unit', e.target.value)}
                />
                <InputError message={errors.unit} className="mt-2" />
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
