import InputError from '@/Components/InputError';
import PageHelp from '@/Components/PageHelp';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface StageRow {
    id: number;
    name: string;
    role: 'not_started' | 'shipped' | 'invoiced' | null;
    is_built_in: boolean;
    jobs_count: number;
}

function roleNote(role: StageRow['role']): string {
    switch (role) {
        case 'not_started':
            return 'Every job starts here.';
        case 'shipped':
            return 'Flags a job as ready to invoice on the dashboard.';
        case 'invoiced':
            return 'Pushes the invoice to QuickBooks, if connected.';
        default:
            return '';
    }
}

function CustomStageRow({
    stage,
    canMoveUp,
    canMoveDown,
    onMoveUp,
    onMoveDown,
}: {
    stage: StageRow;
    canMoveUp: boolean;
    canMoveDown: boolean;
    onMoveUp: () => void;
    onMoveDown: () => void;
}) {
    const can = useCan();
    const [editing, setEditing] = useState(false);
    const { data, setData, patch, processing, errors, reset } = useForm({
        name: stage.name,
    });

    const save: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('job-stages.update', stage.id), {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    const destroy = () => {
        if (confirm(`Delete ${stage.name}?`)) {
            router.delete(route('job-stages.destroy', stage.id), {
                preserveScroll: true,
            });
        }
    };

    return (
        <li className="flex flex-wrap items-center justify-between gap-3 bg-white px-6 py-3">
            <div className="flex items-center gap-2">
                {can('job-stages.update') && (
                    <div className="flex flex-col">
                        <button
                            type="button"
                            onClick={onMoveUp}
                            disabled={!canMoveUp}
                            aria-label={`Move ${stage.name} earlier`}
                            className="text-gray-400 hover:text-gray-700 disabled:opacity-25"
                        >
                            ▲
                        </button>
                        <button
                            type="button"
                            onClick={onMoveDown}
                            disabled={!canMoveDown}
                            aria-label={`Move ${stage.name} later`}
                            className="text-gray-400 hover:text-gray-700 disabled:opacity-25"
                        >
                            ▼
                        </button>
                    </div>
                )}

                {editing ? (
                    <form onSubmit={save} className="flex items-start gap-2">
                        <div>
                            <TextInput
                                value={data.name}
                                maxLength={50}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                isFocused
                                required
                            />
                            <InputError
                                message={errors.name}
                                className="mt-1"
                            />
                        </div>
                        <PrimaryButton disabled={processing}>
                            Save
                        </PrimaryButton>
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
                            {stage.name}
                        </p>
                        <p className="text-xs text-gray-500">
                            {stage.jobs_count > 0
                                ? `${stage.jobs_count} job${stage.jobs_count === 1 ? '' : 's'} here now`
                                : 'No jobs here right now'}
                        </p>
                    </div>
                )}
            </div>

            {!editing && (
                <div className="flex gap-4 text-sm">
                    {can('job-stages.update') && (
                        <button
                            type="button"
                            onClick={() => setEditing(true)}
                            className="text-indigo-600 hover:text-indigo-900"
                        >
                            Rename
                        </button>
                    )}
                    {can('job-stages.delete') && (
                        <button
                            type="button"
                            onClick={destroy}
                            disabled={stage.jobs_count > 0}
                            title={
                                stage.jobs_count > 0
                                    ? 'Move its jobs to another stage first'
                                    : undefined
                            }
                            className="text-red-600 hover:text-red-900 disabled:cursor-not-allowed disabled:text-gray-300"
                        >
                            Delete
                        </button>
                    )}
                </div>
            )}
        </li>
    );
}

function BuiltInStageRow({ stage }: { stage: StageRow }) {
    return (
        <li className="flex items-center justify-between bg-gray-50 px-6 py-3">
            <div>
                <span className="text-sm font-medium text-gray-700">
                    {stage.name}
                </span>
                <span className="ms-2 inline-flex items-center rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600">
                    Fixed
                </span>
                <p className="mt-0.5 text-xs text-gray-500">
                    {roleNote(stage.role)}
                </p>
            </div>
            <span className="text-xs text-gray-500">
                {stage.jobs_count} job{stage.jobs_count === 1 ? '' : 's'}
            </span>
        </li>
    );
}

export default function Index({ stages }: PageProps<{ stages: StageRow[] }>) {
    const can = useCan();
    const customStages = stages.filter((s) => !s.is_built_in);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
    });

    const add: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('job-stages.store'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const reorder = (fromIndex: number, toIndex: number) => {
        const ids = customStages.map((s) => s.id);
        const [moved] = ids.splice(fromIndex, 1);
        ids.splice(toIndex, 0, moved);

        router.patch(
            route('job-stages.reorder'),
            { ordered_ids: ids },
            { preserveScroll: true },
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Job board stages
                    </h2>
                    <PageHelp>
                        <p>
                            The columns on your job board, in the order jobs
                            travel through them. Not Started, Shipped and
                            Invoiced always exist and can't be changed -
                            everything between them is yours: name it for how
                            your shop actually works, add as many or as few as
                            you need, and reorder them.
                        </p>
                        <p>
                            A job can skip a stage it doesn't need - nothing
                            forces it to stop in every column.
                        </p>
                    </PageHelp>
                </div>
            }
        >
            <Head title="Job board stages" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <ul className="divide-y divide-gray-100">
                            {stages.map((stage) => {
                                if (stage.is_built_in) {
                                    return (
                                        <BuiltInStageRow
                                            key={stage.id}
                                            stage={stage}
                                        />
                                    );
                                }

                                const index = customStages.findIndex(
                                    (s) => s.id === stage.id,
                                );

                                return (
                                    <CustomStageRow
                                        key={stage.id}
                                        stage={stage}
                                        canMoveUp={index > 0}
                                        canMoveDown={
                                            index < customStages.length - 1
                                        }
                                        onMoveUp={() =>
                                            reorder(index, index - 1)
                                        }
                                        onMoveDown={() =>
                                            reorder(index, index + 1)
                                        }
                                    />
                                );
                            })}
                        </ul>

                        {can('job-stages.create') && (
                            <form
                                onSubmit={add}
                                className="border-t border-gray-100 bg-gray-50 px-6 py-4"
                            >
                                <div className="flex flex-wrap items-start gap-2">
                                    <TextInput
                                        className="w-64"
                                        placeholder="New stage, e.g. Coating"
                                        maxLength={50}
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        required
                                    />
                                    <PrimaryButton disabled={processing}>
                                        Add stage
                                    </PrimaryButton>
                                </div>
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                                <p className="mt-2 text-xs text-gray-500">
                                    Added just before Shipped - reorder it with
                                    the arrows once it's added.
                                </p>
                            </form>
                        )}
                    </section>

                    <SecondaryButton
                        onClick={() => router.visit(route('jobs.index'))}
                    >
                        Back to job board
                    </SecondaryButton>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
