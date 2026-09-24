import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';

export interface MaterialOption {
    value: string;
    label: string;
}

export interface ToolFormData {
    name: string;
    cuts: string[];
    cost: string;
    unit: string;
    notes: string;
}

const commonUnits = ['each', 'hole', 'ft', 'in', 'cut', 'min'];

export default function ToolFields({
    data,
    setData,
    errors,
    materialOptions,
}: {
    data: ToolFormData;
    setData: <K extends keyof ToolFormData>(
        field: K,
        value: ToolFormData[K],
    ) => void;
    errors: Partial<Record<string, string>>;
    materialOptions: MaterialOption[];
}) {
    const toggleMaterial = (value: string) =>
        setData(
            'cuts',
            data.cuts.includes(value)
                ? data.cuts.filter((v) => v !== value)
                : [...data.cuts, value],
        );

    return (
        <>
            <div>
                <InputLabel htmlFor="name" value="Name" />
                <TextInput
                    id="name"
                    className="mt-1 block w-full"
                    value={data.name}
                    placeholder='e.g. 1/4" cobalt drill bit'
                    onChange={(e) => setData('name', e.target.value)}
                    isFocused
                    required
                />
                <InputError message={errors.name} className="mt-2" />
            </div>

            <fieldset className="mt-4">
                <legend className="block text-sm font-medium text-gray-700">
                    Cuts
                </legend>
                <p className="text-xs text-gray-500">
                    Every material this tool is rated for. A bit for steel and a
                    bit for aluminum are separate tools.
                </p>
                <div className="mt-2 flex flex-wrap gap-2">
                    {materialOptions.map((option) => {
                        const checked = data.cuts.includes(option.value);

                        return (
                            <label
                                key={option.value}
                                className={`inline-flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm ${checked ? 'border-indigo-300 bg-indigo-50 text-indigo-900' : 'border-gray-200 text-gray-600 hover:bg-gray-50'}`}
                            >
                                <input
                                    type="checkbox"
                                    className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    checked={checked}
                                    onChange={() =>
                                        toggleMaterial(option.value)
                                    }
                                />
                                {option.label}
                            </label>
                        );
                    })}
                </div>
                <InputError
                    message={errors.cuts ?? errors['cuts.0']}
                    className="mt-2"
                />
            </fieldset>

            <div className="mt-4 grid grid-cols-2 gap-4">
                <div>
                    <InputLabel htmlFor="cost" value="Cost" />
                    <TextInput
                        id="cost"
                        type="number"
                        step="0.01"
                        min="0"
                        className="mt-1 block w-full"
                        value={data.cost}
                        onChange={(e) => setData('cost', e.target.value)}
                        required
                    />
                    <InputError message={errors.cost} className="mt-2" />
                </div>
                <div>
                    <InputLabel htmlFor="unit" value="Per" />
                    <TextInput
                        id="unit"
                        list="tool-units"
                        className="mt-1 block w-full"
                        value={data.unit}
                        onChange={(e) => setData('unit', e.target.value)}
                        required
                    />
                    <datalist id="tool-units">
                        {commonUnits.map((unit) => (
                            <option key={unit} value={unit} />
                        ))}
                    </datalist>
                    <p className="mt-1 text-xs text-gray-500">
                        What one quantity on a quote means - each tool, per
                        hole, per foot of cut...
                    </p>
                    <InputError message={errors.unit} className="mt-2" />
                </div>
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
