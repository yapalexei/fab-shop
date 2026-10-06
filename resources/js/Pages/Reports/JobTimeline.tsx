import PageHelp from '@/Components/PageHelp';
import SecondaryButton from '@/Components/SecondaryButton';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { addMonths, dayOfMonth, fromDay, shortDate, toDay } from '@/lib/dates';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { CSSProperties } from 'react';

type Range = 'week' | 'month';

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
    started_on: string;
    ended_on: string | null;
    due_date: string | null;
}

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
}: PageProps<{
    filters: Filters;
    period: { start: string; end: string };
    today: string;
    stages: { value: string; label: string }[];
    jobs: TimelineJob[];
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
                    : addMonths(anchor, direction),
            ),
        });
    };

    const toggleStage = (value: string) =>
        visit({
            stages: filters.stages.includes(value)
                ? filters.stages.filter((s) => s !== value)
                : [...filters.stages, value],
        });

    const periodLabel = `${shortDate(start)} - ${shortDate(end)}, ${new Date(end * 86_400_000).getUTCFullYear()}`;

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
                            {(['week', 'month'] as const).map((range) => (
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
                                    {range === 'week' ? 'Week' : 'Month'}
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

                    {jobs.length === 0 ? (
                        <div className="rounded-lg border bg-card p-8 text-center text-sm text-muted-foreground">
                            No jobs were being worked in this period.
                        </div>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border bg-card">
                            <div
                                className="grid min-w-[48rem]"
                                style={{
                                    gridTemplateColumns: `14rem repeat(${days.length}, minmax(${filters.range === 'week' ? '4rem' : '1.5rem'}, 1fr))`,
                                }}
                            >
                                <div className="border-b px-3 py-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    Job
                                </div>
                                {days.map((day) => (
                                    <div
                                        key={day}
                                        className={`border-b border-s px-1 py-2 text-center text-xs ${day === todayDay ? 'bg-primary/10 font-bold text-primary' : 'text-muted-foreground'}`}
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
                                                    {job.assignee
                                                        ? ` · ${job.assignee}`
                                                        : ''}
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
                                            <div
                                                className="grid items-center border-b"
                                                style={{
                                                    gridColumn: `2 / span ${days.length}`,
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
