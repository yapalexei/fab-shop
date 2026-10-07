import PermissionGrid, {
    actionLabel,
    isDestructive,
    ResourceOption,
} from '@/Components/PermissionGrid';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { BackLink } from '@/Components/SubPageLinks';
import { Checkbox } from '@/Components/ui/checkbox';
import { cn } from '@/lib/utils';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export type RoleGrants = Record<string, string[]>;

export default function Edit({
    resources,
    grants,
    defaults,
}: PageProps<{
    resources: ResourceOption[];
    grants: RoleGrants;
    defaults: RoleGrants;
}>) {
    const roles = Object.keys(grants);
    const [role, setRole] = useState(roles[0]);
    const { data, setData, put, processing, isDirty } = useForm<{
        grants: RoleGrants;
    }>({ grants });

    const granted = data.grants[role] ?? [];

    // Mirrors the server rule that any action on a resource implies seeing
    // it: ticking Edit also ticks View, and unticking View unticks the rest.
    const toggle = (permission: string) => {
        const [resource, action] = splitPermission(permission);
        const view = `${resource}.view`;
        const hasView = resources
            .find((r) => r.value === resource)
            ?.actions.some((a) => a.action === 'view');
        let next: string[];

        if (granted.includes(permission)) {
            next =
                action === 'view'
                    ? granted.filter((p) => !p.startsWith(`${resource}.`))
                    : granted.filter((p) => p !== permission);
        } else {
            next = [...granted, permission];
            if (hasView && action !== 'view' && !next.includes(view)) {
                next.push(view);
            }
        }

        setData('grants', { ...data.grants, [role]: next });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('shop.permissions.update'), { preserveScroll: true });
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
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <BackLink
                        href={route('shop.security.index')}
                        label="Security"
                    />
                    <form
                        onSubmit={submit}
                        className="space-y-6 bg-white p-4 shadow-sm sm:rounded-lg sm:p-8"
                    >
                        <p className="text-sm text-gray-500">
                            What each role can do in this shop. The owner can
                            always do everything. To make an exception for one
                            person, open them from the{' '}
                            <Link
                                href={route('team.index')}
                                className="text-indigo-600 hover:underline"
                            >
                                team page
                            </Link>
                            . Actions in{' '}
                            <span className="text-red-600">red</span> delete or
                            permanently change data.
                        </p>

                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div
                                role="tablist"
                                className="inline-flex rounded-md shadow-xs"
                            >
                                {roles.map((name, i) => (
                                    <button
                                        key={name}
                                        type="button"
                                        role="tab"
                                        aria-selected={role === name}
                                        onClick={() => setRole(name)}
                                        className={`border px-4 py-2 text-sm font-medium ${i === 0 ? 'rounded-s-md' : '-ms-px'} ${i === roles.length - 1 ? 'rounded-e-md' : ''} ${role === name ? 'z-10 border-indigo-600 bg-indigo-600 text-white hover:bg-primary-hover' : 'border-gray-300 bg-white text-gray-700 hover:bg-accent hover:text-foreground'}`}
                                    >
                                        {name}
                                        <span className="ms-2 text-xs opacity-75">
                                            {(data.grants[name] ?? []).length}
                                        </span>
                                    </button>
                                ))}
                            </div>
                            <SecondaryButton
                                onClick={() =>
                                    setData('grants', {
                                        ...data.grants,
                                        [role]: defaults[role] ?? [],
                                    })
                                }
                            >
                                Reset {role} to defaults
                            </SecondaryButton>
                        </div>

                        <PermissionGrid
                            resources={resources}
                            renderCell={(action) => {
                                const checked = granted.includes(
                                    action.permission,
                                );

                                return (
                                    <label
                                        className={cn(
                                            'inline-flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm',
                                            checked
                                                ? isDestructive(action.action)
                                                    ? 'border-destructive/60 bg-destructive/10 text-foreground'
                                                    : 'border-primary/60 bg-primary/10 text-foreground'
                                                : 'border-border text-muted-foreground hover:bg-accent',
                                        )}
                                    >
                                        <Checkbox
                                            className={cn(
                                                isDestructive(action.action) &&
                                                    'data-[state=checked]:border-destructive data-[state=checked]:bg-destructive',
                                            )}
                                            checked={checked}
                                            onCheckedChange={() =>
                                                toggle(action.permission)
                                            }
                                            aria-label={`${role}: ${action.permission}`}
                                        />
                                        {actionLabel(action.action)}
                                    </label>
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
                                Save
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function splitPermission(permission: string): [string, string] {
    const dot = permission.indexOf('.');

    return [permission.slice(0, dot), permission.slice(dot + 1)];
}
