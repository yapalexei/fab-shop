import BillingDisclosure, {
    BillingQuote,
} from '@/Components/BillingDisclosure';
import BillingIntervalToggle, {
    Interval,
} from '@/Components/BillingIntervalToggle';
import DangerButton from '@/Components/DangerButton';
import Modal from '@/Components/Modal';
import PageHelp from '@/Components/PageHelp';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useShopDateTime } from '@/lib/datetime';
import { formatCents, formatMoney } from '@/lib/money';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';

interface PlanAdvice {
    plan_key: string;
    plan_name: string;
    plan_total_cents: number;
    separate_total_cents: number;
    savings_cents: number;
    covers: string[];
    still_addons: string[];
    extras: string[];
}

interface AddonRow {
    feature: string;
    label: string;
    tagline: string;
    priceCents: number;
    bundledInPlan: boolean;
    subscribed: boolean;
    advice: PlanAdvice | null;
    endsAt: string | null;
    quotes: Record<Interval, BillingQuote>;
}

// The plan page shows what the upgrade costs today and asks for confirmation.
function upgrade(_advice: PlanAdvice) {
    router.visit(route('shop.plan.index'));
}

function AdviceSummary({ advice }: { advice: PlanAdvice }) {
    const cheaper = advice.savings_cents > 0;

    return (
        <div className="space-y-2 text-sm text-gray-700">
            <p>
                As separate add-ons this comes to{' '}
                <strong>{formatCents(advice.separate_total_cents)}/mo</strong>.
                The <strong>{advice.plan_name}</strong> plan includes{' '}
                {advice.covers.join(' + ')}
                {advice.still_addons.length > 0 &&
                    ` (${advice.still_addons.join(', ')} would stay a separate add-on)`}{' '}
                for <strong>{formatCents(advice.plan_total_cents)}/mo</strong>
                {cheaper
                    ? ` - saving ${formatCents(advice.savings_cents)}/mo.`
                    : ' - the same price, with more included.'}
            </p>
            {advice.extras.length > 0 && (
                <p>Also included: {advice.extras.join(', ')}.</p>
            )}
        </div>
    );
}

function AddonAction({
    addon,
    onSubscribe,
    onCancel,
}: {
    addon: AddonRow;
    onSubscribe: (addon: AddonRow) => void;
    onCancel: (addon: AddonRow) => void;
}) {
    const formatDate = useShopDateTime({ dateStyle: 'medium' });

    if (addon.bundledInPlan) {
        return (
            <Badge variant="success">
                Included
            </Badge>
        );
    }

    if (addon.endsAt) {
        return (
            <div className="flex flex-col items-end gap-1">
                <span className="text-xs text-amber-700">
                    Ends {formatDate(addon.endsAt)}
                </span>
                <SecondaryButton
                    onClick={() =>
                        router.post(
                            route('shop.addons.store', addon.feature),
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    Keep
                </SecondaryButton>
            </div>
        );
    }

    if (addon.subscribed) {
        return (
            <DangerButton onClick={() => onCancel(addon)}>Cancel</DangerButton>
        );
    }

    return (
        <PrimaryButton onClick={() => onSubscribe(addon)}>
            Subscribe
        </PrimaryButton>
    );
}

export default function Index({
    addons,
    bannerAdvice,
    hasBillingPeriod,
    billingInterval,
    annualMonthsCharged,
}: PageProps<{
    addons: AddonRow[];
    bannerAdvice: PlanAdvice | null;
    hasBillingPeriod: boolean;
    billingInterval: Interval;
    annualMonthsCharged: number;
}>) {
    const formatDate = useShopDateTime({ dateStyle: 'medium' });
    // One dialog at a time: the cheaper-plan warning, the charge
    // confirmation, or the cancellation notice.
    const [advising, setAdvising] = useState<AddonRow | null>(null);
    const [confirming, setConfirming] = useState<AddonRow | null>(null);
    const [cancelling, setCancelling] = useState<AddonRow | null>(null);
    // Only chosen by a shop with no billing period yet; otherwise it follows theirs.
    const [chosenInterval, setChosenInterval] = useState<Interval>('monthly');
    const interval = hasBillingPeriod ? billingInterval : chosenInterval;

    const startSubscribe = (addon: AddonRow) =>
        addon.advice ? setAdvising(addon) : setConfirming(addon);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Add-ons
                    </h2>
                    <PageHelp>
                        <p>
                            Subscribe to a feature on its own, without upgrading
                            your whole plan. A feature your current plan already
                            includes is never billed again as a separate add-on.
                        </p>
                        <p>
                            An add-on you start part-way through a billing
                            period is charged for the days remaining, then
                            renews with everything else. Cancelling keeps it
                            until the end of the period you've paid for.
                        </p>
                        <p>
                            These subscriptions aren't charged automatically yet
                            - no payment processor is connected - but they
                            control what your shop can access the same way a
                            paid plan would.
                        </p>
                    </PageHelp>
                </div>
            }
        >
            <Head title="Add-ons" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    {bannerAdvice && (
                        <div
                            role="status"
                            className="mb-4 flex flex-wrap items-center justify-between gap-3 border-l-4 border-amber-400 bg-amber-50 p-4 shadow-sm sm:rounded-lg"
                        >
                            <div className="text-sm text-amber-900">
                                <div className="mb-1 font-semibold">
                                    You could pay less on the{' '}
                                    {bannerAdvice.plan_name} plan
                                </div>
                                <AdviceSummary advice={bannerAdvice} />
                            </div>
                            <PrimaryButton
                                onClick={() => upgrade(bannerAdvice)}
                            >
                                Review the {bannerAdvice.plan_name} plan
                            </PrimaryButton>
                        </div>
                    )}
                    <Card className="divide-y divide-gray-200">
                        {addons.map((addon) => (
                            <div
                                key={addon.feature}
                                className="flex items-center justify-between gap-4 p-6"
                            >
                                <div>
                                    <div className="text-sm font-medium text-gray-900">
                                        {addon.label}
                                    </div>
                                    <div className="mt-1 text-sm text-gray-500">
                                        {addon.tagline}
                                    </div>
                                    {addon.bundledInPlan ? (
                                        <div className="mt-1 text-xs text-gray-400">
                                            Saves{' '}
                                            {formatCents(addon.priceCents)}
                                            /mo vs. subscribing separately
                                        </div>
                                    ) : (
                                        <div className="mt-1 text-xs text-gray-400">
                                            {formatCents(addon.priceCents)} / mo
                                        </div>
                                    )}
                                </div>
                                <AddonAction
                                    addon={addon}
                                    onSubscribe={startSubscribe}
                                    onCancel={setCancelling}
                                />
                            </div>
                        ))}
                    </Card>
                </div>
            </div>

            <Modal
                show={advising !== null}
                maxWidth="lg"
                onClose={() => setAdvising(null)}
            >
                {advising?.advice && (
                    <div className="space-y-4 p-6">
                        <h3 className="text-lg font-semibold text-gray-900">
                            A plan would cover {advising.label} for less
                        </h3>
                        <AdviceSummary advice={advising.advice} />
                        <div className="flex flex-wrap justify-end gap-3">
                            <SecondaryButton
                                onClick={() => {
                                    setConfirming(advising);
                                    setAdvising(null);
                                }}
                            >
                                Subscribe anyway
                            </SecondaryButton>
                            <PrimaryButton
                                onClick={() => upgrade(advising.advice!)}
                            >
                                Review the {advising.advice.plan_name} plan
                            </PrimaryButton>
                        </div>
                    </div>
                )}
            </Modal>

            <Modal
                show={confirming !== null}
                maxWidth="lg"
                onClose={() => setConfirming(null)}
            >
                {confirming && (
                    <div className="space-y-4 p-6">
                        <h3 className="text-lg font-semibold text-gray-900">
                            Subscribe to {confirming.label}
                        </h3>
                        {!hasBillingPeriod && (
                            <BillingIntervalToggle
                                value={chosenInterval}
                                onChange={setChosenInterval}
                                annualMonthsCharged={annualMonthsCharged}
                            />
                        )}
                        <BillingDisclosure
                            quote={confirming.quotes[interval]}
                        />
                        <div className="flex justify-end gap-3">
                            <SecondaryButton
                                onClick={() => setConfirming(null)}
                            >
                                Not now
                            </SecondaryButton>
                            <PrimaryButton
                                onClick={() => {
                                    router.post(
                                        route(
                                            'shop.addons.store',
                                            confirming.feature,
                                        ),
                                        { confirm_separate: true, interval },
                                        { preserveScroll: true },
                                    );
                                    setConfirming(null);
                                }}
                            >
                                Subscribe
                                {confirming.quotes[interval].charge_cents > 0 &&
                                    ` - ${formatMoney(confirming.quotes[interval].charge_cents)} today`}
                            </PrimaryButton>
                        </div>
                    </div>
                )}
            </Modal>

            <Modal
                show={cancelling !== null}
                maxWidth="md"
                onClose={() => setCancelling(null)}
            >
                {cancelling && (
                    <div className="space-y-4 p-6">
                        <h3 className="text-lg font-semibold text-gray-900">
                            Cancel {cancelling.label}?
                        </h3>
                        <p className="text-sm text-gray-700">
                            {hasBillingPeriod ? (
                                <>
                                    You'll keep it until{' '}
                                    <strong>
                                        {formatDate(
                                            cancelling.quotes[billingInterval]
                                                .renews_at,
                                        )}
                                    </strong>
                                    , the end of the period you've already paid
                                    for, and it won't renew. We don't refund the
                                    current period.
                                </>
                            ) : (
                                "You'll lose access immediately."
                            )}
                        </p>
                        <div className="flex justify-end gap-3">
                            <SecondaryButton
                                onClick={() => setCancelling(null)}
                            >
                                Keep it
                            </SecondaryButton>
                            <DangerButton
                                onClick={() => {
                                    router.delete(
                                        route(
                                            'shop.addons.destroy',
                                            cancelling.feature,
                                        ),
                                        { preserveScroll: true },
                                    );
                                    setCancelling(null);
                                }}
                            >
                                Cancel subscription
                            </DangerButton>
                        </div>
                    </div>
                )}
            </Modal>
        </AuthenticatedLayout>
    );
}
