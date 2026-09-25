import DangerButton from '@/Components/DangerButton';
import PageHelp from '@/Components/PageHelp';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatCents } from '@/lib/money';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';

interface AddonRow {
    feature: string;
    label: string;
    tagline: string;
    priceCents: number;
    bundledInPlan: boolean;
    subscribed: boolean;
}

function AddonAction({ addon }: { addon: AddonRow }) {
    if (addon.bundledInPlan) {
        return (
            <span className="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                Included in your plan
            </span>
        );
    }

    if (addon.subscribed) {
        return (
            <DangerButton
                onClick={() => {
                    if (
                        confirm(
                            `Cancel ${addon.label}? You'll lose access immediately.`,
                        )
                    ) {
                        router.delete(
                            route('shop.addons.destroy', addon.feature),
                            { preserveScroll: true },
                        );
                    }
                }}
            >
                Cancel
            </DangerButton>
        );
    }

    return (
        <PrimaryButton
            onClick={() =>
                router.post(
                    route('shop.addons.store', addon.feature),
                    {},
                    { preserveScroll: true },
                )
            }
        >
            Subscribe
        </PrimaryButton>
    );
}

export default function Index({ addons }: PageProps<{ addons: AddonRow[] }>) {
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
                    <div className="divide-y divide-gray-200 bg-white shadow sm:rounded-lg">
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
                                <AddonAction addon={addon} />
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
