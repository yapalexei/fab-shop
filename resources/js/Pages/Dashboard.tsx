import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ReactNode, useState } from 'react';
import FollowUpDraftDialog from '@/Components/FollowUpDraftDialog';
import { Card } from '@/Components/ui/card';
import GettingStartedBanner, {
    GettingStartedStep,
} from '@/Components/GettingStartedBanner';

interface Counts {
    work_in_progress: number;
    ready_to_invoice: number;
    open_quotes: number;
}

// Only sent to people with the "See financials" permission.
interface Financials {
    invoiced_this_month: number;
    invoiced_last_month: number;
    work_in_progress: number;
    ready_to_invoice: number;
    open_quotes: number;
    win_rate: number | null;
    win_rate_window_days: number;
    revenue_trend: RevenueMonth[];
}

interface RevenueMonth {
    month: string;
    label: string;
    total: number;
}

interface PipelineStage {
    status: string;
    label: string;
    count: number;
}

interface AttentionItem {
    kind: 'ready_to_invoice' | 'sync_error' | 'stale_quote';
    title: string;
    subtitle: string | null;
    amount: number | null;
    href: string;
    /** Set on stale-quote items: the quote a follow-up can be drafted for. */
    quote_id?: number;
    customer?: string;
}

interface ActivityEntry {
    id: number;
    description: string;
    causer: string | null;
    job_number: string | null;
    href: string | null;
    created_at: string;
}

const currency = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 0,
});

const attentionStyles: Record<AttentionItem['kind'], string> = {
    sync_error: 'bg-red-500',
    ready_to_invoice: 'bg-green-500',
    stale_quote: 'bg-yellow-400',
};

function timeAgo(iso: string): string {
    const seconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];
    const format = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

    for (const [unit, size] of units) {
        if (seconds >= size) {
            return format.format(-Math.floor(seconds / size), unit);
        }
    }

    return 'just now';
}

function Panel({
    title,
    action,
    children,
}: {
    title: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <Card className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-500">
                    {title}
                </h3>
                {action}
            </div>
            {children}
        </Card>
    );
}

function KpiCard({
    label,
    value,
    detail,
    href,
}: {
    label: string;
    value: string;
    detail: ReactNode;
    href: string;
}) {
    return (
        <Link
            href={href}
            className="block rounded-lg bg-white p-5 shadow-xs transition hover:shadow-md"
        >
            <p className="text-sm font-medium text-gray-500">{label}</p>
            <p className="mt-1 text-2xl font-semibold text-gray-900">{value}</p>
            <p className="mt-1 text-sm text-gray-500">{detail}</p>
        </Link>
    );
}

function monthOverMonth(current: number, previous: number): ReactNode {
    if (previous === 0) {
        return `${currency.format(previous)} last month`;
    }

    const change = Math.round(((current - previous) / previous) * 100);
    const colour = change >= 0 ? 'text-green-600' : 'text-red-600';

    return (
        <>
            <span className={colour}>
                {change >= 0 ? '+' : ''}
                {change}%
            </span>{' '}
            vs {currency.format(previous)} last month
        </>
    );
}

function RevenueChart({ months }: { months: RevenueMonth[] }) {
    const max = Math.max(...months.map((m) => m.total), 1);

    return (
        <div className="flex h-48 items-end gap-3">
            {months.map((month, index) => {
                const isCurrent = index === months.length - 1;

                return (
                    <div
                        key={month.month}
                        className="flex h-full flex-1 flex-col items-center justify-end gap-1"
                    >
                        <span className="text-xs text-gray-500">
                            {month.total > 0
                                ? currency.format(month.total)
                                : ''}
                        </span>
                        <div
                            className={`w-full rounded-t ${isCurrent ? 'bg-green-400' : 'bg-green-800'}`}
                            style={{
                                height: `${(month.total / max) * 100}%`,
                                minHeight: month.total > 0 ? '4px' : '1px',
                            }}
                            title={`${month.label}: ${currency.format(month.total)}`}
                        />
                        <span
                            className={`text-xs ${isCurrent ? 'font-semibold text-gray-900' : 'text-gray-500'}`}
                        >
                            {month.label}
                        </span>
                    </div>
                );
            })}
        </div>
    );
}

function Pipeline({ stages }: { stages: PipelineStage[] }) {
    return (
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
            {stages.map((stage) => (
                <Link
                    key={stage.status}
                    href={route('jobs.index')}
                    className={`rounded-md p-3 text-center transition hover:ring-2 hover:ring-indigo-200 ${stage.count > 0 ? 'bg-gray-100' : 'bg-gray-100'}`}
                >
                    <p
                        className={`text-2xl font-semibold ${stage.count > 0 ? 'text-indigo-700' : 'text-gray-300'}`}
                    >
                        {stage.count}
                    </p>
                    <p className="mt-1 text-xs text-gray-600">{stage.label}</p>
                </Link>
            ))}
        </div>
    );
}

function plural(count: number, word: string): string {
    return `${count} ${word}${count === 1 ? '' : 's'}`;
}

function FinancialKpis({
    financials,
    counts,
}: {
    financials: Financials;
    counts: Counts;
}) {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <KpiCard
                label="Invoiced this month"
                value={currency.format(financials.invoiced_this_month)}
                detail={monthOverMonth(
                    financials.invoiced_this_month,
                    financials.invoiced_last_month,
                )}
                href={route('jobs.index')}
            />
            <KpiCard
                label="Ready to invoice"
                value={currency.format(financials.ready_to_invoice)}
                detail={plural(counts.ready_to_invoice, 'shipped job')}
                href={route('jobs.index', { focus: 'shipped' })}
            />
            <KpiCard
                label="Work in progress"
                value={currency.format(financials.work_in_progress)}
                detail={`${plural(counts.work_in_progress, 'job')} in the shop`}
                href={route('jobs.index')}
            />
            <KpiCard
                label="Open quotes"
                value={currency.format(financials.open_quotes)}
                detail={
                    <>
                        {counts.open_quotes} awaiting reply
                        {financials.win_rate !== null &&
                            ` · ${financials.win_rate}% win rate (${financials.win_rate_window_days}d)`}
                    </>
                }
                href={route('quotes.index')}
            />
        </div>
    );
}

function CountKpis({ counts }: { counts: Counts }) {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <KpiCard
                label="In the shop"
                value={counts.work_in_progress.toString()}
                detail="Not started through Finishing"
                href={route('jobs.index')}
            />
            <KpiCard
                label="Shipped, not yet invoiced"
                value={counts.ready_to_invoice.toString()}
                detail={plural(counts.ready_to_invoice, 'job')}
                href={route('jobs.index', { focus: 'shipped' })}
            />
            <KpiCard
                label="Open quotes"
                value={counts.open_quotes.toString()}
                detail="Sent and awaiting a reply"
                href={route('quotes.index')}
            />
        </div>
    );
}

export default function Dashboard({
    counts,
    financials,
    pipeline,
    attention,
    recentActivity,
    gettingStarted,
    ai,
}: PageProps<{
    counts: Counts;
    financials: Financials | null;
    pipeline: PipelineStage[];
    attention: AttentionItem[];
    recentActivity: ActivityEntry[];
    gettingStarted: GettingStartedStep[] | null;
    ai: { available: boolean; configured: boolean };
}>) {
    const [followUp, setFollowUp] = useState<AttentionItem | null>(null);

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {gettingStarted && (
                        <GettingStartedBanner steps={gettingStarted} />
                    )}

                    {financials ? (
                        <FinancialKpis
                            financials={financials}
                            counts={counts}
                        />
                    ) : (
                        <CountKpis counts={counts} />
                    )}

                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        {financials && (
                            <div className="lg:col-span-2">
                                <Panel title="Invoiced revenue">
                                    <RevenueChart
                                        months={financials.revenue_trend}
                                    />
                                </Panel>
                            </div>
                        )}

                        <div className={financials ? '' : 'lg:col-span-3'}>
                            <Panel title="Needs attention">
                                {attention.length === 0 ? (
                                    <p className="text-sm text-gray-500">
                                        Nothing waiting on you right now.
                                    </p>
                                ) : (
                                    <ul className="-my-2 max-h-56 divide-y overflow-y-auto">
                                        {attention.map((item) => (
                                            <li
                                                key={`${item.kind}-${item.href}`}
                                            >
                                                <Link
                                                    href={item.href}
                                                    className="flex items-start gap-3 py-2 hover:bg-accent"
                                                >
                                                    <span
                                                        className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${attentionStyles[item.kind]}`}
                                                    />
                                                    <span className="min-w-0 flex-1">
                                                        <span className="block text-sm font-medium text-gray-900">
                                                            {item.title}
                                                        </span>
                                                        {item.subtitle && (
                                                            <span className="block truncate text-xs text-gray-500">
                                                                {item.subtitle}
                                                            </span>
                                                        )}
                                                    </span>
                                                    {item.amount !== null && (
                                                        <span className="text-sm text-gray-500">
                                                            {currency.format(
                                                                item.amount,
                                                            )}
                                                        </span>
                                                    )}
                                                </Link>
                                                {item.kind === 'stale_quote' &&
                                                    item.quote_id &&
                                                    ai.available &&
                                                    ai.configured && (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setFollowUp(item)
                                                            }
                                                            className="mb-2 ml-5 text-xs font-medium text-indigo-600 hover:underline"
                                                        >
                                                            Draft follow-up
                                                        </button>
                                                    )}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Panel>
                        </div>
                    </div>

                    <Panel
                        title="Jobs by stage"
                        action={
                            <Link
                                href={route('jobs.index')}
                                className="text-sm text-indigo-600 hover:underline"
                            >
                                Open board
                            </Link>
                        }
                    >
                        <Pipeline stages={pipeline} />
                    </Panel>

                    <Panel title="Recent job activity">
                        {recentActivity.length === 0 ? (
                            <p className="text-sm text-gray-500">
                                No job activity yet - win a quote and convert it
                                to a job to get started.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {recentActivity.map((entry) => (
                                    <li
                                        key={entry.id}
                                        className="flex flex-wrap items-baseline justify-between gap-x-4 py-2 text-sm"
                                    >
                                        <span className="text-gray-700">
                                            {entry.href ? (
                                                <Link
                                                    href={entry.href}
                                                    className="font-medium text-indigo-600 hover:underline"
                                                >
                                                    {entry.job_number}
                                                </Link>
                                            ) : (
                                                entry.job_number
                                            )}
                                            : {entry.description}
                                            {entry.causer && (
                                                <span className="text-gray-500">
                                                    {' '}
                                                    by {entry.causer}
                                                </span>
                                            )}
                                        </span>
                                        <span className="text-xs text-gray-400">
                                            {timeAgo(entry.created_at)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>
            </div>

            {followUp?.quote_id && (
                <FollowUpDraftDialog
                    quoteId={followUp.quote_id}
                    customer={followUp.customer ?? 'customer'}
                    onClose={() => setFollowUp(null)}
                />
            )}
        </AuthenticatedLayout>
    );
}
