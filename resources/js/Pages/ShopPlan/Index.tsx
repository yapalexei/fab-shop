import BillingDisclosure, {
    BillingQuote,
} from '@/Components/BillingDisclosure';
import BillingIntervalToggle, {
    Interval,
} from '@/Components/BillingIntervalToggle';
import Modal from '@/Components/Modal';
import { ComparisonPlan } from '@/Components/PlanComparisonTable';
import PlanCostCalculator from '@/Components/PlanCostCalculator';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SubscriptionTerms, {
    SubscriptionTermsProps,
} from '@/Components/SubscriptionTerms';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatCalendarDay, useShopDateTime } from '@/lib/datetime';
import { formatMoney } from '@/lib/money';
import { PageProps } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface PlanRow {
    id: number;
    key: string;
    name: string;
    user_limit: number | null;
    monthly_price_cents: number;
    annual_price_cents: number;
    features: string[];
    extra_seat_price_cents: number | null;
    feature_keys: string[];
    /** Set only when an admin chose to advertise when the tier leaves the list. */
    available_until: string | null;
    upgradeable: boolean;
    quotes: Record<Interval, BillingQuote> | null;
}

interface PendingChange {
    plan_id: number;
    from_monthly_cents: number;
    from_annual_cents: number;
    name: string;
    monthly_price_cents: number;
    annual_price_cents: number;
    effective_at: string;
}

interface PlanNotice {
    plan_name: string;
    added: { label: string; tagline: string }[];
    retired: { label: string }[];
    price: { from_cents: number; to_cents: number; effective_at: string } | null;
    seats: { from: number | null; to: number | null } | null;
}

interface Calculator {
    features: { key: string; label: string; addonCents: number }[];
    /** Features the shop uses today. */
    needed: string[];
    people: number;
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
    pendingChange,
    planNotice,
    calculator,
    terms,
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
    /** A lower price already promised for the next payment period. */
    pendingChange: PendingChange | null;
    /** What changed on the shop's plan since the owner last dismissed it. */
    planNotice: PlanNotice | null;
    calculator: Calculator;
    terms: SubscriptionTermsProps;
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
    // The same tier's newest version, when the shop is on an earlier one.
    const sameTier = currentPlan
        ? plans.find(
              (plan) =>
                  plan.key === currentPlan.key && plan.id !== currentPlan.id,
          )
        : undefined;
    // What the shop itself pays, per its own billing interval.
    const ownPrice = (plan: {
        monthly_price_cents: number;
        annual_price_cents: number;
    }) =>
        billingInterval === 'annual'
            ? `${formatMoney(plan.annual_price_cents)}/year`
            : `${formatMoney(plan.monthly_price_cents)}/month`;
    // Every plan the owner could be on, for the comparison table: what is
    // on offer, plus their own plan when it isn't (an earlier version, or a
    // tier we no longer sell) so its price sits beside the rest.
    const toComparison = (
        plan: PlanRow,
        note?: string,
        highlight?: ComparisonPlan['highlight'],
    ): ComparisonPlan => ({
        id: plan.id,
        name: plan.name,
        note,
        highlight,
        monthlyCents: plan.monthly_price_cents,
        seats: plan.user_limit,
        extraSeatCents: plan.extra_seat_price_cents,
        features: plan.feature_keys,
        action:
            plan.id === currentPlanId ? (
                <span className="text-sm font-medium text-indigo-700">
                    Current plan
                </span>
            ) : plan.id === pendingChange?.plan_id ? (
                <span className="text-sm font-medium text-green-700">
                    From {formatDate(pendingChange.effective_at)}
                </span>
            ) : plan.upgradeable && plan.quotes ? (
                <PrimaryButton
                    onClick={() =>
                        setPending({
                            plan,
                            interval,
                            quote: plan.quotes![interval],
                        })
                    }
                >
                    Upgrade
                </PrimaryButton>
            ) : null,
    });
    const comparisonPlans: ComparisonPlan[] = [
        ...(currentPlan && !plans.some((plan) => plan.id === currentPlan.id)
            ? [
                  {
                      ...toComparison(currentPlan, 'Your plan', 'current'),
                      action: null,
                  },
              ]
            : []),
        ...plans.map((plan) =>
            toComparison(
                plan,
                plan.id === currentPlanId
                    ? 'Your plan'
                    : plan.id === pendingChange?.plan_id
                      ? 'Your plan from ' + formatDate(pendingChange.effective_at)
                      : undefined,
                plan.id === currentPlanId
                    ? 'current'
                    : plan.id === pendingChange?.plan_id
                      ? 'next'
                      : undefined,
            ),
        ),
    ];
    const [addSeats, setAddSeats] = useState(1);
    // The Terms box in the confirmations (only asked until they've agreed).
    const [agreed, setAgreed] = useState(false);
    const canConfirm = terms.accepted || agreed;
    const termsError = usePage<PageProps>().props.errors?.accepted_terms;
    const [seatsPending, setSeatsPending] = useState(false);
    const maxAdd = Object.keys(seats.quotes).length;
    const seatQuote = seats.quotes[String(addSeats)];

    const setExtraSeats = (extra: number) =>
        router.put(
            route('shop.seats.update'),
            { extra_seats: extra, accepted_terms: agreed },
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
            accepted_terms: agreed,
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

                    {currentPlan && pendingChange ? (
                        <div className="border-l-4 border-amber-500 bg-amber-50 p-4 text-sm text-amber-950 shadow-sm sm:rounded-lg dark:bg-amber-950 dark:text-amber-100">
                            <strong>Heads up:</strong> the price of your{' '}
                            {currentPlan.name} plan goes up from{' '}
                            <strong>
                                {billingInterval === 'annual'
                                    ? `${formatMoney(pendingChange.from_annual_cents)}/year`
                                    : `${formatMoney(pendingChange.from_monthly_cents)}/month`}
                            </strong>{' '}
                            to <strong>{ownPrice(pendingChange)}</strong> on{' '}
                            <strong>{formatDate(pendingChange.effective_at)}</strong>
                            . Nothing changes before then and what you've
                            already paid for isn't affected; the new price
                            applies to your first payment on or after that
                            date. If you'd rather not continue at the new
                            price, you can cancel before{' '}
                            {formatDate(pendingChange.effective_at)} and you
                            won't be charged it. See "Price changes" in the{' '}
                            <a
                                href={route('legal.terms')}
                                target="_blank"
                                rel="noreferrer"
                                className="font-medium underline"
                            >
                                Terms of Service
                            </a>
                            . You keep every feature you have.
                        </div>
                    ) : (
                        currentPlan &&
                        !plans.some((plan) => plan.id === currentPlan.id) && (
                            <div className="border-l-4 border-indigo-400 bg-indigo-50 p-4 text-sm text-indigo-900 shadow-sm sm:rounded-lg dark:bg-indigo-900 dark:text-indigo-50">
                                {sameTier ? (
                                    sameTier.monthly_price_cents >
                                    currentPlan.monthly_price_cents ? (
                                        <>
                                            You're on an earlier version of the{' '}
                                            <strong>{currentPlan.name}</strong>{' '}
                                            plan at{' '}
                                            <strong>{ownPrice(currentPlan)}</strong>
                                            ; new customers pay{' '}
                                            {ownPrice(sameTier)}. You keep
                                            your price, and you get every
                                            feature added to the plan.
                                        </>
                                    ) : sameTier.monthly_price_cents <
                                      currentPlan.monthly_price_cents ? (
                                        <>
                                            You're on an earlier version of the{' '}
                                            <strong>{currentPlan.name}</strong>{' '}
                                            plan at{' '}
                                            <strong>{ownPrice(currentPlan)}</strong>
                                            . The current version is{' '}
                                            {ownPrice(sameTier)}, but it
                                            gives your shop fewer seats (or
                                            dearer extra seats), so we haven't
                                            moved you to it automatically.
                                            Your seats and price stay as they
                                            are.
                                        </>
                                    ) : (
                                        <>
                                            You're on an earlier version of the{' '}
                                            <strong>{currentPlan.name}</strong>{' '}
                                            plan. Your price is the same as the
                                            current version's, and you get
                                            every feature added to it.
                                        </>
                                    )
                                ) : (
                                    <>
                                        You're on <strong>{currentPlan.name}</strong>{' '}
                                        at <strong>{ownPrice(currentPlan)}</strong>
                                        , which is no longer offered to new
                                        customers. You keep your price and
                                        features.
                                    </>
                                )}
                            </div>
                        )
                    )}

                    {planNotice &&
                        (planNotice.added.length > 0 ||
                            planNotice.retired.length > 0 ||
                            planNotice.seats ||
                            (planNotice.price && !pendingChange)) && (
                            <div className="space-y-3 border-l-4 border-sky-500 bg-sky-50 p-4 text-sm text-sky-950 shadow-sm sm:rounded-lg dark:bg-sky-950 dark:text-sky-50">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="font-semibold">
                                        What changed on your{' '}
                                        {planNotice.plan_name} plan
                                    </div>
                                    <button
                                        type="button"
                                        className="shrink-0 text-xs underline"
                                        onClick={() =>
                                            router.delete(
                                                route(
                                                    'shop.plan.notice.dismiss',
                                                ),
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Got it
                                    </button>
                                </div>

                                {planNotice.price && !pendingChange && (
                                    <p>
                                        <strong>Price:</strong> since{' '}
                                        {formatDate(
                                            planNotice.price.effective_at,
                                        )}{' '}
                                        it{' '}
                                        {planNotice.price.to_cents <
                                        planNotice.price.from_cents
                                            ? 'went down'
                                            : 'went up'}{' '}
                                        from{' '}
                                        {formatMoney(planNotice.price.from_cents)}{' '}
                                        to{' '}
                                        {formatMoney(planNotice.price.to_cents)}
                                        /month.
                                    </p>
                                )}

                                {planNotice.seats && (
                                    <p>
                                        <strong>Seats included:</strong>{' '}
                                        {planNotice.seats.from ?? 'unlimited'} →{' '}
                                        {planNotice.seats.to ?? 'unlimited'}
                                        {pendingChange
                                            ? ` from ${formatDate(pendingChange.effective_at)}`
                                            : ''}
                                        .
                                    </p>
                                )}

                                {planNotice.added.length > 0 && (
                                    <div>
                                        <strong>
                                            Now included, on top of what you
                                            had:
                                        </strong>
                                        <ul className="mt-1 space-y-1">
                                            {planNotice.added.map((feature) => (
                                                <li key={feature.label}>
                                                    ✓ {feature.label}
                                                    <span className="text-sky-800 dark:text-sky-200">
                                                        {' '}
                                                        - {feature.tagline}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}

                                {planNotice.retired.length > 0 && (
                                    <div className="text-red-900 dark:text-red-200">
                                        <strong>No longer available:</strong>{' '}
                                        {planNotice.retired
                                            .map((feature) => feature.label)
                                            .join(', ')}
                                        . We can no longer support{' '}
                                        {planNotice.retired.length === 1
                                            ? 'it, so it has'
                                            : 'them, so they have'}{' '}
                                        been removed from every plan, and any
                                        add-on for{' '}
                                        {planNotice.retired.length === 1
                                            ? 'it'
                                            : 'them'}{' '}
                                        is cancelled.
                                    </div>
                                )}

                                <p>You keep every other feature you had.</p>
                            </div>
                        )}

                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        {plans.map((plan) => (
                            <div
                                key={plan.key}
                                className={`flex flex-col bg-white p-6 shadow sm:rounded-lg ${
                                    plan.id === currentPlanId
                                        ? 'ring-2 ring-indigo-500'
                                        : plan.id === pendingChange?.plan_id
                                          ? 'ring-2 ring-green-500'
                                          : plan.id === sameTier?.id
                                            ? 'ring-2 ring-indigo-300'
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
                                    ) : plan.id === pendingChange?.plan_id ? (
                                        <span className="text-sm font-medium text-green-700">
                                            Your plan from{' '}
                                            {formatDate(
                                                pendingChange.effective_at,
                                            )}
                                        </span>
                                    ) : plan.id === sameTier?.id ? (
                                        <span className="text-sm font-medium text-indigo-700">
                                            Your plan (earlier version)
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

                    <div className="space-y-4 bg-white p-6 shadow sm:rounded-lg">
                        <div>
                            <div className="text-lg font-semibold text-gray-900">
                                What would each plan cost your shop?
                            </div>
                            <p className="mt-1 text-sm text-gray-600">
                                Every plan you can subscribe to, side by side -
                                monthly and yearly, with extra people and any
                                add-ons you'd still need. Nothing is charged
                                from here.
                            </p>
                        </div>
                        <PlanCostCalculator
                            plans={comparisonPlans}
                            features={calculator.features}
                            annualMonths={annualMonthsCharged}
                            initialPeople={calculator.people}
                            initialNeeded={calculator.needed}
                        />
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
                        <SubscriptionTerms
                            terms={terms}
                            checked={agreed}
                            onChange={setAgreed}
                            error={termsError}
                        />
                        <div className="flex justify-end gap-3">
                            <SecondaryButton
                                onClick={() => setSeatsPending(false)}
                            >
                                Not now
                            </SecondaryButton>
                            <PrimaryButton onClick={confirmSeats} disabled={!canConfirm}>
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
                        <SubscriptionTerms
                            terms={terms}
                            checked={agreed}
                            onChange={setAgreed}
                            error={termsError}
                        />
                        <div className="flex justify-end gap-3">
                            <SecondaryButton onClick={() => setPending(null)}>
                                Not now
                            </SecondaryButton>
                            <PrimaryButton onClick={confirm} disabled={!canConfirm}>
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
