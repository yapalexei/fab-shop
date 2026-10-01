import CsvImportDialog from '@/Components/CsvImportDialog';
import InputError from '@/Components/InputError';
import PageHelp from '@/Components/PageHelp';
import PrimaryButton from '@/Components/PrimaryButton';
import SampleDataDialog from '@/Components/SampleDataDialog';
import SampleDataPrompt from '@/Components/SampleDataPrompt';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface MaterialTypeRow {
    id: number;
    name: string;
    is_default: boolean;
    tools_count: number;
    materials_count: number;
}

function usage(type: MaterialTypeRow): string {
    const parts = [];
    if (type.materials_count > 0) {
        parts.push(
            `${type.materials_count} material${type.materials_count === 1 ? '' : 's'}`,
        );
    }
    if (type.tools_count > 0) {
        parts.push(
            `${type.tools_count} tool${type.tools_count === 1 ? '' : 's'}`,
        );
    }
    return parts.length > 0 ? `Used by ${parts.join(', ')}` : 'Not used yet';
}

function CustomTypeRow({ type }: { type: MaterialTypeRow }) {
    const can = useCan();
    const [editing, setEditing] = useState(false);
    const { data, setData, patch, processing, errors, reset } = useForm({
        name: type.name,
    });

    const save: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('material-types.update', type.id), {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    const destroy = () => {
        if (confirm(`Delete ${type.name}?`)) {
            router.delete(route('material-types.destroy', type.id), {
                preserveScroll: true,
            });
        }
    };

    return (
        <li className="flex flex-wrap items-center justify-between gap-3 px-6 py-3">
            {editing ? (
                <form onSubmit={save} className="flex items-start gap-2">
                    <div>
                        <TextInput
                            value={data.name}
                            maxLength={50}
                            onChange={(e) => setData('name', e.target.value)}
                            isFocused
                            required
                        />
                        <InputError message={errors.name} className="mt-1" />
                    </div>
                    <PrimaryButton disabled={processing}>Save</PrimaryButton>
                    <button
                        type="button"
                        onClick={() => {
                            reset();
                            setEditing(false);
                        }}
                        className="px-2 py-2 text-sm text-gray-500 hover:text-gray-700"
                    >
                        Cancel
                    </button>
                </form>
            ) : (
                <div>
                    <p className="text-sm font-medium text-gray-900">
                        {type.name}
                    </p>
                    <p className="text-xs text-gray-500">{usage(type)}</p>
                </div>
            )}

            {!editing && (
                <div className="flex gap-4 text-sm">
                    {can('material-types.update') && (
                        <button
                            type="button"
                            onClick={() => setEditing(true)}
                            className="text-indigo-600 hover:text-indigo-900"
                        >
                            Rename
                        </button>
                    )}
                    {can('material-types.delete') && (
                        <button
                            type="button"
                            onClick={destroy}
                            className="text-red-600 hover:text-red-900"
                        >
                            Delete
                        </button>
                    )}
                </div>
            )}
        </li>
    );
}

export default function Index({
    types,
    customCount,
    customLimit,
    auth,
}: PageProps<
    {
        types: MaterialTypeRow[];
        customCount: number;
        customLimit: number;
    } & { auth: { isOwner: boolean } }
>) {
    const can = useCan();
    const [sampleOpen, setSampleOpen] = useState(false);
    const builtIn = types.filter((t) => t.is_default);
    const custom = types.filter((t) => !t.is_default);
    const atLimit = customCount >= customLimit;

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
    });

    const add: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('material-types.store'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Material types
                    </h2>
                    <PageHelp>
                        <p>
                            The kinds of material your shop works with - every
                            shop starts with a built-in set (Carbon steel,
                            Aluminum, etc.) and can add its own on top, up to a
                            shop-wide limit.
                        </p>
                        <p>
                            Used to tag catalog materials and rate tooling for
                            what it can cut, so the quote builder can warn when
                            a tool doesn't match a quote's materials.
                        </p>
                        {can('material-types.create') && (
                            <SampleDataPrompt
                                label="material types"
                                onOpen={() => setSampleOpen(true)}
                            />
                        )}
                    </PageHelp>
                </div>
            }
        >
            <Head title="Material types" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <p className="text-sm text-gray-500">
                        What your materials are made of and what your tooling is
                        rated to cut. Tag each catalog material with its type,
                        and the quote builder warns when a quote's tooling isn't
                        rated for its materials.
                    </p>

                    <section className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                            <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-500">
                                Your custom types
                            </h3>
                            <div className="flex items-center gap-4">
                                <span
                                    className={`text-sm ${atLimit ? 'font-medium text-amber-700' : 'text-gray-500'}`}
                                >
                                    {customCount} of {customLimit} used
                                </span>
                                {auth.isOwner && (
                                    <CsvImportDialog
                                        resource="material-types"
                                        label="Material types"
                                        reloadProps={['types', 'customCount']}
                                    />
                                )}
                            </div>
                        </div>

                        {custom.length === 0 ? (
                            <p className="px-6 py-4 text-sm text-gray-500">
                                None yet - add types the built-ins don't cover,
                                like Chromoly (4130) or Galvanized steel.
                            </p>
                        ) : (
                            <ul className="divide-y divide-gray-100">
                                {custom.map((type) => (
                                    <CustomTypeRow key={type.id} type={type} />
                                ))}
                            </ul>
                        )}

                        {can('material-types.create') && (
                            <form
                                onSubmit={add}
                                className="border-t border-gray-100 bg-gray-50 px-6 py-4"
                            >
                                <div className="flex flex-wrap items-start gap-2">
                                    <TextInput
                                        className="w-64"
                                        placeholder={
                                            atLimit
                                                ? 'Custom type limit reached'
                                                : 'New material type'
                                        }
                                        maxLength={50}
                                        value={data.name}
                                        disabled={atLimit}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        required
                                    />
                                    <PrimaryButton
                                        disabled={processing || atLimit}
                                    >
                                        Add
                                    </PrimaryButton>
                                </div>
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                                {atLimit && (
                                    <p className="mt-2 text-xs text-gray-500">
                                        Delete an unused custom type to add
                                        another.
                                    </p>
                                )}
                            </form>
                        )}
                    </section>

                    <section className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="border-b border-gray-100 px-6 py-4">
                            <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-500">
                                Built in
                            </h3>
                            <p className="mt-1 text-xs text-gray-500">
                                Every shop has these - they can't be renamed or
                                removed, and don't count toward your limit.
                            </p>
                        </div>
                        <ul className="divide-y divide-gray-100">
                            {builtIn.map((type) => (
                                <li
                                    key={type.id}
                                    className="flex items-center justify-between px-6 py-3"
                                >
                                    <span className="text-sm text-gray-900">
                                        {type.name}
                                    </span>
                                    <span className="text-xs text-gray-500">
                                        {usage(type)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>
            </div>

            <SampleDataDialog
                resource="material-types"
                label="material types"
                note="Specialty types count toward your custom type limit."
                show={sampleOpen}
                onClose={() => setSampleOpen(false)}
            />
        </AuthenticatedLayout>
    );
}
