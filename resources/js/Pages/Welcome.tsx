import ApplicationLogo from '@/Components/ApplicationLogo';
import { formatCents } from '@/lib/money';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface FeatureRow {
    key: string;
    label: string;
    tagline: string;
    shipped: boolean;
}

interface TierFeature {
    key: string;
    included: boolean;
}

interface Tier {
    key: string;
    name: string;
    userLimit: number;
    isTrialOnly: boolean;
    monthlyPriceCents: number;
    savingsCents: number;
    features: TierFeature[];
}

function Check({ ok }: { ok: boolean }) {
    return ok ? (
        <svg
            className="h-5 w-5 shrink-0 text-green-600"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
        >
            <path
                fillRule="evenodd"
                d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                clipRule="evenodd"
            />
        </svg>
    ) : (
        <svg
            className="h-5 w-5 shrink-0 text-gray-300"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
        >
            <path
                fillRule="evenodd"
                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                clipRule="evenodd"
            />
        </svg>
    );
}

function TierCard({
    tier,
    features,
    canRegister,
}: {
    tier: Tier;
    features: FeatureRow[];
    canRegister: boolean;
}) {
    return (
        <div className="flex h-full flex-col rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h3 className="text-lg font-semibold text-gray-900">
                {tier.name}
            </h3>
            <p className="mt-1 text-sm text-gray-500">
                Up to {tier.userLimit} user{tier.userLimit === 1 ? '' : 's'}
            </p>

            {!tier.isTrialOnly && (
                <div className="mt-2">
                    <span className="text-2xl font-bold text-gray-900">
                        {formatCents(tier.monthlyPriceCents)}
                    </span>
                    <span className="text-sm text-gray-500">/mo</span>
                    {tier.savingsCents > 0 && (
                        <p className="mt-1 text-xs font-medium text-green-700">
                            Save {formatCents(tier.savingsCents)}/mo bundled vs.
                            buying these features separately
                        </p>
                    )}
                </div>
            )}

            {tier.isTrialOnly ? (
                <p className="mt-4 flex-1 rounded-md bg-indigo-50 px-3 py-2 text-sm text-indigo-900">
                    Every shipped feature, free for 14 days.
                </p>
            ) : (
                <ul className="mt-4 flex-1 space-y-2">
                    {features.map((feature) => {
                        const row = tier.features.find(
                            (f) => f.key === feature.key,
                        );
                        const included = row?.included ?? false;

                        return (
                            <li
                                key={feature.key}
                                className="flex items-center gap-2 text-sm"
                            >
                                {feature.shipped ? (
                                    <Check ok={included} />
                                ) : (
                                    <svg
                                        className="h-5 w-5 shrink-0 text-amber-400"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                        aria-hidden="true"
                                    >
                                        <path
                                            fillRule="evenodd"
                                            d="M8.257 3.099c.765-1.36 2.72-1.36 3.486 0l6.28 11.185c.75 1.334-.213 2.98-1.742 2.98H3.72c-1.53 0-2.493-1.646-1.743-2.98L8.257 3.1zM10 13a1 1 0 100-2 1 1 0 000 2zm-.75-6a.75.75 0 011.5 0v3a.75.75 0 01-1.5 0v-3z"
                                            clipRule="evenodd"
                                        />
                                    </svg>
                                )}
                                <span
                                    className={
                                        feature.shipped
                                            ? included
                                                ? 'text-gray-900'
                                                : 'text-gray-400'
                                            : 'text-gray-500'
                                    }
                                >
                                    {feature.label}
                                    {!feature.shipped && (
                                        <span className="ms-1.5 text-xs text-amber-600">
                                            (coming soon)
                                        </span>
                                    )}
                                </span>
                            </li>
                        );
                    })}
                </ul>
            )}

            {canRegister && (
                <Link
                    href={route('register')}
                    className="mt-6 block rounded-md bg-indigo-600 px-4 py-2 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500"
                >
                    Start free trial
                </Link>
            )}
        </div>
    );
}

export default function Welcome({
    auth,
    canLogin,
    canRegister,
    features,
    tiers,
}: PageProps<{
    canLogin: boolean;
    canRegister: boolean;
    features: FeatureRow[];
    tiers: Tier[];
}>) {
    return (
        <>
            <Head title="Quoting, jobs & invoicing for metal fab shops" />

            <div className="min-h-screen bg-white">
                <header className="border-b border-gray-100">
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                        <Link
                            href="/"
                            className="flex items-center gap-2 text-gray-900"
                        >
                            <ApplicationLogo className="h-8 w-8 fill-current text-gray-800" />
                            <span className="text-lg font-semibold">
                                Fab Shop
                            </span>
                        </Link>

                        <nav className="flex items-center gap-4">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    {canLogin && (
                                        <Link
                                            href={route('login')}
                                            className="text-sm font-medium text-gray-600 hover:text-gray-900"
                                        >
                                            Log in
                                        </Link>
                                    )}
                                    {canRegister && (
                                        <Link
                                            href={route('register')}
                                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500"
                                        >
                                            Start free trial
                                        </Link>
                                    )}
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <main>
                    <section className="mx-auto max-w-5xl px-4 py-20 text-center sm:px-6 lg:px-8">
                        <h1 className="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
                            Quoting, jobs and invoicing built for metal fab
                            shops
                        </h1>
                        <p className="mx-auto mt-6 max-w-2xl text-lg text-gray-600">
                            Replace the QuickBooks-plus-spreadsheets-plus-paper-traveler
                            routine with one place to quote a job, track it
                            across the shop floor, and sync the invoice
                            automatically once it ships.
                        </p>
                        <div className="mt-10 flex items-center justify-center gap-4">
                            {canRegister && (
                                <Link
                                    href={route('register')}
                                    className="rounded-md bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500"
                                >
                                    Start your free 14-day trial
                                </Link>
                            )}
                            <a
                                href="#pricing"
                                className="text-sm font-semibold text-gray-700 hover:text-gray-900"
                            >
                                See what&apos;s included →
                            </a>
                        </div>
                    </section>

                    <section className="bg-gray-50 py-16">
                        <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                            <h2 className="text-center text-2xl font-bold text-gray-900">
                                Everything a small shop needs to quote, track
                                and get paid
                            </h2>
                            <div className="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                {features.map((feature) => (
                                    <div
                                        key={feature.key}
                                        className="rounded-lg bg-white p-6 shadow-sm"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <h3 className="font-semibold text-gray-900">
                                                {feature.label}
                                            </h3>
                                            {!feature.shipped && (
                                                <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                                                    Coming soon
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-2 text-sm text-gray-600">
                                            {feature.tagline}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section id="pricing" className="py-16">
                        <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                            <h2 className="text-center text-2xl font-bold text-gray-900">
                                One plan to try, two to grow into
                            </h2>
                            <p className="mx-auto mt-3 max-w-2xl text-center text-sm text-gray-600">
                                Every plan starts with a full-access, 14-day
                                free trial - no card required. Pick Start-up
                                or Pro when the trial ends, or keep exploring
                                on the free trial until you're ready.
                            </p>

                            <div className="mt-10 grid grid-cols-1 items-stretch gap-6 lg:grid-cols-3">
                                {tiers.map((tier) => (
                                    <TierCard
                                        key={tier.key}
                                        tier={tier}
                                        features={features}
                                        canRegister={canRegister}
                                    />
                                ))}
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="border-t border-gray-100 py-8">
                    <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 text-sm text-gray-500 sm:flex-row sm:px-6 lg:px-8">
                        <span className="flex items-center gap-2">
                            <ApplicationLogo className="h-5 w-5 fill-current text-gray-400" />
                            Fab Shop
                        </span>
                        {canLogin && (
                            <Link
                                href={route('login')}
                                className="hover:text-gray-700"
                            >
                                Log in
                            </Link>
                        )}
                    </div>
                </footer>
            </div>
        </>
    );
}
