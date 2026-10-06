import AiCatalogDraftPanel from '@/Components/AiCatalogDraftPanel';
import { AiStatus } from '@/Components/AiQuoteDraftPanel';
import Breadcrumbs from '@/Components/Breadcrumbs';
import FormErrorSummary from '@/Components/FormErrorSummary';
import PrimaryButton from '@/Components/PrimaryButton';
import ToolFields, {
    MaterialOption,
    ToolFormData,
} from '@/Components/ToolFields';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Card } from '@/Components/ui/card';

interface DraftFields {
    name: string | null;
    cost: number | null;
    unit: string | null;
    part_number: string | null;
    source_url: string | null;
    cuts: number[];
    notes: string | null;
}

export default function Create({
    materialOptions,
    ai,
}: PageProps<{ materialOptions: MaterialOption[]; ai: AiStatus }>) {
    const { data, setData, post, processing, errors } = useForm<ToolFormData>({
        name: '',
        cuts: [],
        cost: '',
        unit: 'each',
        notes: '',
        part_number: '',
        source_url: '',
        mark_price_checked: false,
        image: null,
        remove_image: false,
        model: null,
        remove_model: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('tools.store'), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <Breadcrumbs
                    items={[
                        { label: 'Tooling', href: route('tools.index') },
                        { label: 'Add tooling' },
                    ]}
                />
            }
        >
            <Head title="Add tooling" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <Card className="p-4 sm:p-8">
                        <AiCatalogDraftPanel<DraftFields>
                            kind="tools"
                            noun="tool"
                            ai={ai}
                            withLink
                            onDraft={(f) => {
                                // Only what the page stated: a null leaves
                                // whatever is already typed.
                                if (f.name !== null) setData('name', f.name);
                                if (f.cost !== null)
                                    setData('cost', String(f.cost));
                                if (f.unit !== null) setData('unit', f.unit);
                                if (f.part_number !== null)
                                    setData('part_number', f.part_number);
                                if (f.source_url !== null)
                                    setData('source_url', f.source_url);
                                if (f.cuts.length > 0)
                                    setData('cuts', f.cuts);
                                if (f.notes !== null)
                                    setData('notes', f.notes);
                            }}
                        />

                        <form onSubmit={submit}>
                            <FormErrorSummary
                                errors={errors as Record<string, string>}
                            />

                            <ToolFields
                                data={data}
                                setData={setData}
                                errors={errors}
                                materialOptions={materialOptions}
                            />

                            <div className="mt-6 flex justify-end">
                                <PrimaryButton disabled={processing}>
                                    Save
                                </PrimaryButton>
                            </div>
                        </form>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
