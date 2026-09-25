import DangerButton from '@/Components/DangerButton';
import PageHelp from '@/Components/PageHelp';
import SecondaryButton from '@/Components/SecondaryButton';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout';
import { formatCents } from '@/lib/money';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';

interface ShopRow {
    id: number;
    name: string;
    owner_email: string;
    plan_id: number | null;
    plan_name: string | null;
    on_trial: boolean;
    trial_ends_at: string | null;
    is_onboarded: boolean;
    is_suspended: boolean;
    member_count: number;
    seat_limit: number | null;
    created_at: string;
}

interface PlanOption {
    id: number;
    name: string;
}

interface Revenue {
    active_mrr_cents: number;
    trial_potential_mrr_cents: number;
}

function StatCard({
    label,
    value,
    hint,
}: {
    label: string;
    value: string;
    hint?: string;
}) {
    return (
        <div className="rounded-lg bg-white p-6 shadow-sm">
            <div className="text-sm font-medium text-gray-500">{label}</div>
            <div className="mt-1 text-2xl font-semibold text-gray-900">
                {value}
            </div>
            {hint && <div className="mt-1 text-xs text-gray-400">{hint}</div>}
        </div>
    );
}

function StatusBadge({ shop }: { shop: ShopRow }) {
    if (shop.is_suspended) {
        return (
            <span className="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">
                Suspended
            </span>
        );
    }

    if (!shop.is_onboarded) {
        return (
            <span className="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">
                Onboarding
            </span>
        );
    }

    if (shop.on_trial) {
        return (
            <span className="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">
                Trial
            </span>
        );
    }

    return (
        <span className="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
            Active
        </span>
    );
}

export default function Index({
    shops,
    plans,
    revenue,
}: PageProps<{
    shops: ShopRow[];
    plans: PlanOption[];
    revenue: Revenue;
}>) {
    const toggleSuspend = (shop: ShopRow) => {
        if (shop.is_suspended) {
            router.post(
                route('platform-admin.shops.unsuspend', shop.id),
                {},
                { preserveScroll: true },
            );
            return;
        }

        if (
            confirm(
                `Suspend ${shop.name}? Its users will be locked out until you unsuspend it.`,
            )
        ) {
            router.post(
                route('platform-admin.shops.suspend', shop.id),
                {},
                { preserveScroll: true },
            );
        }
    };

    const changePlan = (shop: ShopRow, planId: string) => {
        router.patch(
            route('platform-admin.shops.plan', shop.id),
            { plan_id: planId === '' ? null : planId },
            { preserveScroll: true },
        );
    };

    return (
        <PlatformAdminLayout
            header={
                <div className="flex items-center gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Shop directory
                    </h2>
                    {shops.length > 0 && (
                        <PageHelp>
                            <p>
                                Every shop on the platform - internal,
                                cross-tenant view, not something a shop's own
                                users ever see.
                            </p>
                            <p>
                                Suspend a shop, override its plan, or check
                                potential revenue from here. Actual collected
                                revenue (Stripe/Square/Plaid) isn't wired up yet
                                - the MRR figures above are computed, not real
                                billing data.
                            </p>
                        </PageHelp>
                    )}
                </div>
            }
        >
            <Head title="Platform admin" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <StatCard
                            label="Shops"
                            value={shops.length.toString()}
                        />
                        <StatCard
                            label="Potential MRR (active)"
                            value={formatCents(revenue.active_mrr_cents)}
                            hint="Post-trial shops on a paid plan, not suspended - theoretical only, not tied to Stripe/Square/Plaid."
                        />
                        <StatCard
                            label="Potential MRR (trialing)"
                            value={formatCents(
                                revenue.trial_potential_mrr_cents,
                            )}
                            hint="What's on the table if every current trial converts."
                        />
                    </div>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        {shops.length === 0 ? (
                            <div className="p-6">
                                <PageHelp empty>
                                    <p>
                                        No shops yet. This is every shop on the
                                        platform - internal, cross-tenant view,
                                        not something a shop's own users ever
                                        see.
                                    </p>
                                    <p>
                                        Suspend a shop, override its plan, or
                                        check potential revenue from here once
                                        shops start signing up.
                                    </p>
                                </PageHelp>
                            </div>
                        ) : (
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Shop
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Status
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Plan
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Seats
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Created
                                        </th>
                                        <th className="px-6 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 bg-white">
                                    {shops.map((shop) => (
                                        <tr key={shop.id}>
                                            <td className="whitespace-nowrap px-6 py-4">
                                                <div className="text-sm font-medium text-gray-900">
                                                    {shop.name}
                                                </div>
                                                <div className="text-sm text-gray-500">
                                                    {shop.owner_email}
                                                </div>
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4">
                                                <StatusBadge shop={shop} />
                                                {shop.on_trial &&
                                                    shop.trial_ends_at && (
                                                        <div className="mt-1 text-xs text-gray-400">
                                                            ends{' '}
                                                            {new Date(
                                                                shop.trial_ends_at,
                                                            ).toLocaleDateString()}
                                                        </div>
                                                    )}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                <select
                                                    className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    value={shop.plan_id ?? ''}
                                                    onChange={(e) =>
                                                        changePlan(
                                                            shop,
                                                            e.target.value,
                                                        )
                                                    }
                                                >
                                                    <option value="">
                                                        No plan
                                                    </option>
                                                    {plans.map((plan) => (
                                                        <option
                                                            key={plan.id}
                                                            value={plan.id}
                                                        >
                                                            {plan.name}
                                                        </option>
                                                    ))}
                                                </select>
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {shop.member_count}
                                                {shop.seat_limit !== null
                                                    ? ` / ${shop.seat_limit}`
                                                    : ''}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {new Date(
                                                    shop.created_at,
                                                ).toLocaleDateString()}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                                {shop.is_suspended ? (
                                                    <SecondaryButton
                                                        onClick={() =>
                                                            toggleSuspend(shop)
                                                        }
                                                    >
                                                        Unsuspend
                                                    </SecondaryButton>
                                                ) : (
                                                    <DangerButton
                                                        onClick={() =>
                                                            toggleSuspend(shop)
                                                        }
                                                    >
                                                        Suspend
                                                    </DangerButton>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </div>
            </div>
        </PlatformAdminLayout>
    );
}
