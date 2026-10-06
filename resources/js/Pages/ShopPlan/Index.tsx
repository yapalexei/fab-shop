import BillingDisclosure, {
    BillingQuote,
} from '@/Components/BillingDisclosure';
import BillingIntervalToggle, {
    Interval,
} from '@/Components/BillingIntervalToggle';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useShopDateTime } from '@/lib/datetime';
import { formatMoney } from '@/lib/money';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface PlanRow {
    key: string;
    name: string;
    user_limit: number | null;
    monthly_price_cents: number;
    annual_price_cents: number;
    features: string[];
    upgradeable: boolean;
    quotes: Record<Interval, BillingQuote> | null;
}

interface Pending {
    plan: PlanRow;
    interval: Interval;
    quote: BillingQuote;
    /** Same plan, moving from monthly to annual - not a higher tier. */
    annualSwitch?: boolean;
}

export default function Index({
    plans,
    currentPlanKey,
    billingInterval,
    renewsAt,
    annualMonthsCharged,
    annualSwitch,
}: PageProps<{
    plans: PlanRow[];
    currentPlanKey: string | null;
    billingInterval: Interval;
    renewsAt: string | null;
    annualMonthsCharged: number;
    annualSwitch: BillingQuote | null;
}>) {
    const formatDate = useShopDateTime({ dateStyle: 'medium' });
    const [interval, setInterval] = useState<Interval>(billingInterval);
    const [pending, setPending] = useState<Pending | null>(null);
    const current = plans.find((plan) => plan.key === currentPlanKey);

    const confirm = () => {
        if (!pending) return;
        router.post(route('shop.plan.update'), {
            plan: pending.plan.key,
            interval: pending.interval,
        });
        setPending(null);
    };

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
                        Upgrading unlocks editing for anything your shop created
                        during its trial. Plans aren't charged automatically yet
                        - no payment processor is connected.
                    </p>

                    {renewsAt && (
                        <p className="px-4 text-sm text-gray-600 sm:px-0">
                            Your {billingInterval} billing period renews on{' '}
                            <strong>{formatDate(renewsAt)}</strong>.
                        </p>
                    )}

                    <div className="px-4 sm:px-0">
                        <BillingIntervalToggle
                            value={interval}
                            onChange={setInterval}
                            annualMonthsCharged={annualMonthsCharged}
                        />
                    </div>

                    {annualSwitch && current && (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-l-4 border-indigo-400 bg-indigo-50 p-4 shadow-sm sm:rounded-lg">
                            <p className="text-sm text-indigo-900">
                                Switch your {current.name} plan to annual
                                billing and pay{' '}
                                {formatMoney(current.annual_price_cents)}/year
                                instead of{' '}
                                {formatMoney(current.monthly_price_cents * 12)}.
                            </p>
                            <SecondaryButton
                                onClick={() =>
                                    setPending({
                                        plan: current,
                                        interval: 'annual',
                                        quote: annualSwitch,
                                        annualSwitch: true,
                                    })
                                }
                            >
                                Switch to annual
                            </SecondaryButton>
                        </div>
                    )}

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
                                    {interval === 'annual'
                                        ? `${formatMoney(plan.annual_price_cents)} / yr`
                                        : `${formatMoney(plan.monthly_price_cents)} / mo`}{' '}
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
                                    ) : plan.upgradeable && plan.quotes ? (
                                        <PrimaryButton
                                            onClick={() =>
                                                setPending({
                                                    plan,
                                                    interval,
                                                    quote: plan.quotes![
                                                        interval
                                                    ],
                                                })
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

            <Modal
                show={pending !== null}
                maxWidth="lg"
                onClose={() => setPending(null)}
            >
                {pending && (
                    <div className="space-y-4 p-6">
                        <h3 className="text-lg font-semibold text-gray-900">
                            {pending.annualSwitch
                                ? `Switch ${pending.plan.name} to annual billing`
                                : `Upgrade to ${pending.plan.name} (${pending.interval})`}
                        </h3>
                        <BillingDisclosure quote={pending.quote} />
                        <div className="flex justify-end gap-3">
                            <SecondaryButton onClick={() => setPending(null)}>
                                Not now
                            </SecondaryButton>
                            <PrimaryButton onClick={confirm}>
                                Confirm
                                {pending.quote.charge_cents > 0 &&
                                    ` - ${formatMoney(pending.quote.charge_cents)} today`}
                            </PrimaryButton>
                        </div>
                    </div>
                )}
            </Modal>
        </AuthenticatedLayout>
    );
}
