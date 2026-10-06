import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';

export interface ProcedureFormData {
    name: string;
    code: string;
    notes: string;
    [key: string]: string;
}

export default function ProcedureFields({
    data,
    setData,
    errors,
}: {
    data: ProcedureFormData;
    setData: (field: keyof ProcedureFormData, value: string) => void;
    errors: Partial<Record<keyof ProcedureFormData, string>>;
}) {
    return (
        <>
            <div>
                <InputLabel htmlFor="name" value="Name" />
                <TextInput
                    id="name"
                    className="mt-1 block w-full"
                    placeholder="e.g. GMAW Steel 3G, NDT Dye Penetrant, Forklift Operation"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    isFocused
                    required
                />
                <InputError message={errors.name} className="mt-2" />
            </div>

            <div className="mt-4">
                <InputLabel htmlFor="code" value="Code (optional)" />
                <TextInput
                    id="code"
                    className="mt-1 block w-full"
                    placeholder="an internal reference, e.g. WPS-014"
                    value={data.code}
                    onChange={(e) => setData('code', e.target.value)}
                />
                <InputError message={errors.code} className="mt-2" />
            </div>

            <div className="mt-4">
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
        </>
    );
}
