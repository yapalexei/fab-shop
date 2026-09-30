import PageHelp from '@/Components/PageHelp';
import Pagination, { PaginationLink } from '@/Components/Pagination';
import SearchableSelect from '@/Components/SearchableSelect';
import SecondaryButton from '@/Components/SecondaryButton';
import ShopManageDialog, { ManagedShop } from '@/Components/ShopManageDialog';
import TextInput from '@/Components/TextInput';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout';
import { formatCents } from '@/lib/money';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

type ShopRow = ManagedShop & {
    owner_email: string;
    plan_name: string | null;
    is_onboarded: boolean;
    created_at: string;
};

interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    total: number;
}

interface PlanOption {
    id: number;
    name: string;
}

interface Revenue {
    active_mrr_cents: number;
    trial_potential_mrr_cents: number;
}

interface Filters {
    search: string | null;
    status: string | null;
    plan_id: string | null;
    trial_within_days: string | null;
    subscription: string | null;
}

const MS_PER_DAY = 86_400_000;

function daysUntil(iso: string): number {
    return Math.ceil((new Date(iso).getTime() - Date.now()) / MS_PER_DAY);
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

/**
 * subscription_renews_at is a platform admin's own manually-set "paid
 * through" record (see Shop::isSubscriptionOverdue()'s doc) - there's no
 * real payment processor behind this, so "not tracked" is a distinct,
 * neutral state from "overdue," not an error.
 */
function SubscriptionBadge({ shop }: { shop: ShopRow }) {
    if (!shop.subscription_renews_at) {
        return (
            <span className="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500">
                Not tracked
            </span>
        );
    }

    if (shop.subscription_overdue) {
        return (
            <span className="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">
                Overdue
            </span>
        );
    }

    return (
        <span className="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
            Paid
        </span>
    );
}

export default function Index({
    shops,
    plans,
    revenue,
    filters,
}: PageProps<{
    shops: Paginated<ShopRow>;
    plans: PlanOption[];
    revenue: Revenue;
    filters: Filters;
}>) {
    // The id, not a snapshot of the shop object - every mutation inside the
    // dialog reloads this page's props via Inertia's own back()-redirect
    // response, and deriving the managed shop fresh from that on every
    // render is what makes the dialog show the result of its own action
    // immediately instead of stale data until it's closed and reopened.
    const [managingShopId, setManagingShopId] = useState<number | null>(null);
    const managingShop =
        shops.data.find((shop) => shop.id === managingShopId) ?? null;

    // Local filter state, synced to the URL - search is debounced so typing
    // doesn't fire a request per keystroke, the rest apply immediately.
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [planId, setPlanId] = useState(filters.plan_id ?? '');
    const [trialWithinDays, setTrialWithinDays] = useState(
        filters.trial_within_days ?? '',
    );
    const [subscription, setSubscription] = useState(
        filters.subscription ?? '',
    );
    const isFirstRender = useRef(true);

    const applyFilters = (next: {
        search?: string;
        status?: string;
        plan_id?: string;
        trial_within_days?: string;
        subscription?: string;
    }) => {
        router.get(
            route('platform-admin.index'),
            {
                search,
                status,
                plan_id: planId,
                trial_within_days: trialWithinDays,
                subscription,
                ...next,
            },
            { preserveState: true, replace: true },
        );
    };

    // Debounce search only - the select/number filters call applyFilters
    // directly on change instead, since there's no "typing" to wait out.
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const timer = setTimeout(() => applyFilters({ search }), 300);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const hasShops = shops.total > 0;

    return (
        <PlatformAdminLayout
            header={
                <div className="flex items-center gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Shop directory
                    </h2>
                    {hasShops && (
                        <PageHelp>
                            <p>
                                Every shop on the platform - internal,
                                cross-tenant view, not something a shop's own
                                users ever see.
                            </p>
                            <p>
                                Click Manage on a shop to suspend it, override
                                its plan or seat limit, extend or end its trial,
                                or mark it paid. Actual collected revenue
                                (Stripe/Square/Plaid) isn't wired up yet - the
                                MRR figures above are computed, not real billing
                                data, and subscription status is a manual record
                                you set yourself.
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
                            value={shops.total.toString()}
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

                    <div className="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm">
                        <div className="min-w-[12rem] flex-1">
                            <label className="block text-xs font-medium text-gray-500">
                                Search
                            </label>
                            <TextInput
                                className="mt-1 w-full"
                                placeholder="Shop name or owner email"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500">
                                Status
                            </label>
                            <SearchableSelect
                                wrapperClassName="mt-1 w-32"
                                className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={status}
                                onChange={(value) => {
                                    setStatus(value);
                                    applyFilters({ status: value });
                                }}
                                emptyLabel="Any"
                                options={[
                                    { value: 'trial', label: 'Trial' },
                                    { value: 'active', label: 'Active' },
                                    { value: 'onboarding', label: 'Onboarding' },
                                    { value: 'suspended', label: 'Suspended' },
                                ]}
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500">
                                Plan
                            </label>
                            <SearchableSelect
                                wrapperClassName="mt-1 w-32"
                                className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={planId}
                                onChange={(value) => {
                                    setPlanId(value);
                                    applyFilters({ plan_id: value });
                                }}
                                emptyLabel="Any"
                                options={[
                                    { value: 'none', label: 'No plan' },
                                    ...plans.map((plan) => ({
                                        value: String(plan.id),
                                        label: plan.name,
                                    })),
                                ]}
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500">
                                Trial ends within
                            </label>
                            <TextInput
                                type="number"
                                min="0"
                                className="mt-1 w-20"
                                placeholder="days"
                                value={trialWithinDays}
                                onChange={(e) => {
                                    setTrialWithinDays(e.target.value);
                                    applyFilters({
                                        trial_within_days: e.target.value,
                                    });
                                }}
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500">
                                Subscription
                            </label>
                            <SearchableSelect
                                wrapperClassName="mt-1 w-32"
                                className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={subscription}
                                onChange={(value) => {
                                    setSubscription(value);
                                    applyFilters({ subscription: value });
                                }}
                                emptyLabel="Any"
                                options={[
                                    { value: 'paid', label: 'Paid' },
                                    { value: 'overdue', label: 'Overdue' },
                                    { value: 'not_tracked', label: 'Not tracked' },
                                ]}
                            />
                        </div>
                        {(search ||
                            status ||
                            planId ||
                            trialWithinDays ||
                            subscription) && (
                            <SecondaryButton
                                onClick={() => {
                                    setSearch('');
                                    setStatus('');
                                    setPlanId('');
                                    setTrialWithinDays('');
                                    setSubscription('');
                                    applyFilters({
                                        search: '',
                                        status: '',
                                        plan_id: '',
                                        trial_within_days: '',
                                        subscription: '',
                                    });
                                }}
                            >
                                Clear filters
                            </SecondaryButton>
                        )}
                    </div>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        {shops.data.length === 0 ? (
                            <div className="p-6">
                                <PageHelp empty>
                                    <p>
                                        {hasShops
                                            ? 'No shops match these filters.'
                                            : "No shops yet. This is every shop on the platform - internal, cross-tenant view, not something a shop's own users ever see."}
                                    </p>
                                    <p>
                                        Suspend a shop, override its plan, or
                                        check potential revenue from here once
                                        shops start signing up.
                                    </p>
                                </PageHelp>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
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
                                                Subscription
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                Created
                                            </th>
                                            <th className="px-6 py-3" />
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 bg-white">
                                        {shops.data.map((shop) => (
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
                                                    {shop.trial_ends_at && (
                                                        <div className="mt-1 text-xs text-gray-400">
                                                            {shop.on_trial
                                                                ? `${daysUntil(shop.trial_ends_at)}d left`
                                                                : `ended ${new Date(shop.trial_ends_at).toLocaleDateString()}`}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                    {shop.plan_name ??
                                                        'No plan'}
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                    {shop.member_count}
                                                    {shop.seat_limit !== null
                                                        ? ` / ${shop.seat_limit}`
                                                        : ''}
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4">
                                                    <SubscriptionBadge
                                                        shop={shop}
                                                    />
                                                    {shop.subscription_renews_at && (
                                                        <div className="mt-1 text-xs text-gray-400">
                                                            {shop.subscription_overdue
                                                                ? `since ${new Date(shop.subscription_renews_at).toLocaleDateString()}`
                                                                : `${daysUntil(shop.subscription_renews_at)}d left`}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                    {new Date(
                                                        shop.created_at,
                                                    ).toLocaleDateString()}
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                                    <SecondaryButton
                                                        onClick={() =>
                                                            setManagingShopId(
                                                                shop.id,
                                                            )
                                                        }
                                                    >
                                                        Manage
                                                    </SecondaryButton>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        <Pagination links={shops.links} />
                    </div>
                </div>
            </div>

            <ShopManageDialog
                shop={managingShop}
                plans={plans}
                onClose={() => setManagingShopId(null)}
            />
        </PlatformAdminLayout>
    );
}
