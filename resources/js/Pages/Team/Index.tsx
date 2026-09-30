import FormErrorSummary from '@/Components/FormErrorSummary';
import PageHelp from '@/Components/PageHelp';
import PrimaryButton from '@/Components/PrimaryButton';
import SearchableSelect from '@/Components/SearchableSelect';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Member {
    id: number;
    name: string;
    email: string;
    role: string | null;
    is_owner: boolean;
    can_edit_access: boolean;
    hourly_rate: number | null;
}

interface PendingInvitation {
    email: string;
    role: string;
    expires_at: string;
}

function InviteForm({ roles }: { roles: string[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        role: roles[roles.length - 1] ?? 'Staff',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('team.invite'), {
            preserveScroll: true,
            onSuccess: () => reset('email'),
        });
    };

    return (
        <div className="bg-white p-6 shadow-sm sm:rounded-lg">
            <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-500">
                Invite by email
            </h3>
            <form onSubmit={submit} className="mt-4">
                <FormErrorSummary errors={errors} />
                <div className="flex flex-wrap items-start gap-2">
                    <TextInput
                        type="email"
                        placeholder="email@example.com"
                        className="w-64"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                    />
                    <SearchableSelect
                        wrapperClassName="w-32"
                        className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.role}
                        onChange={(value) => setData('role', value)}
                        options={roles.map((role) => ({ value: role, label: role }))}
                    />
                    <PrimaryButton disabled={processing}>
                        Send invite
                    </PrimaryButton>
                </div>
            </form>
        </div>
    );
}

function PendingInvitations({
    invitations,
}: {
    invitations: PendingInvitation[];
}) {
    if (invitations.length === 0) {
        return null;
    }

    return (
        <div className="bg-white p-6 shadow-sm sm:rounded-lg">
            <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-500">
                Pending invitations
            </h3>
            <ul className="mt-3 divide-y divide-gray-100">
                {invitations.map((invitation) => (
                    <li
                        key={invitation.email}
                        className="flex items-center justify-between py-2 text-sm"
                    >
                        <span className="text-gray-900">
                            {invitation.email}
                        </span>
                        <span className="text-gray-500">{invitation.role}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function RateCell({
    member,
    canManageTeam,
}: {
    member: Member;
    canManageTeam: boolean;
}) {
    const [rate, setRate] = useState(member.hourly_rate?.toString() ?? '');
    const [saved, setSaved] = useState(false);

    if (!canManageTeam) {
        return (
            <span className="text-sm text-gray-500">
                {member.hourly_rate !== null
                    ? `$${member.hourly_rate.toFixed(2)}/hr`
                    : '—'}
            </span>
        );
    }

    const save = () => {
        router.patch(
            route('team.update', member.id),
            { hourly_rate: rate === '' ? null : rate },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSaved(true);
                    setTimeout(() => setSaved(false), 1500);
                },
            },
        );
    };

    return (
        <div className="flex items-center gap-2">
            <TextInput
                type="number"
                step="0.01"
                min="0"
                className="w-24"
                value={rate}
                onChange={(e) => setRate(e.target.value)}
            />
            <button
                type="button"
                onClick={save}
                className="text-sm text-indigo-600 hover:text-indigo-900"
            >
                Save
            </button>
            {saved && <span className="text-sm text-green-600">Saved</span>}
        </div>
    );
}

export default function Index({
    members,
    canManageTeam,
    canManageAccess,
    canInvite,
    invitableRoles,
    pendingInvitations,
}: PageProps<{
    members: Member[];
    canManageTeam: boolean;
    canManageAccess: boolean;
    canInvite: boolean;
    invitableRoles: string[];
    pendingInvitations: PendingInvitation[];
}>) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Team
                    </h2>
                    <PageHelp>
                        <p>
                            Everyone with access to this shop - their role, and
                            (if you can manage the team) their hourly rate for
                            labor line items.
                        </p>
                        <p>
                            Invite new teammates by email from here. What each
                            Staff member can additionally do beyond their role's
                            defaults is set on the Roles &amp; permissions page.
                        </p>
                    </PageHelp>
                </div>
            }
        >
            <Head title="Team" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {canInvite && <InviteForm roles={invitableRoles} />}
                    {canInvite && (
                        <PendingInvitations invitations={pendingInvitations} />
                    )}

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Name
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Email
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Role
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Hourly rate
                                    </th>
                                    {canManageAccess && (
                                        <th className="px-6 py-3" />
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 bg-white">
                                {members.map((member) => (
                                    <tr key={member.id}>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                            {member.name}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                            {member.email}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                            {member.role}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4">
                                            <RateCell
                                                member={member}
                                                canManageTeam={canManageTeam}
                                            />
                                        </td>
                                        {canManageAccess && (
                                            <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                                {member.is_owner ? (
                                                    <span className="text-gray-400">
                                                        Full access
                                                    </span>
                                                ) : member.can_edit_access ? (
                                                    <Link
                                                        href={route(
                                                            'team.access.edit',
                                                            member.id,
                                                        )}
                                                        className="text-indigo-600 hover:text-indigo-900"
                                                    >
                                                        Role & access
                                                    </Link>
                                                ) : null}
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
