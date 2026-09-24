import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import PrimaryButton from "@/Components/PrimaryButton";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { PermissionOption, RoleGrants } from "@/Pages/Permissions/Edit";
import { PageProps } from "@/types";
import { Head, Link, useForm } from "@inertiajs/react";
import { FormEventHandler } from "react";

interface Member {
    id: number;
    name: string;
    email: string;
    role: string | null;
}

type Override = boolean | null;

const choices: { value: Override; label: string }[] = [
    { value: null, label: "Role default" },
    { value: true, label: "Allow" },
    { value: false, label: "Deny" },
];

export default function Access({
    member,
    roles,
    permissions,
    roleGrants,
    overrides,
}: PageProps<{
    member: Member;
    roles: string[];
    permissions: PermissionOption[];
    roleGrants: RoleGrants;
    overrides: Record<string, boolean>;
}>) {
    const { data, setData, put, processing, errors, isDirty } = useForm<{
        role: string;
        overrides: Record<string, Override>;
    }>({
        role:
            member.role && roles.includes(member.role) ? member.role : "Staff",
        overrides: Object.fromEntries(
            permissions.map((p) => [p.value, overrides[p.value] ?? null]),
        ),
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route("team.access.update", member.id), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    <Link
                        href={route("team.index")}
                        className="text-gray-500 hover:text-gray-700"
                    >
                        Team
                    </Link>{" "}
                    / {member.name}
                </h2>
            }
        >
            <Head title={`${member.name} - access`} />

            <div className="py-12">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    <form
                        onSubmit={submit}
                        className="space-y-8 bg-white p-4 shadow sm:rounded-lg sm:p-8"
                    >
                        <div>
                            <p className="text-sm text-gray-500">
                                {member.email}
                            </p>
                            <div className="mt-4 max-w-xs">
                                <InputLabel
                                    htmlFor="role"
                                    value="Role in this shop"
                                />
                                <select
                                    id="role"
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={data.role}
                                    onChange={(e) =>
                                        setData("role", e.target.value)
                                    }
                                >
                                    {roles.map((role) => (
                                        <option key={role} value={role}>
                                            {role}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={errors.role}
                                    className="mt-2"
                                />
                            </div>
                        </div>

                        <div>
                            <h3 className="text-sm font-semibold text-gray-900">
                                Permissions
                            </h3>
                            <p className="mt-1 text-sm text-gray-500">
                                By default {member.name} gets whatever the{" "}
                                {data.role} role has (set on{" "}
                                <Link
                                    href={route("shop.permissions.edit")}
                                    className="text-indigo-600 hover:text-indigo-900"
                                >
                                    Roles & permissions
                                </Link>
                                ). Allow or deny here to make an exception for
                                just this person, in this shop only.
                            </p>

                            <ul className="mt-4 divide-y divide-gray-100 rounded-md border border-gray-200">
                                {permissions.map((permission) => {
                                    const roleHasIt = (
                                        roleGrants[data.role] ?? []
                                    ).includes(permission.value);
                                    const override =
                                        data.overrides[permission.value];
                                    const effective = override ?? roleHasIt;

                                    return (
                                        <li
                                            key={permission.value}
                                            className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div>
                                                <p className="text-sm font-medium text-gray-900">
                                                    {permission.label}
                                                    <span
                                                        className={`ms-2 inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${effective ? "bg-green-100 text-green-800" : "bg-gray-100 text-gray-600"}`}
                                                    >
                                                        {effective
                                                            ? "Allowed"
                                                            : "Not allowed"}
                                                    </span>
                                                </p>
                                                <p className="text-xs text-gray-500">
                                                    {permission.description}
                                                </p>
                                            </div>

                                            <div
                                                role="radiogroup"
                                                aria-label={permission.label}
                                                className="inline-flex shrink-0 rounded-md shadow-sm"
                                            >
                                                {choices.map((choice, i) => {
                                                    const selected =
                                                        override ===
                                                        choice.value;

                                                    return (
                                                        <button
                                                            key={choice.label}
                                                            type="button"
                                                            role="radio"
                                                            aria-checked={
                                                                selected
                                                            }
                                                            onClick={() =>
                                                                setData(
                                                                    "overrides",
                                                                    {
                                                                        ...data.overrides,
                                                                        [permission.value]:
                                                                            choice.value,
                                                                    },
                                                                )
                                                            }
                                                            className={`border px-3 py-1.5 text-xs font-medium ${i === 0 ? "rounded-s-md" : "-ms-px"} ${i === choices.length - 1 ? "rounded-e-md" : ""} ${selected ? "z-10 border-indigo-600 bg-indigo-600 text-white" : "border-gray-300 bg-white text-gray-700 hover:bg-gray-50"}`}
                                                        >
                                                            {choice.value ===
                                                            null
                                                                ? `${choice.label} (${roleHasIt ? "yes" : "no"})`
                                                                : choice.label}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>

                        <div className="flex justify-end">
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
