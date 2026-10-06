import ApplicationLogo from '@/Components/ApplicationLogo';
import LegalLinks from '@/Components/LegalLinks';
import ThemeToggle from '@/Components/ThemeToggle';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { formatCents } from '@/lib/money';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    ClipboardList,
    FileText,
    Hammer,
    Minus,
    Receipt,
    TriangleAlert,
} from 'lucide-react';

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

const numberWords = [
    'zero',
    'one',
    'two',
    'three',
    'four',
    'five',
    'six',
    'seven',
    'eight',
    'nine',
];

const steps = [
    {
        icon: FileText,
        title: 'Quote',
        text: 'Build a priced quote from your own materials, services and tooling catalog, with markup rules and a PDF you can email to the customer.',
    },
    {
        icon: Hammer,
        title: 'Run the job',
        text: 'A won quote becomes a job on a board you shape to your shop - move it stage by stage, log material and scrap, attach photos.',
    },
    {
        icon: Receipt,
        title: 'Invoice',
        text: 'Ship it and it is flagged ready to invoice, so nothing finished sits unbilled.',
    },
];

/** Decorative, static picture of the job board - not live data. */
function BoardPreview() {
    const columns = [
        { name: 'Not started', cards: ['Gate repair', 'Stair rail'] },
        { name: 'Cutting', cards: ['Trailer hitch'] },
        { name: 'Welding', cards: ['Planter boxes', 'Fire pit'] },
        { name: 'Shipped', cards: ['Bracket set'] },
    ];

    return (
        <div
            aria-hidden="true"
            className="mx-auto mt-14 max-w-4xl rounded-xl border bg-card p-3 shadow-xl ring-1 ring-black/5 sm:p-4"
        >
            <div className="mb-3 flex items-center gap-1.5 px-1">
                <span className="size-2.5 rounded-full bg-red-400" />
                <span className="size-2.5 rounded-full bg-amber-400" />
                <span className="size-2.5 rounded-full bg-green-400" />
            </div>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {columns.map((column) => (
                    <div
                        key={column.name}
                        className="rounded-lg bg-muted/60 p-2 text-start"
                    >
                        <p className="px-1 pb-2 text-xs font-semibold text-muted-foreground">
                            {column.name}
                        </p>
                        <div className="space-y-2">
                            {column.cards.map((card) => (
                                <div
                                    key={card}
                                    className="rounded-md border bg-background p-2.5 text-xs shadow-xs"
                                >
                                    <p className="font-medium">{card}</p>
                                    <div className="mt-2 h-1.5 w-2/3 rounded-full bg-muted" />
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function TierFeatureRow({
    feature,
    tier,
}: {
    feature: FeatureRow;
    tier: Tier;
}) {
    const row = tier.features.find((f) => f.key === feature.key);
    const included = row?.included ?? false;

    return (
        <li className="flex items-center gap-2 text-sm">
            {feature.shipped ? (
                included ? (
                    <Check className="size-4 shrink-0 text-green-600" />
                ) : (
                    <Minus className="size-4 shrink-0 text-muted-foreground/50" />
                )
            ) : (
                <TriangleAlert className="size-4 shrink-0 text-amber-500" />
            )}
            <span
                className={
                    feature.shipped
                        ? included
                            ? 'text-foreground'
                            : 'text-muted-foreground/60'
                        : 'text-muted-foreground'
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
}

function TierCard({
    tier,
    features,
    canRegister,
    trialDays,
}: {
    tier: Tier;
    features: FeatureRow[];
    canRegister: boolean;
    trialDays: number;
}) {
    // Split down the middle rather than a 2-col CSS grid with row-major
    // flow, so the first half fills the left column top-to-bottom and the
    // rest fills the right - not interleaved left/right/left/right.
    const half = Math.ceil(features.length / 2);
    const firstColumn = features.slice(0, half);
    const secondColumn = features.slice(half);

    return (
        <Card className="flex h-full flex-col p-6">
            <h3 className="text-lg font-semibold">{tier.name}</h3>
            <p className="mt-1 text-sm text-muted-foreground">
                Up to {tier.userLimit} user{tier.userLimit === 1 ? '' : 's'}
            </p>

            {!tier.isTrialOnly && (
                <div className="mt-3">
                    <span className="text-3xl font-bold tracking-tight">
                        {formatCents(tier.monthlyPriceCents)}
                    </span>
                    <span className="text-sm text-muted-foreground">/mo</span>
                    {tier.savingsCents > 0 && (
                        <p className="mt-1 text-xs font-medium text-green-700">
                            Save {formatCents(tier.savingsCents)}/mo bundled vs.
                            buying these features separately
                        </p>
                    )}
                </div>
            )}

            {tier.isTrialOnly ? (
                <p className="mt-4 flex-1 rounded-md bg-indigo-50 px-3 py-2 text-sm text-indigo-900 dark:bg-indigo-900 dark:text-indigo-50">
                    Every shipped feature, free for {trialDays} days.
                </p>
            ) : (
                // 2 columns once the card has room for them (full-width on
                // mobile, half-width at sm) - back to 1 column at lg, where
                // the tier grid itself goes to 4-across and each card gets
                // too narrow for two columns of feature labels.
                <div className="mt-5 grid flex-1 grid-cols-1 content-start gap-x-6 gap-y-2 border-t pt-5 sm:grid-cols-2 lg:grid-cols-1">
                    <ul className="space-y-2">
                        {firstColumn.map((feature) => (
                            <TierFeatureRow
                                key={feature.key}
                                feature={feature}
                                tier={tier}
                            />
                        ))}
                    </ul>
                    <ul className="space-y-2">
                        {secondColumn.map((feature) => (
                            <TierFeatureRow
                                key={feature.key}
                                feature={feature}
                                tier={tier}
                            />
                        ))}
                    </ul>
                </div>
            )}

            {canRegister && (
                <Button asChild className="mt-6" variant={tier.isTrialOnly ? 'default' : 'outline'}>
                    <Link href={route('register')}>Start free trial</Link>
                </Button>
            )}
        </Card>
    );
}

export default function Welcome({
    auth,
    canLogin,
    canRegister,
    features,
    tiers,
    trialDays,
}: PageProps<{
    canLogin: boolean;
    canRegister: boolean;
    features: FeatureRow[];
    tiers: Tier[];
    trialDays: number;
}>) {
    return (
        <>
            <Head title="Quoting, jobs & invoicing for metal fab shops" />

            <div className="min-h-screen bg-background">
                <header className="sticky top-0 z-40 border-b bg-background/80 backdrop-blur">
                    <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                        <Link
                            href="/"
                            className="flex items-center gap-2 text-foreground"
                        >
                            <ApplicationLogo className="size-8 fill-current" />
                            <span className="text-lg font-semibold">
                                Fab Shop
                            </span>
                        </Link>

                        <nav className="flex items-center gap-2">
                            <a
                                href="#pricing"
                                className="hidden px-3 text-sm font-medium text-muted-foreground hover:text-foreground sm:inline"
                            >
                                Pricing
                            </a>
                            <ThemeToggle />
                            {auth.user ? (
                                <Button asChild size="sm">
                                    <Link href={route('dashboard')}>
                                        Dashboard
                                    </Link>
                                </Button>
                            ) : (
                                <>
                                    {canLogin && (
                                        <Button asChild variant="ghost" size="sm">
                                            <Link href={route('login')}>
                                                Log in
                                            </Link>
                                        </Button>
                                    )}
                                    {canRegister && (
                                        <Button asChild size="sm">
                                            <Link href={route('register')}>
                                                Start free trial
                                            </Link>
                                        </Button>
                                    )}
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <main>
                    <section className="relative overflow-hidden">
                        <div
                            aria-hidden="true"
                            className="absolute inset-x-0 top-0 -z-10 h-[32rem] bg-linear-to-b from-indigo-100/70 to-transparent dark:from-indigo-950/60"
                        />
                        <div className="mx-auto max-w-5xl px-4 pb-16 pt-20 text-center sm:px-6 sm:pt-28 lg:px-8">
                            <Badge variant="secondary" className="mb-6">
                                For custom, ornamental and mobile fab shops
                            </Badge>
                            <h1 className="text-4xl font-bold tracking-tight sm:text-6xl">
                                Quoting, jobs and invoicing built for metal fab
                                shops
                            </h1>
                            <p className="mx-auto mt-6 max-w-2xl text-lg text-muted-foreground">
                                Replace the
                                QuickBooks-plus-spreadsheets-plus-paper-traveler
                                routine with one place to quote a job, track it
                                across the shop floor, and get it invoiced once
                                it ships.
                            </p>
                            <div className="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
                                {canRegister && (
                                    <Button asChild size="lg">
                                        <Link href={route('register')}>
                                            Start your free {trialDays}-day
                                            trial
                                            <ArrowRight />
                                        </Link>
                                    </Button>
                                )}
                                <Button asChild variant="ghost" size="lg">
                                    <a href="#pricing">
                                        See what&apos;s included
                                    </a>
                                </Button>
                            </div>
                            <p className="mt-4 text-xs text-muted-foreground">
                                No card required.
                            </p>

                            <BoardPreview />
                        </div>
                    </section>

                    <section className="border-y bg-muted/40 py-20">
                        <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                            <h2 className="text-center text-3xl font-bold tracking-tight">
                                From estimate to invoice
                            </h2>
                            <div className="mt-12 grid gap-6 md:grid-cols-3">
                                {steps.map((step, index) => (
                                    <div key={step.title} className="text-center">
                                        <div className="mx-auto flex size-12 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                                            <step.icon className="size-6" />
                                        </div>
                                        <p className="mt-4 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                            Step {index + 1}
                                        </p>
                                        <h3 className="mt-1 text-lg font-semibold">
                                            {step.title}
                                        </h3>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {step.text}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="py-20">
                        <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                            <h2 className="text-center text-3xl font-bold tracking-tight">
                                Everything a small shop needs to quote, track
                                and get paid
                            </h2>
                            <div className="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                {features.map((feature) => (
                                    <Card key={feature.key} className="p-6">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                <ClipboardList className="size-5" />
                                            </div>
                                            {!feature.shipped && (
                                                <Badge variant="warning">
                                                    Coming soon
                                                </Badge>
                                            )}
                                        </div>
                                        <h3 className="mt-4 font-semibold">
                                            {feature.label}
                                        </h3>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {feature.tagline}
                                        </p>
                                    </Card>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section id="pricing" className="border-t bg-muted/40 py-20">
                        <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                            <h2 className="text-center text-3xl font-bold tracking-tight">
                                One plan to try,{' '}
                                {tiers.length < numberWords.length
                                    ? numberWords[tiers.length]
                                    : 'many'}{' '}
                                to grow into
                            </h2>
                            <p className="mx-auto mt-3 max-w-2xl text-center text-sm text-muted-foreground">
                                Every plan starts with a full-access,{' '}
                                {trialDays}-day free trial - no card required.
                                Pick Solo, Shop or Pro when the trial ends,
                                or keep exploring on the free trial until you're
                                ready.
                            </p>

                            <div className="mt-12 grid grid-cols-1 items-stretch gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                {tiers.map((tier) => (
                                    <TierCard
                                        key={tier.key}
                                        tier={tier}
                                        features={features}
                                        canRegister={canRegister}
                                        trialDays={trialDays}
                                    />
                                ))}
                            </div>
                        </div>
                    </section>

                    {canRegister && (
                        <section className="py-20">
                            <div className="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
                                <h2 className="text-3xl font-bold tracking-tight">
                                    Try it on your next quote
                                </h2>
                                <p className="mt-3 text-muted-foreground">
                                    {trialDays} days, every feature, no card.
                                </p>
                                <Button asChild size="lg" className="mt-8">
                                    <Link href={route('register')}>
                                        Start free trial
                                        <ArrowRight />
                                    </Link>
                                </Button>
                            </div>
                        </section>
                    )}
                </main>

                <footer className="border-t py-8">
                    <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 text-sm text-muted-foreground sm:flex-row sm:px-6 lg:px-8">
                        <span className="flex items-center gap-2">
                            <ApplicationLogo className="size-5 fill-current" />
                            Fab Shop
                        </span>
                        <LegalLinks />
                        {canLogin && (
                            <Link
                                href={route('login')}
                                className="hover:text-foreground"
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
