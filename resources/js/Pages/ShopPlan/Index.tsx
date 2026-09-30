import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatCents } from '@/lib/money';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';

interface PlanRow {
    key: string;
    name: string;
    user_limit: number | null;
    monthly_price_cents: number;
    features: string[];
    upgradeable: boolean;
}

export default function Index({
    plans,
    currentPlanKey,
}: PageProps<{ plans: PlanRow[]; currentPlanKey: string | null }>) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Plan
                </h2>
            }
        >
            <Head title="Plan" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
                    <p className="px-4 text-sm text-gray-600 sm:px-0">
                        Upgrading unlocks editing for anything your shop
                        created during its trial. Plans aren't charged
                        automatically yet - no payment processor is connected.
                    </p>

                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        {plans.map((plan) => (
                            <div
                                key={plan.key}
                                className={`flex flex-col bg-white p-6 shadow sm:rounded-lg ${
                                    plan.key === currentPlanKey
                                        ? 'ring-2 ring-indigo-500'
                                        : ''
                                }`}
                            >
                                <div className="text-lg font-semibold text-gray-900">
                                    {plan.name}
                                </div>
                                <div className="mt-1 text-sm text-gray-500">
                                    {formatCents(plan.monthly_price_cents)} / mo
                                    ·{' '}
                                    {plan.user_limit === null
                                        ? 'unlimited users'
                                        : `${plan.user_limit} user${plan.user_limit === 1 ? '' : 's'}`}
                                </div>
                                <ul className="mt-4 flex-1 space-y-1 text-sm text-gray-700">
                                    {plan.features.length === 0 ? (
                                        <li className="text-gray-400">
                                            View-only after the trial
                                        </li>
                                    ) : (
                                        plan.features.map((feature) => (
                                            <li key={feature}>✓ {feature}</li>
                                        ))
                                    )}
                                </ul>
                                <div className="mt-4">
                                    {plan.key === currentPlanKey ? (
                                        <span className="text-sm font-medium text-indigo-700">
                                            Current plan
                                        </span>
                                    ) : plan.upgradeable ? (
                                        <PrimaryButton
                                            onClick={() =>
                                                router.post(
                                                    route('shop.plan.update'),
                                                    { plan: plan.key },
                                                )
                                            }
                                        >
                                            Upgrade to {plan.name}
                                        </PrimaryButton>
                                    ) : null}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
