import { UserAvatar } from '@/Components/Avatar';
import PageHelp from '@/Components/PageHelp';
import SecondaryButton from '@/Components/SecondaryButton';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    addMonths,
    dayOfMonth,
    fromDay,
    monthStart,
    shortDate,
    toDay,
} from '@/lib/dates';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { CSSProperties } from 'react';

type Range = 'week' | 'month' | 'year';

interface Filters {
    range: Range;
    date: string;
    stages: string[];
    overdue: boolean;
}

interface TimelineJob {
    id: number;
    job_number: string;
    customer: string;
    stage_label: string;
    assignee: string | null;
    assignee_avatar_url: string | null;
    started_on: string;
    ended_on: string | null;
    due_date: string | null;
    /** Only present for members who may see financials. */
    amount?: number;
    profit?: number;
}

interface Financials {
    jobs: number;
    amount: number;
    cost: number;
    profit: number;
    margin_percent: number | null;
}

const currency = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 0,
});

const profitClass = (profit: number) =>
    profit < 0 ? 'text-red-600' : 'text-foreground';

// Same hue vocabulary as the board's cards and the jobs calendar.
const DONE_HUE = 140;
const IN_PROGRESS_HUE = 50;
const LATE_HUE = 6;

export default function JobTimeline({
    filters,
    period,
    today,
    stages,
    jobs,
    financials,
}: PageProps<{
    filters: Filters;
    period: { start: string; end: string };
    today: string;
    stages: { value: string; label: string }[];
    jobs: TimelineJob[];
    /** Null without the financials permission - the money is never sent. */
    financials: Financials | null;
}>) {
    const start = toDay(period.start);
    const end = toDay(period.end);
    const todayDay = toDay(today);
    const days = Array.from({ length: end - start + 1 }, (_, i) => start + i);

    // The range and stage filter are applied by the server (it only sends
    // jobs overlapping the period), so every change is a fresh visit.
    const visit = (next: Partial<Filters>) =>
        router.get(
            route('reports.job-timeline'),
            { ...filters, ...next },
            { preserveScroll: true, preserveState: true, replace: true },
        );

    const step = (direction: -1 | 1) => {
        const anchor = toDay(filters.date);
        visit({
            date: fromDay(
                filters.range === 'week'
                    ? anchor + 7 * direction
                    : addMonths(
                          anchor,
                          filters.range === 'year' ? 12 * direction : direction,
                      ),
            ),
        });
    };

    const toggleStage = (value: string) =>
        visit({
            stages: filters.stages.includes(value)
                ? filters.stages.filter((s) => s !== value)
                : [...filters.stages, value],
        });

    const endYear = new Date(end * 86_400_000).getUTCFullYear();
    const periodLabel =
        filters.range === 'year'
            ? String(endYear)
            : `${shortDate(start)} - ${shortDate(end)}, ${endYear}`;

    // A year is too many columns to label by day: the header shows months
    // instead (each spanning its days), while bars stay at day precision.
    const months =
        filters.range === 'year'
            ? Array.from({ length: 12 }, (_, i) => {
                  const first = monthStart(addMonths(start, i));
                  const next = monthStart(addMonths(start, i + 1));
                  return { first, length: next - first };
              })
            : [];

    // With financials there is one extra fixed column (amount / profit)
    // between the job and the chart.
    const moneyRem = 8;
    const chartStart = financials ? 3 : 2;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Job timeline
                    </h2>
                    <PageHelp>
                        <p>
                            A timeline of when jobs were worked: each bar runs
                            from the day a job left Not started to the day it
                            shipped, or through today while it's in progress.
                        </p>
                        <p>
                            Jobs that haven't started yet, and cancelled jobs,
                            don't have a period and aren't shown. Pick which
                            stages to include to narrow it down.
                        </p>
                    </PageHelp>
                </div>
            }
        >
            <Head title="Job timeline" />

            <div className="py-12">
                <div className="mx-auto max-w-full space-y-4 px-4 sm:px-6 lg:px-8">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <SecondaryButton
                                aria-label="Previous period"
                                onClick={() => step(-1)}
                            >
                                ‹
                            </SecondaryButton>
                            <SecondaryButton
                                onClick={() => visit({ date: today })}
                            >
                                Today
                            </SecondaryButton>
                            <SecondaryButton
                                aria-label="Next period"
                                onClick={() => step(1)}
                            >
                                ›
                            </SecondaryButton>
                            <span className="ms-2 text-sm font-medium text-foreground">
                                {periodLabel}
                            </span>
                        </div>
                        <div
                            role="group"
                            aria-label="Time range"
                            className="inline-flex rounded-md border p-0.5"
                        >
                            {(['week', 'month', 'year'] as const).map((range) => (
                                <Button
                                    key={range}
                                    type="button"
                                    size="sm"
                                    variant={
                                        filters.range === range
                                            ? 'default'
                                            : 'ghost'
                                    }
                                    aria-pressed={filters.range === range}
                                    onClick={() => visit({ range })}
                                >
                                    {range === 'week'
                                        ? 'Week'
                                        : range === 'month'
                                          ? 'Month'
                                          : 'Year'}
                                </Button>
                            ))}
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-sm text-muted-foreground">
                            Include stages:
                        </span>
                        {stages.map((stage) => {
                            const on = filters.stages.includes(stage.value);
                            return (
                                <Button
                                    key={stage.value}
                                    type="button"
                                    size="xs"
                                    variant={on ? 'default' : 'outline'}
                                    aria-pressed={on}
                                    onClick={() => toggleStage(stage.value)}
                                >
                                    {stage.label}
                                </Button>
                            );
                        })}
                        <span className="text-xs text-muted-foreground">
                            {filters.stages.length === 0
                                ? 'All stages'
                                : `${filters.stages.length} selected`}
                        </span>
                        <Button
                            type="button"
                            size="xs"
                            variant={filters.overdue ? 'destructive' : 'outline'}
                            aria-pressed={filters.overdue}
                            className="ms-2"
                            onClick={() =>
                                visit({ overdue: !filters.overdue })
                            }
                        >
                            Overdue only
                        </Button>
                        {filters.stages.length > 0 && (
                            <Button
                                type="button"
                                size="xs"
                                variant="ghost"
                                onClick={() => visit({ stages: [] })}
                            >
                                Clear
                            </Button>
                        )}
                    </div>

                    {financials && (
                        <section
                            aria-label="Totals for the jobs shown"
                            className="space-y-2"
                        >
                            <dl className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                                <div className="rounded-lg border bg-card p-4">
                                    <dt className="text-xs text-muted-foreground">
                                        Jobs
                                    </dt>
                                    <dd className="mt-1 text-xl font-semibold tabular-nums">
                                        {financials.jobs}
                                    </dd>
                                </div>
                                <div className="rounded-lg border bg-card p-4">
                                    <dt className="text-xs text-muted-foreground">
                                        Amount
                                    </dt>
                                    <dd className="mt-1 text-xl font-semibold tabular-nums">
                                        {currency.format(financials.amount)}
                                    </dd>
                                </div>
                                <div className="rounded-lg border bg-card p-4">
                                    <dt className="text-xs text-muted-foreground">
                                        Cost
                                    </dt>
                                    <dd className="mt-1 text-xl font-semibold tabular-nums">
                                        {currency.format(financials.cost)}
                                    </dd>
                                </div>
                                <div className="rounded-lg border bg-card p-4">
                                    <dt className="text-xs text-muted-foreground">
                                        Profit
                                    </dt>
                                    <dd
                                        className={`mt-1 text-xl font-semibold tabular-nums ${profitClass(financials.profit)}`}
                                    >
                                        {currency.format(financials.profit)}
                                        {financials.margin_percent !== null && (
                                            <span className="ms-2 text-sm font-normal text-muted-foreground">
                                                {financials.margin_percent.toFixed(
                                                    1,
                                                )}
                                                %
                                            </span>
                                        )}
                                    </dd>
                                </div>
                            </dl>
                            <p className="text-xs text-muted-foreground">
                                Totals for the jobs listed below, each counted
                                in full even if only part of it falls in this
                                period. Amount is the quoted price; profit is
                                that price minus the quoted cost, so it is an
                                estimate, not what the job actually cost.
                            </p>
                        </section>
                    )}

                    {jobs.length === 0 ? (
                        <div className="rounded-lg border bg-card p-8 text-center text-sm text-muted-foreground">
                            No jobs were being worked in this period.
                        </div>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border bg-card">
                            <div
                                className="grid min-w-[36rem]"
                                style={{
                                    // Day columns share whatever width is
                                    // left (minmax(0, 1fr)), so the chart
                                    // always fits the page - a year just
                                    // gets thin columns. Only a phone-width
                                    // screen scrolls.
                                    gridTemplateColumns: `13rem ${financials ? `${moneyRem}rem ` : ''}repeat(${days.length}, minmax(0, 1fr))`,
                                }}
                            >
                                <div className="border-b px-3 py-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    Job
                                </div>
                                {financials && (
                                    <div className="border-b px-3 py-2 text-right text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                        Amount / profit
                                    </div>
                                )}
                                {filters.range === 'year' &&
                                    months.map((month) => {
                                        const current =
                                            todayDay >= month.first &&
                                            todayDay < month.first + month.length;

                                        return (
                                            <div
                                                key={month.first}
                                                style={{
                                                    gridColumn: `span ${month.length}`,
                                                }}
                                                className={`truncate border-b border-s px-1 py-2 text-center text-xs ${current ? 'bg-primary/10 font-bold text-primary' : 'text-muted-foreground'}`}
                                            >
                                                {new Date(
                                                    month.first * 86_400_000,
                                                ).toLocaleDateString('en-US', {
                                                    month: 'short',
                                                    timeZone: 'UTC',
                                                })}
                                            </div>
                                        );
                                    })}
                                {filters.range !== 'year' &&
                                    days.map((day) => (
                                    <div
                                        key={day}
                                        className={`truncate border-b border-s px-1 py-2 text-center text-xs ${day === todayDay ? 'bg-primary/10 font-bold text-primary' : 'text-muted-foreground'}`}
                                    >
                                        {filters.range === 'week'
                                            ? shortDate(day)
                                            : dayOfMonth(day)}
                                    </div>
                                    ))}

                                {jobs.map((job) => {
                                    const jobStart = toDay(job.started_on);
                                    const jobEnd = job.ended_on
                                        ? toDay(job.ended_on)
                                        : Math.max(todayDay, jobStart);
                                    const dueDay = job.due_date
                                        ? toDay(job.due_date)
                                        : null;
                                    // Late = still open past its due date, or
                                    // it shipped after it.
                                    const late =
                                        dueDay !== null &&
                                        (job.ended_on
                                            ? toDay(job.ended_on) > dueDay
                                            : todayDay > dueDay);
                                    const from = Math.max(jobStart, start);
                                    const to = Math.min(jobEnd, end);

                                    return (
                                        <div key={job.id} className="contents">
                                            <div className="truncate border-b px-3 py-2 text-sm">
                                                <Link
                                                    href={route(
                                                        'jobs.show',
                                                        job.id,
                                                    )}
                                                    className="font-semibold text-indigo-600 hover:underline"
                                                >
                                                    {job.job_number}
                                                </Link>{' '}
                                                <span className="text-muted-foreground">
                                                    {job.customer}
                                                </span>
                                                <div className="text-xs text-muted-foreground">
                                                    {job.stage_label}
                                                    {job.assignee && (
                                                        <>
                                                            {' · '}
                                                            <UserAvatar
                                                                name={job.assignee}
                                                                src={job.assignee_avatar_url}
                                                                className="mx-0.5 size-4 align-text-bottom"
                                                            />{' '}
                                                            {job.assignee}
                                                        </>
                                                    )}
                                                    {dueDay !== null && (
                                                        <span
                                                            className={
                                                                late
                                                                    ? 'font-semibold text-red-600'
                                                                    : ''
                                                            }
                                                        >
                                                            {` · Due ${shortDate(dueDay)}`}
                                                            {late &&
                                                                (job.ended_on
                                                                    ? ' (shipped late)'
                                                                    : ' (overdue)')}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                            {financials && (
                                                <div className="border-b px-3 py-2 text-right text-sm tabular-nums">
                                                    <div>
                                                        {currency.format(
                                                            job.amount ?? 0,
                                                        )}
                                                    </div>
                                                    <div
                                                        className={`text-xs ${profitClass(job.profit ?? 0)}`}
                                                    >
                                                        {currency.format(
                                                            job.profit ?? 0,
                                                        )}{' '}
                                                        profit
                                                    </div>
                                                </div>
                                            )}
                                            <div
                                                className="grid items-center border-b"
                                                style={{
                                                    gridColumn: `${chartStart} / span ${days.length}`,
                                                    gridTemplateColumns: `repeat(${days.length}, minmax(0, 1fr))`,
                                                }}
                                            >
                                                <div
                                                    title={`${job.job_number}: ${shortDate(jobStart)} - ${job.ended_on ? shortDate(jobEnd) : 'in progress'}`}
                                                    className={`job-card h-6 border ${jobStart < start ? 'rounded-s-none border-s-0' : 'rounded-s-md'} ${jobEnd > end ? 'rounded-e-none border-e-0' : 'rounded-e-md'}`}
                                                    style={
                                                        {
                                                            '--card-hue': late
                                                                ? LATE_HUE
                                                                : job.ended_on
                                                                  ? DONE_HUE
                                                                  : IN_PROGRESS_HUE,
                                                            gridColumn: `${from - start + 1} / ${to - start + 2}`,
                                                            gridRow: 1,
                                                        } as CSSProperties
                                                    }
                                                />
                                                {dueDay !== null &&
                                                    dueDay >= start &&
                                                    dueDay <= end && (
                                                        <div
                                                            title={`Due ${shortDate(dueDay)}`}
                                                            className={`pointer-events-none z-10 h-8 w-0.5 justify-self-end ${late ? 'bg-red-600' : 'bg-foreground/60'}`}
                                                            style={{
                                                                gridColumn:
                                                                    dueDay -
                                                                    start +
                                                                    1,
                                                                gridRow: 1,
                                                            }}
                                                        />
                                                    )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    <p className="text-xs text-muted-foreground">
                        <span className="me-3 inline-flex items-center gap-1">
                            <span
                                className="job-card inline-block h-3 w-3 rounded-sm border"
                                style={
                                    {
                                        '--card-hue': IN_PROGRESS_HUE,
                                    } as CSSProperties
                                }
                            />
                            In progress
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <span
                                className="job-card inline-block h-3 w-3 rounded-sm border"
                                style={
                                    { '--card-hue': DONE_HUE } as CSSProperties
                                }
                            />
                            Shipped
                        </span>
                        <span className="ms-3 inline-flex items-center gap-1">
                            <span
                                className="job-card inline-block h-3 w-3 rounded-sm border"
                                style={
                                    { '--card-hue': LATE_HUE } as CSSProperties
                                }
                            />
                            Overdue / shipped late
                        </span>
                        <span className="ms-3 inline-flex items-center gap-1">
                            <span className="inline-block h-3 w-0.5 bg-foreground/60" />
                            Due date
                        </span>
                    </p>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
