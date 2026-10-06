import { Row } from '@/Components/Attachments';
import { SquareImage } from '@/Components/Avatar';
import ImageField, { MAX_IMAGE_MB } from '@/Components/ImageField';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PriceChecked from '@/Components/PriceChecked';
import TextInput from '@/Components/TextInput';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { useRef } from 'react';

/** One of the shop's material types (built-in or custom). */
export interface MaterialOption {
    value: number;
    label: string;
}

export interface ToolFormData {
    name: string;
    cuts: number[];
    cost: string;
    unit: string;
    notes: string;
    part_number: string;
    source_url: string;
    /** Confirms the price is still current even though it didn't change. */
    mark_price_checked: boolean;
    /** A newly chosen photo, or null to keep the current one. */
    image: File | null;
    remove_image: boolean;
    /** A newly chosen 3D/CAD file, or null to keep the current one. */
    model: File | null;
    remove_model: boolean;
}

/** The saved 3D file, as `ToolModels::props()` sends it. */
export interface ToolModelFile {
    name: string;
    size: number;
    kind: string;
    file_path: string;
}

export const MAX_MODEL_MB = 30;
const MODEL_ACCEPT = '.step,.stp,.stl,.3mf,.obj,.dxf,.dwg';

const commonUnits = ['each', 'hole', 'ft', 'in', 'cut', 'min'];

export default function ToolFields({
    data,
    setData,
    errors,
    materialOptions,
    currentImage,
    currentModel,
    priceCheckedAt,
}: {
    data: ToolFormData;
    setData: <K extends keyof ToolFormData>(
        field: K,
        value: ToolFormData[K],
    ) => void;
    errors: Partial<Record<string, string>>;
    materialOptions: MaterialOption[];
    /** The photo already saved for this tool (edit page). */
    currentImage?: string | null;
    /** The 3D file already saved for this tool (edit page). */
    currentModel?: ToolModelFile | null;
    /** When the saved cost was last confirmed (edit page). */
    priceCheckedAt?: string | null;
}) {
    const modelInput = useRef<HTMLInputElement>(null);
    const savedModel = data.remove_model ? null : (currentModel ?? null);
    const hasModel = !!data.model || !!savedModel;

    const pickModel = (file: File | null) => {
        if (file && file.size > MAX_MODEL_MB * 1024 * 1024) {
            if (modelInput.current) modelInput.current.value = '';
            return;
        }
        setData('model', file);
        setData('remove_model', false);
    };

    const clearModel = () => {
        if (modelInput.current) modelInput.current.value = '';
        setData('model', null);
        setData('remove_model', !!currentModel);
    };

    const toggleMaterial = (value: number) =>
        setData(
            'cuts',
            data.cuts.includes(value)
                ? data.cuts.filter((v) => v !== value)
                : [...data.cuts, value],
        );

    return (
        <>
            <ImageField
                label="Photo"
                preview={(src) => (
                    <SquareImage src={src} className="size-24" />
                )}
                current={currentImage}
                file={data.image}
                removed={data.remove_image}
                onChange={(file) => setData('image', file)}
                onRemove={(removed) => setData('remove_image', removed)}
                error={errors.image}
                hint={`JPG, PNG, GIF or WebP, up to ${MAX_IMAGE_MB} MB. It's cropped to a square from the centre.`}
            />

            <div className="mt-4">
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
                    bit for aluminum are separate tools. Missing one? Add it
                    under Setup → Material types.
                </p>
                <div className="mt-2 flex flex-wrap gap-2">
                    {materialOptions.map((option) => {
                        const checked = data.cuts.includes(option.value);

                        return (
                            <label
                                key={option.value}
                                className={`inline-flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm ${checked ? 'border-primary/40 bg-primary/10 text-foreground' : 'border-border text-muted-foreground hover:bg-accent'}`}
                            >
                                <Checkbox
                                    checked={checked}
                                    onCheckedChange={() =>
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

            <div className="mt-4">
                <InputLabel htmlFor="model" value="3D file" />
                <p className="text-xs text-muted-foreground">
                    Optional. STEP, STL, 3MF, OBJ, DXF or DWG, up to{' '}
                    {MAX_MODEL_MB} MB. STL, OBJ and 3MF can be previewed in
                    the browser.
                </p>
                {data.model ? (
                    <ul className="mt-2">
                        <Row
                            name={data.model.name}
                            size={data.model.size}
                            onRemove={clearModel}
                        />
                    </ul>
                ) : savedModel ? (
                    <ul className="mt-2">
                        <Row
                            name={savedModel.name}
                            size={savedModel.size}
                            kind={savedModel.kind}
                            href={savedModel.file_path}
                            onRemove={clearModel}
                        />
                    </ul>
                ) : null}
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="mt-2"
                    onClick={() => modelInput.current?.click()}
                >
                    {hasModel ? 'Replace file' : 'Upload file'}
                </Button>
                <input
                    ref={modelInput}
                    id="model"
                    type="file"
                    accept={MODEL_ACCEPT}
                    className="sr-only"
                    tabIndex={-1}
                    onChange={(e) => pickModel(e.target.files?.[0] ?? null)}
                />
                <InputError message={errors.model} className="mt-2" />
            </div>

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

            {priceCheckedAt !== undefined && (
                <div className="mt-2">
                    <PriceChecked
                        checkedAt={priceCheckedAt}
                        marked={data.mark_price_checked}
                        onMark={() => setData('mark_price_checked', true)}
                        url={data.source_url}
                    />
                </div>
            )}

            <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel htmlFor="part_number" value="Supplier part number" />
                    <TextInput
                        id="part_number"
                        className="mt-1 block w-full"
                        maxLength={100}
                        value={data.part_number}
                        onChange={(e) => setData('part_number', e.target.value)}
                    />
                    <InputError message={errors.part_number} className="mt-2" />
                </div>
                <div>
                    <InputLabel htmlFor="source_url" value="Supplier link" />
                    <TextInput
                        id="source_url"
                        type="url"
                        className="mt-1 block w-full"
                        placeholder="https://"
                        maxLength={2048}
                        value={data.source_url}
                        onChange={(e) => setData('source_url', e.target.value)}
                    />
                    <InputError message={errors.source_url} className="mt-2" />
                </div>
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
