import DangerButton from '@/Components/DangerButton';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import SearchableSelect from '@/Components/SearchableSelect';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { router } from '@inertiajs/react';
import { FormDataConvertible } from '@inertiajs/core';
import { useEffect, useState } from 'react';

export interface ManagedShop {
    id: number;
    name: string;
    plan_id: number | null;
    is_suspended: boolean;
    on_trial: boolean;
    trial_ends_at: string | null;
    member_count: number;
    seat_limit: number | null;
    seat_limit_override: number | null;
    subscription_renews_at: string | null;
    subscription_overdue: boolean;
}

interface PlanOption {
    id: number;
    name: string;
}

const MS_PER_DAY = 86_400_000;

function daysUntil(iso: string): number {
    return Math.ceil((new Date(iso).getTime() - Date.now()) / MS_PER_DAY);
}

/**
 * Every per-shop admin action in one place, opened from a single "Manage"
 * button in the shop directory table - the table itself used to hold all of
 * this inline (an extend-days input + button, a seat-limit input, a
 * mark-paid input + button, a plan select, and a suspend button, all in one
 * row), which got crowded enough to spill past the page margin once
 * subscription tracking was added on top of trial/seat overrides. Nothing
 * here is new functionality, just consolidated out of the table row.
 */
export default function ShopManageDialog({
    shop,
    plans,
    onClose,
}: {
    shop: ManagedShop | null;
    plans: PlanOption[];
    onClose: () => void;
}) {
    const [trialDays, setTrialDays] = useState('');
    const [subscriptionDays, setSubscriptionDays] = useState('');
    const [seatLimit, setSeatLimit] = useState('');

    // Fresh drafts every time a different shop opens, not left over from
    // whichever shop was managed previously.
    useEffect(() => {
        setTrialDays('');
        setSubscriptionDays('');
        setSeatLimit(shop?.seat_limit_override?.toString() ?? '');
    }, [shop?.id, shop?.seat_limit_override]);

    // Keep rendering the last shop while the dialog fades out, same reason
    // JobMoveDialog does - the content shouldn't blank before the close
    // transition finishes.
    const [shown, setShown] = useState(shop);
    useEffect(() => {
        if (shop) setShown(shop);
    }, [shop]);

    if (!shown) {
        return null;
    }

    const patch = (
        routeName: string,
        data: Record<string, FormDataConvertible>,
        onSuccess?: () => void,
    ) => {
        router.patch(route(routeName, shown.id), data, {
            preserveScroll: true,
            onSuccess,
        });
    };

    const changePlan = (planId: string) => {
        patch('platform-admin.shops.plan', {
            plan_id: planId === '' ? null : planId,
        });
    };

    const extendTrial = () => {
        const days = Number(trialDays);
        if (!days || days < 1) return;
        patch('platform-admin.shops.trial.extend', { days }, () =>
            setTrialDays(''),
        );
    };

    const expireTrial = () => {
        if (confirm(`End ${shown.name}'s trial right now?`)) {
            router.post(
                route('platform-admin.shops.trial.expire', shown.id),
                {},
                { preserveScroll: true },
            );
        }
    };

    const extendSubscription = () => {
        const days = Number(subscriptionDays);
        if (!days || days < 1) return;
        patch('platform-admin.shops.subscription.extend', { days }, () =>
            setSubscriptionDays(''),
        );
    };

    const saveSeatLimit = () => {
        patch('platform-admin.shops.seat-limit', {
            seat_limit: seatLimit === '' ? null : Number(seatLimit),
        });
    };

    const toggleSuspend = () => {
        if (shown.is_suspended) {
            router.post(
                route('platform-admin.shops.unsuspend', shown.id),
                {},
                { preserveScroll: true },
            );
            return;
        }

        if (
            confirm(
                `Suspend ${shown.name}? Its users will be locked out until you unsuspend it.`,
            )
        ) {
            router.post(
                route('platform-admin.shops.suspend', shown.id),
                {},
                { preserveScroll: true },
            );
        }
    };

    return (
        <Modal show={shop !== null} maxWidth="lg" onClose={onClose}>
            <div className="p-6">
                <h2 className="text-lg font-medium text-gray-900">
                    Manage {shown.name}
                </h2>

                <div className="mt-6 space-y-6">
                    <div>
                        <InputLabel value="Plan" />
                        <SearchableSelect
                            wrapperClassName="mt-1 w-48"
                            className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={String(shown.plan_id ?? '')}
                            onChange={changePlan}
                            emptyLabel="No plan"
                            options={plans.map((plan) => ({
                                value: String(plan.id),
                                label: plan.name,
                            }))}
                        />
                    </div>

                    <div>
                        <InputLabel value="Seat limit override" />
                        <p className="mt-1 text-xs text-gray-500">
                            {shown.member_count} member(s). Blank uses the plan
                            default
                            {shown.seat_limit !== null
                                ? ` (${shown.seat_limit})`
                                : ' (unlimited)'}
                            .
                        </p>
                        <div className="mt-1 flex items-center gap-2">
                            <TextInput
                                type="number"
                                min="1"
                                placeholder="plan default"
                                className="w-32"
                                value={seatLimit}
                                onChange={(e) => setSeatLimit(e.target.value)}
                                onBlur={saveSeatLimit}
                            />
                        </div>
                    </div>

                    <div className="border-t border-gray-100 pt-6">
                        <InputLabel value="Trial" />
                        <p className="mt-1 text-xs text-gray-500">
                            {shown.trial_ends_at
                                ? shown.on_trial
                                    ? `Ends ${new Date(shown.trial_ends_at).toLocaleDateString()} (${daysUntil(shown.trial_ends_at)} day(s) left).`
                                    : `Ended ${new Date(shown.trial_ends_at).toLocaleDateString()}.`
                                : 'This shop has never had a trial.'}
                        </p>
                        <div className="mt-2 flex items-center gap-2">
                            <TextInput
                                type="number"
                                min="1"
                                placeholder="days"
                                className="w-20"
                                value={trialDays}
                                onChange={(e) => setTrialDays(e.target.value)}
                            />
                            <SecondaryButton onClick={extendTrial}>
                                Extend
                            </SecondaryButton>
                            {shown.on_trial && (
                                <button
                                    type="button"
                                    onClick={expireTrial}
                                    className="text-xs text-red-600 hover:text-red-800 hover:underline"
                                >
                                    End trial now
                                </button>
                            )}
                        </div>
                    </div>

                    <div className="border-t border-gray-100 pt-6">
                        <InputLabel value="Subscription" />
                        <p className="mt-1 text-xs text-gray-500">
                            {shown.subscription_renews_at
                                ? shown.subscription_overdue
                                    ? `Overdue since ${new Date(shown.subscription_renews_at).toLocaleDateString()}.`
                                    : `Paid until ${new Date(shown.subscription_renews_at).toLocaleDateString()} (${daysUntil(shown.subscription_renews_at)} day(s) left).`
                                : "Not tracked - this is a manual record, there's no real payment processor wired up."}
                        </p>
                        <div className="mt-2 flex items-center gap-2">
                            <TextInput
                                type="number"
                                min="1"
                                placeholder="days"
                                className="w-20"
                                value={subscriptionDays}
                                onChange={(e) =>
                                    setSubscriptionDays(e.target.value)
                                }
                            />
                            <SecondaryButton onClick={extendSubscription}>
                                Mark paid
                            </SecondaryButton>
                        </div>
                    </div>
                </div>

                <div className="mt-6 flex items-center justify-between border-t border-gray-100 pt-6">
                    {shown.is_suspended ? (
                        <SecondaryButton onClick={toggleSuspend}>
                            Unsuspend
                        </SecondaryButton>
                    ) : (
                        <DangerButton onClick={toggleSuspend}>
                            Suspend
                        </DangerButton>
                    )}
                    <SecondaryButton onClick={onClose}>Close</SecondaryButton>
                </div>
            </div>
        </Modal>
    );
}
