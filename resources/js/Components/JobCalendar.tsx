import SecondaryButton from '@/Components/SecondaryButton';
import {
    addMonths,
    dayOfMonth,
    monthLabel,
    monthStart,
    shortDate,
    startOfWeek,
    toDay,
    WEEKDAYS,
} from '@/lib/dates';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/Components/ui/tooltip';
import { Link } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { CSSProperties, useState } from 'react';

export interface CalendarJob {
    id: number;
    job_number: string;
    customer: string;
    cancelled: boolean;
    started_on: string | null;
    ended_on: string | null;
    stage_label: string;
    assignee: string | null;
    total: number;
    due_date: string | null;
    overdue: boolean;
}

const currency = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

/** How close to its due date an unfinished job gets flagged. */
const DUE_SOON_DAYS = 2;

/**
 * Things the viewer should notice about a job: past due, due very soon, or
 * shipped after its due date. Empty when there's nothing to flag.
 */
function warnings(job: CalendarJob, today: number): string[] {
    if (!job.due_date) return [];

    const due = toDay(job.due_date);
    const found: string[] = [];

    if (job.ended_on) {
        if (toDay(job.ended_on) > due) {
            found.push(
                `Shipped ${toDay(job.ended_on) - due} day(s) after its due date`,
            );
        }
    } else if (job.overdue) {
        found.push(`Overdue by ${today - due} day(s)`);
    } else if (due - today <= DUE_SOON_DAYS) {
        found.push(
            due === today ? 'Due today' : `Due in ${due - today} day(s)`,
        );
    }

    return found;
}

function JobTooltip({ job, today }: { job: CalendarJob; today: number }) {
    const flags = warnings(job, today);

    return (
        <div className="space-y-1 text-start">
            <div className="font-semibold">
                {job.job_number} - {job.customer}
            </div>
            <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5">
                <dt className="opacity-70">Status</dt>
                <dd>
                    {job.stage_label}
                    {!job.ended_on && ' (in progress)'}
                </dd>
                <dt className="opacity-70">Started</dt>
                <dd>{shortDate(toDay(job.started_on as string))}</dd>
                {job.ended_on && (
                    <>
                        <dt className="opacity-70">Shipped</dt>
                        <dd>{shortDate(toDay(job.ended_on))}</dd>
                    </>
                )}
                <dt className="opacity-70">Due</dt>
                <dd>
                    {job.due_date ? shortDate(toDay(job.due_date)) : 'Not set'}
                </dd>
                <dt className="opacity-70">Total</dt>
                <dd>{currency.format(job.total)}</dd>
                <dt className="opacity-70">Assigned</dt>
                <dd>{job.assignee ?? 'Unassigned'}</dd>
            </dl>
            {flags.map((flag) => (
                <div
                    key={flag}
                    className="flex items-center gap-1.5 pt-1 font-medium text-amber-300 dark:text-amber-600"
                >
                    <AlertTriangle className="size-3.5 shrink-0" aria-hidden />
                    {flag}
                </div>
            ))}
        </div>
    );
}

// Same hue vocabulary as the board's cards: green once the work is done,
// amber while it's still in progress.
const DONE_HUE = 140;
const IN_PROGRESS_HUE = 50;

interface Span {
    job: CalendarJob;
    /** 0-6 column within this week, inclusive. */
    from: number;
    to: number;
    lane: number;
    continuesBefore: boolean;
    continuesAfter: boolean;
}

/**
 * The spans of every job that touches the week starting at `weekStart`,
 * packed into lanes so overlapping jobs stack instead of covering each other.
 */
function weekSpans(
    jobs: { job: CalendarJob; start: number; end: number }[],
    weekStart: number,
): Span[] {
    const weekEnd = weekStart + 6;
    const laneEnds: number[] = [];

    return jobs
        .filter(({ start, end }) => start <= weekEnd && end >= weekStart)
        .map(({ job, start, end }) => {
            const from = Math.max(start, weekStart);
            const to = Math.min(end, weekEnd);
            let lane = laneEnds.findIndex((laneEnd) => laneEnd < from);
            if (lane === -1) lane = laneEnds.length;
            laneEnds[lane] = to;

            return {
                job,
                from: from - weekStart,
                to: to - weekStart,
                lane,
                continuesBefore: start < weekStart,
                continuesAfter: end > weekEnd,
            };
        });
}

/**
 * Jobs as multi-day events by the days they were worked: from the day they
 * started to the day they finished (or through today while in progress).
 * Jobs that haven't started - and cancelled ones - have no period, so
 * they're not on it.
 */
export default function JobCalendar({
    jobs,
    today,
}: {
    jobs: CalendarJob[];
    today: string;
}) {
    const todayDay = toDay(today);
    const [month, setMonth] = useState(() => monthStart(todayDay));

    const events = jobs
        .filter((job) => job.started_on && !job.cancelled)
        .map((job) => {
            const start = toDay(job.started_on as string);
            return {
                job,
                start,
                end: job.ended_on
                    ? Math.max(toDay(job.ended_on), start)
                    : Math.max(todayDay, start),
            };
        })
        .sort((a, b) => a.start - b.start || a.job.id - b.job.id);

    const firstWeek = startOfWeek(month);
    const lastDay = addMonths(month, 1) - 1;
    const weeks: number[] = [];
    for (let w = firstWeek; w <= lastDay; w += 7) weeks.push(w);

    return (
        <TooltipProvider delayDuration={150}>
            <div className="space-y-3">
                <div className="flex items-center justify-between gap-2">
                    <h3 className="text-lg font-semibold text-foreground">
                        {monthLabel(month)}
                    </h3>
                    <div className="flex items-center gap-2">
                        <SecondaryButton
                            aria-label="Previous month"
                            onClick={() => setMonth(addMonths(month, -1))}
                        >
                            ‹
                        </SecondaryButton>
                        <SecondaryButton
                            onClick={() => setMonth(monthStart(todayDay))}
                        >
                            Today
                        </SecondaryButton>
                        <SecondaryButton
                            aria-label="Next month"
                            onClick={() => setMonth(addMonths(month, 1))}
                        >
                            ›
                        </SecondaryButton>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <div className="min-w-[44rem] overflow-hidden rounded-lg border bg-card">
                        <div className="grid grid-cols-7 border-b text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            {WEEKDAYS.map((d) => (
                                <div key={d} className="px-2 py-1.5">
                                    {d}
                                </div>
                            ))}
                        </div>

                        {weeks.map((weekStart) => {
                            const spans = weekSpans(events, weekStart);
                            const lanes = Math.max(
                                0,
                                ...spans.map((s) => s.lane + 1),
                            );

                            return (
                                <div
                                    key={weekStart}
                                    className="relative border-b last:border-b-0"
                                >
                                    <div className="pointer-events-none absolute inset-0 grid grid-cols-7">
                                        {WEEKDAYS.map((d, i) => {
                                            const day = weekStart + i;
                                            const outside =
                                                day < month || day > lastDay;
                                            return (
                                                <div
                                                    key={d}
                                                    className={`border-s first:border-s-0 ${outside ? 'bg-muted/40' : ''} ${day === todayDay ? 'bg-primary/5' : ''}`}
                                                />
                                            );
                                        })}
                                    </div>
                                    <div
                                        className="relative grid grid-cols-7 gap-y-1 pb-1.5"
                                        style={{
                                            gridTemplateRows: `1.5rem repeat(${Math.max(lanes, 1)}, 1.5rem)`,
                                        }}
                                    >
                                        {WEEKDAYS.map((d, i) => {
                                            const day = weekStart + i;
                                            return (
                                                <div
                                                    key={d}
                                                    className={`px-2 pt-1 text-xs ${day < month || day > lastDay ? 'text-muted-foreground/60' : 'text-muted-foreground'} ${day === todayDay ? 'font-bold text-primary' : ''}`}
                                                    style={{
                                                        gridColumn: i + 1,
                                                        gridRow: 1,
                                                    }}
                                                >
                                                    {dayOfMonth(day)}
                                                </div>
                                            );
                                        })}
                                        {spans.map((span) => (
                                            <Tooltip key={span.job.id}>
                                                <TooltipTrigger asChild>
                                                    <Link
                                                        href={route(
                                                            'jobs.show',
                                                            span.job.id,
                                                        )}
                                                        className={`job-card mx-0.5 truncate border px-2 text-xs leading-6 text-foreground hover:brightness-95 ${span.continuesBefore ? 'ms-0 rounded-s-none border-s-0' : 'rounded-s-md'} ${span.continuesAfter ? 'me-0 rounded-e-none border-e-0' : 'rounded-e-md'}`}
                                                        style={
                                                            {
                                                                '--card-hue':
                                                                    span.job
                                                                        .ended_on
                                                                        ? DONE_HUE
                                                                        : IN_PROGRESS_HUE,
                                                                gridColumn: `${span.from + 1} / ${span.to + 2}`,
                                                                gridRow:
                                                                    span.lane +
                                                                    2,
                                                            } as CSSProperties
                                                        }
                                                    >
                                                        {warnings(
                                                            span.job,
                                                            todayDay,
                                                        ).length > 0 && (
                                                            <AlertTriangle
                                                                className="me-1 inline size-3 align-[-1px] text-amber-600"
                                                                aria-label="Has warnings"
                                                            />
                                                        )}
                                                        <span className="font-semibold">
                                                            {
                                                                span.job
                                                                    .job_number
                                                            }
                                                        </span>{' '}
                                                        {span.job.customer}
                                                    </Link>
                                                </TooltipTrigger>
                                                <TooltipContent
                                                    side="top"
                                                    className="max-w-xs"
                                                >
                                                    <JobTooltip
                                                        job={span.job}
                                                        today={todayDay}
                                                    />
                                                </TooltipContent>
                                            </Tooltip>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>

                <p className="text-xs text-muted-foreground">
                    Each job runs from the day it started to the day it shipped
                    (or through today while in progress). Jobs not yet started
                    aren't shown.
                </p>
            </div>
        </TooltipProvider>
    );
}
