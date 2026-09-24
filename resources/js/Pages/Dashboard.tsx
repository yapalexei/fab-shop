import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ReactNode } from 'react';

interface CountAndValue {
    count: number;
    value: number;
}

interface Kpis {
    invoiced_this_month: number;
    invoiced_last_month: number;
    work_in_progress: CountAndValue;
    ready_to_invoice: CountAndValue;
    open_quotes: CountAndValue;
    win_rate: number | null;
    win_rate_window_days: number;
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
    amount: number;
    href: string;
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
        <section className="rounded-lg bg-white p-6 shadow-sm">
            <div className="mb-4 flex items-center justify-between">
                <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-500">
                    {title}
                </h3>
                {action}
            </div>
            {children}
        </section>
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
            className="block rounded-lg bg-white p-5 shadow-sm transition hover:shadow-md"
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
                            {month.total > 0 ? currency.format(month.total) : ''}
                        </span>
                        <div
                            className={`w-full rounded-t ${isCurrent ? 'bg-indigo-500' : 'bg-indigo-200'}`}
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
        <div className="grid grid-cols-3 gap-2 sm:grid-cols-6">
            {stages.map((stage) => (
                <Link
                    key={stage.status}
                    href={route('jobs.index')}
                    className={`rounded-md p-3 text-center transition hover:ring-2 hover:ring-indigo-200 ${stage.count > 0 ? 'bg-indigo-50' : 'bg-gray-50'}`}
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

export default function Dashboard({
    kpis,
    revenueTrend,
    pipeline,
    attention,
    recentActivity,
}: PageProps<{
    kpis: Kpis;
    revenueTrend: RevenueMonth[];
    pipeline: PipelineStage[];
    attention: AttentionItem[];
    recentActivity: ActivityEntry[];
}>) {
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
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <KpiCard
                            label="Invoiced this month"
                            value={currency.format(kpis.invoiced_this_month)}
                            detail={monthOverMonth(
                                kpis.invoiced_this_month,
                                kpis.invoiced_last_month,
                            )}
                            href={route('jobs.index')}
                        />
                        <KpiCard
                            label="Ready to invoice"
                            value={currency.format(kpis.ready_to_invoice.value)}
                            detail={`${kpis.ready_to_invoice.count} shipped job${kpis.ready_to_invoice.count === 1 ? '' : 's'}`}
                            href={route('jobs.index')}
                        />
                        <KpiCard
                            label="Work in progress"
                            value={currency.format(kpis.work_in_progress.value)}
                            detail={`${kpis.work_in_progress.count} job${kpis.work_in_progress.count === 1 ? '' : 's'} in the shop`}
                            href={route('jobs.index')}
                        />
                        <KpiCard
                            label="Open quotes"
                            value={currency.format(kpis.open_quotes.value)}
                            detail={
                                <>
                                    {kpis.open_quotes.count} awaiting reply
                                    {kpis.win_rate !== null &&
                                        ` · ${kpis.win_rate}% win rate (${kpis.win_rate_window_days}d)`}
                                </>
                            }
                            href={route('quotes.index')}
                        />
                    </div>

                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <div className="lg:col-span-2">
                            <Panel title="Invoiced revenue">
                                <RevenueChart months={revenueTrend} />
                            </Panel>
                        </div>

                        <Panel title="Needs attention">
                            {attention.length === 0 ? (
                                <p className="text-sm text-gray-500">
                                    Nothing waiting on you right now.
                                </p>
                            ) : (
                                <ul className="-my-2 max-h-56 divide-y divide-gray-100 overflow-y-auto">
                                    {attention.map((item) => (
                                        <li key={`${item.kind}-${item.href}`}>
                                            <Link
                                                href={item.href}
                                                className="flex items-start gap-3 py-2 hover:bg-gray-50"
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
                                                <span className="text-sm text-gray-500">
                                                    {currency.format(item.amount)}
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Panel>
                    </div>

                    <Panel
                        title="Jobs by stage"
                        action={
                            <Link
                                href={route('jobs.index')}
                                className="text-sm text-indigo-600 hover:text-indigo-900"
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
                            <ul className="divide-y divide-gray-100">
                                {recentActivity.map((entry) => (
                                    <li
                                        key={entry.id}
                                        className="flex flex-wrap items-baseline justify-between gap-x-4 py-2 text-sm"
                                    >
                                        <span className="text-gray-700">
                                            {entry.href ? (
                                                <Link
                                                    href={entry.href}
                                                    className="font-medium text-indigo-600 hover:text-indigo-900"
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
        </AuthenticatedLayout>
    );
}
