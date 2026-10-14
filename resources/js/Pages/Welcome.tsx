import ApplicationLogo from '@/Components/ApplicationLogo';
import ProductGlimpse from '@/Components/marketing/ProductGlimpse';
import FlowingBackground from '@/Components/marketing/FlowingBackground';
import Reveal from '@/Components/marketing/Reveal';
import LegalLinks from '@/Components/LegalLinks';
import ThemeToggle from '@/Components/ThemeToggle';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { formatCalendarDay } from '@/lib/datetime';
import { formatCents } from '@/lib/money';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Bot,
    Check,
    ClipboardList,
    FileSpreadsheet,
    FileText,
    Hammer,
    History,
    LucideIcon,
    Mail,
    Minus,
    PackageSearch,
    Receipt,
    ShieldCheck,
    Sparkles,
    Store,
    TriangleAlert,
    Users,
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
    userLimit: number | null;
    isTrialOnly: boolean;
    monthlyPriceCents: number;
    extraSeatPriceCents: number | null;
    savingsCents: number;
    /** Set only when an admin chose to advertise when the tier leaves the list. */
    availableUntil: string | null;
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

const featureIcons: Record<string, LucideIcon> = {
    customers: Users,
    'quote-builder': FileText,
    'job-board': Hammer,
    'material-tracking': PackageSearch,
    'qualification-records': ShieldCheck,
    'quote-history': History,
    'quote-emailing': Mail,
    'ai-assist': Sparkles,
    'ai-agent': Bot,
};

// What the app does beyond the plan-gated feature cards. Everything here is
// built and shipped - keep it that way (see Feature::isShipped()).
const highlights = [
    {
        icon: Sparkles,
        title: 'Paste an email, get a draft quote',
        text: 'Drop in a customer message and the AI suggests lines from your own catalog. It picks the items; your prices and markup fill in, so it never invents a number.',
    },
    {
        icon: FileSpreadsheet,
        title: 'Bring your price lists with you',
        text: 'Import customers, materials, tooling and supplier price sheets from CSV, with column mapping for whatever your supplier exports.',
    },
    {
        icon: ShieldCheck,
        title: 'Control who sees what',
        text: 'Per-person access down to single actions. Keep markup and financials to yourself, and let the crew see only the jobs.',
    },
    {
        icon: Store,
        title: 'More than one shop? One login',
        text: 'Switch between shops from the menu. Each keeps its own customers, catalog, team and numbers.',
    },
];

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
                {tier.userLimit === null
                    ? 'Unlimited users'
                    : `Up to ${tier.userLimit} user${tier.userLimit === 1 ? '' : 's'}`}
            </p>
            {tier.availableUntil && (
                <p className="mt-1 text-xs font-medium text-amber-700">
                    Available until {formatCalendarDay(tier.availableUntil)}
                </p>
            )}

            {!tier.isTrialOnly && (
                <div className="mt-3">
                    <span className="text-3xl font-bold tracking-tight">
                        {formatCents(tier.monthlyPriceCents)}
                    </span>
                    <span className="text-sm text-muted-foreground">/mo</span>
                    {tier.extraSeatPriceCents !== null && (
                        <p className="mt-1 text-xs text-muted-foreground">
                            Extra seats {formatCents(tier.extraSeatPriceCents)}/mo each
                        </p>
                    )}
                    {tier.savingsCents > 0 && (
                        <p className="mt-1 text-xs font-medium text-green-700">
                            Save {formatCents(tier.savingsCents)}/mo bundled vs.
                            buying these features separately
                        </p>
                    )}
                </div>
            )}

            {tier.isTrialOnly ? (
                <p className="mt-4 flex-1 rounded-md bg-indigo-50 px-3 py-2 text-sm text-indigo-900 dark:bg-gray-800 dark:text-indigo-50">
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
                    <section className="relative isolate overflow-hidden">
                        <div
                            aria-hidden="true"
                            className="absolute inset-x-0 top-0 -z-10 h-[32rem] bg-linear-to-b from-indigo-100/70 to-transparent dark:from-indigo-950/60"
                        />
                        <FlowingBackground />
                        <div className="mx-auto max-w-6xl px-4 pb-16 pt-20 text-center sm:px-6 sm:pt-28 lg:px-8">
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
                                it ships - with an AI assistant that drafts the
                                paperwork from your own catalog.
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

                            <ProductGlimpse />
                        </div>
                    </section>

                    <section className="border-y bg-muted/40 py-20">
                        <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                            <h2 className="text-center text-3xl font-bold tracking-tight">
                                From estimate to invoice
                            </h2>
                            <div className="mt-12 grid gap-6 md:grid-cols-3">
                                {steps.map((step, index) => (
                                    <Reveal key={step.title} delay={index * 0.12} className="text-center">
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
                                    </Reveal>
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
                                {features.map((feature, index) => {
                                    const Icon = featureIcons[feature.key] ?? ClipboardList;

                                    return (
                                    <Reveal key={feature.key} delay={(index % 3) * 0.08} className="h-full">
                                    <Card className="h-full p-6 transition-all hover:-translate-y-1 hover:shadow-lg">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                <Icon className="size-5" />
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
                                    </Reveal>
                                    );
                                })}
                            </div>

                            <div className="mt-16 grid gap-6 sm:grid-cols-2">
                                {highlights.map((item, index) => (
                                    <Reveal key={item.title} delay={(index % 2) * 0.1}>
                                        <div className="flex gap-4 rounded-xl border bg-muted/40 p-5">
                                            <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                                <item.icon className="size-5" />
                                            </div>
                                            <div>
                                                <h3 className="font-semibold">{item.title}</h3>
                                                <p className="mt-1 text-sm text-muted-foreground">{item.text}</p>
                                            </div>
                                        </div>
                                    </Reveal>
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
