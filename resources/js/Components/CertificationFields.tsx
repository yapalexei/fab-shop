import { Link } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import SearchableSelect from '@/Components/SearchableSelect';
import TextInput from '@/Components/TextInput';

export interface CertificationFormData {
    user_id: string;
    procedure_id: string;
    cert_number: string;
    issuing_body: string;
    issued_at: string;
    expires_at: string;
    notes: string;
    [key: string]: string;
}

export interface MemberOption {
    id: number;
    name: string;
}

export interface ProcedureOption {
    id: number;
    name: string;
}

export default function CertificationFields({
    data,
    setData,
    errors,
    members,
    procedures,
}: {
    data: CertificationFormData;
    setData: (field: keyof CertificationFormData, value: string) => void;
    errors: Partial<Record<keyof CertificationFormData, string>>;
    members: MemberOption[];
    procedures: ProcedureOption[];
}) {
    return (
        <>
            <div>
                <InputLabel htmlFor="user_id" value="Team member" />
                <SearchableSelect
                    id="user_id"
                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    wrapperClassName="mt-1"
                    value={String(data.user_id ?? '')}
                    onChange={(value) => setData('user_id', value)}
                    emptyLabel="Select a team member"
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
                <InputError message={errors.user_id} className="mt-2" />
            </div>

            <div className="mt-4">
                <InputLabel
                    htmlFor="procedure_id"
                    value="Procedure (optional)"
                />
                <SearchableSelect
                    id="procedure_id"
                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    wrapperClassName="mt-1"
                    value={String(data.procedure_id ?? '')}
                    onChange={(value) => setData('procedure_id', value)}
                    emptyLabel="Not tied to a specific procedure"
                    options={procedures.map((procedure) => ({
                        value: String(procedure.id),
                        label: procedure.name,
                    }))}
                    emptyState={
                        <>
                            No procedures yet. <Link href={route('procedures.create')} className="font-medium underline">Create a procedure</Link> to
                            tie a certification to one.
                        </>
                    }
                />
                <p className="mt-1 text-xs text-gray-500">
                    Lets job records check whether this person was actually
                    covered for that procedure.
                </p>
                <InputError message={errors.procedure_id} className="mt-2" />
            </div>

            <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel htmlFor="cert_number" value="Cert number" />
                    <TextInput
                        id="cert_number"
                        className="mt-1 block w-full"
                        value={data.cert_number}
                        onChange={(e) => setData('cert_number', e.target.value)}
                    />
                    <InputError message={errors.cert_number} className="mt-2" />
                </div>
                <div>
                    <InputLabel htmlFor="issuing_body" value="Issuing body" />
                    <TextInput
                        id="issuing_body"
                        className="mt-1 block w-full"
                        placeholder="AWS, CWB, OSHA..."
                        value={data.issuing_body}
                        onChange={(e) =>
                            setData('issuing_body', e.target.value)
                        }
                    />
                    <InputError
                        message={errors.issuing_body}
                        className="mt-2"
                    />
                </div>
            </div>

            <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel htmlFor="issued_at" value="Issued" />
                    <TextInput
                        id="issued_at"
                        type="date"
                        className="mt-1 block w-full"
                        value={data.issued_at}
                        onChange={(e) => setData('issued_at', e.target.value)}
                    />
                    <InputError message={errors.issued_at} className="mt-2" />
                </div>
                <div>
                    <InputLabel htmlFor="expires_at" value="Expires" />
                    <TextInput
                        id="expires_at"
                        type="date"
                        className="mt-1 block w-full"
                        value={data.expires_at}
                        onChange={(e) => setData('expires_at', e.target.value)}
                    />
                    <p className="mt-1 text-xs text-gray-500">
                        Leave blank if it doesn't expire.
                    </p>
                    <InputError message={errors.expires_at} className="mt-2" />
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
