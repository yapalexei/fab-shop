import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { useShopDateTime } from '@/lib/datetime';
import { useCan } from '@/lib/permissions';
import { router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export interface QualificationRecord {
    id: number;
    procedure_id: number | null;
    procedure: string | null;
    performed_by: string | null;
    description: string;
    performed_at: string;
    was_qualified: boolean | null;
    notes: string | null;
    recorded_by: string | null;
    created_at: string;
}

export interface QualificationOption {
    id: number;
    name: string;
}

function QualifiedBadge({ wasQualified }: { wasQualified: boolean | null }) {
    if (wasQualified === null) {
        return null;
    }

    return (
        <span
            className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${
                wasQualified
                    ? 'bg-green-100 text-green-800'
                    : 'bg-red-100 text-red-800'
            }`}
        >
            {wasQualified ? 'Certified' : 'Not certified'}
        </span>
    );
}

export default function JobQualificationRecords({
    jobId,
    readOnly = false,
    records,
    procedures,
    members,
}: {
    jobId: number;
    readOnly?: boolean;
    records: QualificationRecord[];
    procedures: QualificationOption[];
    members: QualificationOption[];
}) {
    const canEdit = useCan()('jobs.update') && !readOnly;
    const formatDateTime = useShopDateTime();
    const [showForm, setShowForm] = useState(false);

    const today = new Date().toISOString().slice(0, 10);

    const { data, setData, post, processing, errors, reset } = useForm({
        procedure_id: '' as number | '',
        performed_by: '' as number | '',
        description: '',
        performed_at: today,
        notes: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('jobs.qualification-records.store', jobId), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setData('performed_at', today);
                setShowForm(false);
            },
        });
    };

    const destroy = (recordId: number) => {
        if (!confirm('Remove this qualification record?')) return;

        router.delete(
            route('jobs.qualification-records.destroy', [jobId, recordId]),
            { preserveScroll: true },
        );
    };

    return (
        <div className="bg-white p-6 shadow sm:rounded-lg">
            <div className="mb-4 flex items-center justify-between">
                <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-500">
                    Qualification records
                </h3>
                {canEdit && (
                    <button
                        type="button"
                        onClick={() => setShowForm((v) => !v)}
                        className="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50"
                    >
                        {showForm ? 'Cancel' : 'Log record'}
                    </button>
                )}
            </div>

            {showForm && (
                <form
                    onSubmit={submit}
                    className="space-y-3 border-b border-gray-100 pb-4"
                >
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <select
                            className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.procedure_id}
                            onChange={(e) =>
                                setData(
                                    'procedure_id',
                                    e.target.value === ''
                                        ? ''
                                        : Number(e.target.value),
                                )
                            }
                        >
                            <option value="">No specific procedure</option>
                            {procedures.map((procedure) => (
                                <option key={procedure.id} value={procedure.id}>
                                    {procedure.name}
                                </option>
                            ))}
                        </select>
                        <select
                            className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.performed_by}
                            onChange={(e) =>
                                setData(
                                    'performed_by',
                                    e.target.value === ''
                                        ? ''
                                        : Number(e.target.value),
                                )
                            }
                            required
                        >
                            <option value="">Who performed it</option>
                            {members.map((member) => (
                                <option key={member.id} value={member.id}>
                                    {member.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <TextInput
                            placeholder="Description - e.g. root pass on main frame joint"
                            className="block w-full"
                            value={data.description}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                            required
                        />
                        <InputError
                            message={errors.description}
                            className="mt-1"
                        />
                    </div>

                    <div>
                        <TextInput
                            type="date"
                            className="block w-full sm:w-56"
                            value={data.performed_at}
                            onChange={(e) =>
                                setData('performed_at', e.target.value)
                            }
                            required
                        />
                        <InputError
                            message={errors.performed_at}
                            className="mt-1"
                        />
                    </div>

                    <textarea
                        className="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        rows={2}
                        placeholder="Notes (optional)"
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                    />

                    <div className="flex justify-end">
                        <PrimaryButton disabled={processing}>
                            Log entry
                        </PrimaryButton>
                    </div>
                </form>
            )}

            <div className="mt-4">
                {records.length === 0 ? (
                    <p className="text-sm text-gray-500">Nothing logged yet.</p>
                ) : (
                    <ul className="divide-y divide-gray-100">
                        {records.map((record) => (
                            <li
                                key={record.id}
                                className="flex items-start justify-between gap-3 py-2 text-sm"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="flex flex-wrap items-center gap-2 text-gray-900">
                                        <QualifiedBadge
                                            wasQualified={record.was_qualified}
                                        />
                                        {record.description}
                                        {record.procedure && (
                                            <span className="text-xs text-gray-400">
                                                ({record.procedure})
                                            </span>
                                        )}
                                    </p>
                                    {record.notes && (
                                        <p className="text-xs text-gray-500">
                                            {record.notes}
                                        </p>
                                    )}
                                    <p className="text-xs text-gray-400">
                                        {record.performed_by ?? 'Unknown'} on{' '}
                                        {record.performed_at} - logged by{' '}
                                        {record.recorded_by ?? 'System'} (
                                        {formatDateTime(record.created_at)})
                                    </p>
                                </div>
                                {canEdit && (
                                    <button
                                        type="button"
                                        onClick={() => destroy(record.id)}
                                        className="shrink-0 text-gray-400 hover:text-red-600"
                                        aria-label="Remove entry"
                                    >
                                        &times;
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}
