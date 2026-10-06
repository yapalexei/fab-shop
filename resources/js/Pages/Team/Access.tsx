import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PermissionGrid, {
    actionLabel,
    isDestructive,
    ResourceOption,
} from '@/Components/PermissionGrid';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { RoleGrants } from '@/Pages/Permissions/Edit';
import { cn } from '@/lib/utils';
import { PageProps } from '@/types';
import SearchableSelect from '@/Components/SearchableSelect';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Member {
    id: number;
    name: string;
    email: string;
    role: string | null;
}

type Override = boolean | null;

export default function Access({
    member,
    roles,
    canChangeRole,
    resources,
    roleGrants,
    overrides,
    grantable,
}: PageProps<{
    member: Member;
    roles: string[];
    canChangeRole: boolean;
    resources: ResourceOption[];
    roleGrants: RoleGrants;
    overrides: Record<string, boolean>;
    grantable: string[];
}>) {
    const allPermissions = resources.flatMap((r) =>
        r.actions.map((a) => a.permission),
    );

    // Sent as a list, not a map: permission names contain dots, which the
    // server-side validator would read as nesting.
    const { data, setData, put, processing, errors, isDirty } = useForm<{
        role: string;
        overrides: { permission: string; value: Override }[];
    }>({
        role:
            member.role && roles.includes(member.role) ? member.role : 'Staff',
        overrides: allPermissions.map((permission) => ({
            permission,
            value: overrides[permission] ?? null,
        })),
    });

    const errorMap = errors as Record<string, string>;
    const roleHas = (permission: string) =>
        (roleGrants[data.role] ?? []).includes(permission);

    const setOverride = (permission: string, value: Override) =>
        setData(
            'overrides',
            data.overrides.map((row) =>
                row.permission === permission ? { ...row, value } : row,
            ),
        );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('team.access.update', member.id), { preserveScroll: true });
    };

    const exceptions = data.overrides.filter((row) => row.value !== null);

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    <Link
                        href={route('team.index')}
                        className="text-gray-500 hover:text-foreground"
                    >
                        Team
                    </Link>{' '}
                    / {member.name}
                </h2>
            }
        >
            <Head title={`${member.name} - access`} />

            <div className="py-12">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <form
                        onSubmit={submit}
                        className="space-y-6 bg-white p-4 shadow-sm sm:rounded-lg sm:p-8"
                    >
                        <div className="flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <p className="text-sm text-gray-500">
                                    {member.email}
                                </p>
                                <div className="mt-3 w-60">
                                    <InputLabel
                                        htmlFor="role"
                                        value="Role in this shop"
                                    />
                                    <SearchableSelect
                                        id="role"
                                        className="block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-50 disabled:text-gray-500"
                                        wrapperClassName="mt-1"
                                        value={data.role}
                                        disabled={!canChangeRole}
                                        onChange={(value) => setData('role', value)}
                                        options={roles.map((role) => ({ value: role, label: role }))}
                                    />
                                    {!canChangeRole && (
                                        <p className="mt-1 text-xs text-gray-500">
                                            Only the shop owner can change
                                            roles.
                                        </p>
                                    )}
                                    <InputError
                                        message={errors.role}
                                        className="mt-2"
                                    />
                                </div>
                            </div>
                            <p className="text-sm text-gray-500">
                                {exceptions.length === 0
                                    ? `Exactly the ${data.role} role's access.`
                                    : `${exceptions.length} exception${exceptions.length === 1 ? '' : 's'} to the ${data.role} role.`}
                            </p>
                        </div>

                        <p className="text-sm text-gray-500">
                            Each action follows the {data.role} role (set on{' '}
                            <Link
                                href={route('shop.permissions.edit')}
                                className="text-indigo-600 hover:underline"
                            >
                                Roles & permissions
                            </Link>
                            ) unless you allow or deny it here - just for{' '}
                            {member.name}, in this shop only.
                            {!canChangeRole &&
                                ' You can only allow access you have yourself.'}
                        </p>

                        <PermissionGrid
                            resources={resources}
                            renderCell={(action) => {
                                const index = data.overrides.findIndex(
                                    (row) =>
                                        row.permission === action.permission,
                                );
                                const override = data.overrides[index].value;
                                const inherited = roleHas(action.permission);
                                const effective = override ?? inherited;
                                const canAllow =
                                    grantable.includes(action.permission) ||
                                    overrides[action.permission] === true;
                                const error =
                                    errorMap[`overrides.${index}.value`];

                                return (
                                    <div
                                        className={cn(
                                            'rounded-md border px-2 py-1.5',
                                            effective
                                                ? isDestructive(action.action)
                                                    ? 'border-destructive/60 bg-destructive/10'
                                                    : 'border-primary/60 bg-primary/10'
                                                : 'border-border',
                                        )}
                                    >
                                        <label className="block text-xs font-medium text-gray-700">
                                            {actionLabel(action.action)}
                                            <SearchableSelect
                                                ariaLabel={`${member.name}: ${action.permission}`}
                                                wrapperClassName="mt-1"
                                                className="rounded-sm border-gray-300 py-1 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                                                value={
                                                    override === null ? 'inherit' : override ? 'allow' : 'deny'
                                                }
                                                onChange={(value) =>
                                                    setOverride(
                                                        action.permission,
                                                        value === 'inherit' ? null : value === 'allow',
                                                    )
                                                }
                                                options={[
                                                    {
                                                        value: 'inherit',
                                                        label: `Role default (${inherited ? 'yes' : 'no'})`,
                                                    },
                                                    { value: 'allow', label: 'Allow', disabled: !canAllow },
                                                    { value: 'deny', label: 'Deny' },
                                                ]}
                                            />
                                        </label>
                                        {error && (
                                            <p className="mt-1 max-w-40 text-xs text-red-600">
                                                {error}
                                            </p>
                                        )}
                                    </div>
                                );
                            }}
                        />

                        <div className="flex items-center justify-end gap-4">
                            {isDirty && (
                                <span className="text-sm text-gray-500">
                                    Unsaved changes
                                </span>
                            )}
                            <PrimaryButton disabled={processing || !isDirty}>
                                Save access
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
