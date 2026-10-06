import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SearchableSelect from '@/Components/SearchableSelect';
import TextInput from '@/Components/TextInput';
import { useShopDateTime } from '@/lib/datetime';
import { useCan } from '@/lib/permissions';
import { Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';

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
        <Badge variant={wasQualified ? 'success' : 'danger'}>
            {wasQualified ? 'Certified' : 'Not certified'}
        </Badge>
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
        <Card className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-500">
                    Qualification records
                </h3>
                {canEdit && (
                    <button
                        type="button"
                        onClick={() => setShowForm((v) => !v)}
                        className="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-accent"
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
                        <SearchableSelect
                            className="rounded-md border-gray-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                            value={String(data.procedure_id)}
                            onChange={(value) =>
                                setData('procedure_id', value === '' ? '' : Number(value))
                            }
                            emptyLabel="No specific procedure"
                            options={procedures.map((procedure) => ({
                                value: String(procedure.id),
                                label: procedure.name,
                            }))}
                            emptyState={
                                <>
                                    No procedures yet. <Link href={route('procedures.create')} className="font-medium underline">Create a procedure</Link> to
                                    record work against it.
                                </>
                            }
                        />
                        <SearchableSelect
                            className="rounded-md border-gray-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                            value={String(data.performed_by)}
                            onChange={(value) =>
                                setData('performed_by', value === '' ? '' : Number(value))
                            }
                            emptyLabel="Who performed it"
                            required
                            options={members.map((member) => ({
                                value: String(member.id),
                                label: member.name,
                            }))}
                            emptyState={
                                <>
                                    No team members yet. <Link href={route('team.index')} className="font-medium underline">Invite someone</Link> first.
                                </>
                            }
                        />
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
                        className="block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
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
        </Card>
    );
}
