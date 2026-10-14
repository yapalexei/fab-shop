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
import { formatCalendarDay, useShopDateTime } from '@/lib/datetime';
import { formatMoney } from '@/lib/money';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface PlanRow {
    id: number;
    key: string;
    name: string;
    user_limit: number | null;
    monthly_price_cents: number;
    annual_price_cents: number;
    features: string[];
    /** Set only when an admin chose to advertise when the tier leaves the list. */
    available_until: string | null;
    upgradeable: boolean;
    quotes: Record<Interval, BillingQuote> | null;
}

interface Seats {
    used: number;
    included: number | null;
    extra: number;
    limit: number | null;
    purchasable: boolean;
    can_buy: boolean;
    price_cents: number | null;
    min_extra: number;
    quotes: Record<string, BillingQuote>;
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
    currentPlanId,
    currentPlan,
    billingInterval,
    renewsAt,
    annualMonthsCharged,
    annualSwitch,
    seats,
}: PageProps<{
    plans: PlanRow[];
    /** The plan the shop is actually on - maybe an older version or a tier since removed from the list, so it can differ from `plans`. */
    currentPlanId: number | null;
    currentPlan: PlanRow | null;
    billingInterval: Interval;
    renewsAt: string | null;
    annualMonthsCharged: number;
    annualSwitch: BillingQuote | null;
    seats: Seats;
}>) {
    const formatDate = useShopDateTime({ dateStyle: 'medium' });
    const [interval, setInterval] = useState<Interval>(billingInterval);
    const [pending, setPending] = useState<Pending | null>(null);
    const current = currentPlan;
    const [addSeats, setAddSeats] = useState(1);
    const [seatsPending, setSeatsPending] = useState(false);
    const maxAdd = Object.keys(seats.quotes).length;
    const seatQuote = seats.quotes[String(addSeats)];

    const setExtraSeats = (extra: number) =>
        router.put(
            route('shop.seats.update'),
            { extra_seats: extra },
            { preserveScroll: true },
        );

    const confirmSeats = () => {
        setExtraSeats(seats.extra + addSeats);
        setSeatsPending(false);
        setAddSeats(1);
    };

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
                        <div className="flex flex-wrap items-center justify-between gap-3 border-l-4 border-indigo-400 bg-indigo-50 p-4 shadow-sm sm:rounded-lg dark:bg-indigo-900">
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

                    {currentPlan &&
                        !plans.some((plan) => plan.id === currentPlan.id) && (
                            <div className="border-l-4 border-indigo-400 bg-indigo-50 p-4 text-sm text-indigo-900 shadow-sm sm:rounded-lg dark:bg-indigo-900 dark:text-indigo-50">
                                You're on <strong>{currentPlan.name}</strong> at{' '}
                                {formatMoney(currentPlan.monthly_price_cents)}
                                /mo, which is no longer offered to new
                                customers. Your price and features stay as they
                                are until you move to another plan.
                            </div>
                        )}

                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        {plans.map((plan) => (
                            <div
                                key={plan.key}
                                className={`flex flex-col bg-white p-6 shadow sm:rounded-lg ${
                                    plan.id === currentPlanId
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
                                {plan.available_until && (
                                    <div className="mt-1 text-xs font-medium text-amber-700">
                                        Available until{' '}
                                        {formatCalendarDay(plan.available_until)}
                                    </div>
                                )}
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
                                    {plan.id === currentPlanId ? (
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

                    <div className="space-y-3 bg-white p-6 shadow sm:rounded-lg">
                        <div className="text-lg font-semibold text-gray-900">
                            Seats
                        </div>
                        <p className="text-sm text-gray-700">
                            <strong>{seats.used}</strong> of{' '}
                            <strong>{seats.limit ?? 'unlimited'}</strong> seats
                            used
                            {seats.included !== null &&
                                ` (${seats.included} included${seats.extra > 0 ? ` + ${seats.extra} extra` : ''})`}
                            . Pending invitations count toward the limit.
                        </p>
                        {!seats.purchasable ? (
                            <p className="text-sm text-gray-500">
                                Your plan doesn't offer extra seats - upgrade
                                to add more people.
                            </p>
                        ) : !seats.can_buy && seats.extra === 0 ? (
                            <p className="text-sm text-gray-500">
                                Extra seats are{' '}
                                {formatMoney(seats.price_cents ?? 0)}/month
                                each and are billed with your plan - choose a
                                plan above first.
                            </p>
                        ) : (
                            <div className="flex flex-wrap items-center gap-3">
                                {seats.can_buy && maxAdd > 0 && (
                                    <>
                                        <select
                                            className="rounded-md border border-gray-300 text-sm"
                                            value={addSeats}
                                            onChange={(e) =>
                                                setAddSeats(
                                                    Number(e.target.value),
                                                )
                                            }
                                        >
                                            {Object.keys(seats.quotes).map(
                                                (n) => (
                                                    <option key={n} value={n}>
                                                        {n}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                        <PrimaryButton
                                            onClick={() => setSeatsPending(true)}
                                        >
                                            Add seat
                                            {addSeats === 1 ? '' : 's'} (
                                            {formatMoney(
                                                (seats.price_cents ?? 0) *
                                                    addSeats,
                                            )}
                                            /mo)
                                        </PrimaryButton>
                                    </>
                                )}
                                {seats.extra > seats.min_extra && (
                                    <SecondaryButton
                                        onClick={() =>
                                            setExtraSeats(seats.extra - 1)
                                        }
                                    >
                                        Remove a seat
                                    </SecondaryButton>
                                )}
                            </div>
                        )}
                        {seats.purchasable && (
                            <p className="text-xs text-gray-500">
                                {formatMoney(seats.price_cents ?? 0)}/month per
                                extra seat. Removing a seat takes effect now; we
                                don't refund the current period.
                            </p>
                        )}
                    </div>
                </div>
            </div>

            <Modal
                show={seatsPending && !!seatQuote}
                maxWidth="lg"
                onClose={() => setSeatsPending(false)}
            >
                {seatQuote && (
                    <div className="space-y-4 p-6">
                        <h3 className="text-lg font-semibold text-gray-900">
                            Add {addSeats} seat{addSeats === 1 ? '' : 's'}
                        </h3>
                        <BillingDisclosure quote={seatQuote} />
                        <div className="flex justify-end gap-3">
                            <SecondaryButton
                                onClick={() => setSeatsPending(false)}
                            >
                                Not now
                            </SecondaryButton>
                            <PrimaryButton onClick={confirmSeats}>
                                Confirm
                                {seatQuote.charge_cents > 0 &&
                                    ` - ${formatMoney(seatQuote.charge_cents)} today`}
                            </PrimaryButton>
                        </div>
                    </div>
                )}
            </Modal>

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
