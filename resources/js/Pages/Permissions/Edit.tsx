import PrimaryButton from "@/Components/PrimaryButton";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { PageProps } from "@/types";
import { Head, Link, useForm } from "@inertiajs/react";
import { FormEventHandler } from "react";

export interface PermissionOption {
    value: string;
    label: string;
    description: string;
}

export type RoleGrants = Record<string, string[]>;

export default function Edit({
    permissions,
    grants,
}: PageProps<{ permissions: PermissionOption[]; grants: RoleGrants }>) {
    const roles = Object.keys(grants);
    const { data, setData, put, processing, isDirty } = useForm<{
        grants: RoleGrants;
    }>({ grants });

    const toggle = (role: string, permission: string) => {
        const current = data.grants[role] ?? [];
        setData("grants", {
            ...data.grants,
            [role]: current.includes(permission)
                ? current.filter((p) => p !== permission)
                : [...current, permission],
        });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route("shop.permissions.update"), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Roles & permissions
                </h2>
            }
        >
            <Head title="Roles & permissions" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    <form
                        onSubmit={submit}
                        className="overflow-hidden bg-white shadow sm:rounded-lg"
                    >
                        <p className="p-6 text-sm text-gray-500">
                            Choose what each role can do in this shop. The owner
                            can always do everything. To make an exception for
                            one person, open their{" "}
                            <Link
                                href={route("team.index")}
                                className="text-indigo-600 hover:text-indigo-900"
                            >
                                team page
                            </Link>{" "}
                            and change their access there.
                        </p>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Permission
                                        </th>
                                        <th className="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Owner
                                        </th>
                                        {roles.map((role) => (
                                            <th
                                                key={role}
                                                className="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500"
                                            >
                                                {role}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {permissions.map((permission) => (
                                        <tr key={permission.value}>
                                            <td className="px-6 py-4">
                                                <p className="text-sm font-medium text-gray-900">
                                                    {permission.label}
                                                </p>
                                                <p className="text-xs text-gray-500">
                                                    {permission.description}
                                                </p>
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                <input
                                                    type="checkbox"
                                                    checked
                                                    disabled
                                                    aria-label={`Owner: ${permission.label}`}
                                                    className="rounded border-gray-300 text-gray-400"
                                                />
                                            </td>
                                            {roles.map((role) => (
                                                <td
                                                    key={role}
                                                    className="px-6 py-4 text-center"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        aria-label={`${role}: ${permission.label}`}
                                                        checked={(
                                                            data.grants[role] ??
                                                            []
                                                        ).includes(
                                                            permission.value,
                                                        )}
                                                        onChange={() =>
                                                            toggle(
                                                                role,
                                                                permission.value,
                                                            )
                                                        }
                                                        className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                    />
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex justify-end border-t border-gray-100 p-6">
                            <PrimaryButton disabled={processing || !isDirty}>
                                Save
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
