import JobMoveDialog from '@/Components/JobMoveDialog';
import JobStatusActions, { JobTransition } from '@/Components/JobStatusActions';
import PageHelp from '@/Components/PageHelp';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import {
    Announcements,
    DndContext,
    DragEndEvent,
    DragOverlay,
    DragStartEvent,
    KeyboardSensor,
    pointerWithin,
    PointerSensor,
    TouchSensor,
    UniqueIdentifier,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface JobRow {
    id: number;
    job_number: string;
    customer: string;
    stage: string;
    stage_label: string;
    cancelled: boolean;
    total: number;
    available_transitions: JobTransition[];
}

interface StageOption {
    value: string;
    label: string;
    role: 'not_started' | 'shipped' | 'invoiced' | null;
}

const currency = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

function JobCardBody({ job }: { job: JobRow }) {
    return (
        <>
            <div className="flex items-start justify-between">
                <Link
                    href={route('jobs.show', job.id)}
                    className="text-sm font-semibold text-indigo-600 hover:text-indigo-900"
                >
                    {job.job_number}
                </Link>
                <span className="text-sm text-gray-500">
                    {currency.format(job.total)}
                </span>
            </div>
            <p className="text-sm text-gray-700">{job.customer}</p>
        </>
    );
}

function JobCard({ job, draggable }: { job: JobRow; draggable: boolean }) {
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
        id: job.id,
        disabled: !draggable,
    });

    return (
        <div
            ref={setNodeRef}
            className={`relative space-y-2 rounded-md border border-gray-200 bg-white p-3 shadow-sm ${isDragging ? 'opacity-40' : ''} ${draggable ? 'ps-7' : ''}`}
        >
            {draggable && (
                // Only the handle starts a drag, so links/buttons on the card
                // and touch-scrolling the board keep working normally.
                <button
                    type="button"
                    {...listeners}
                    {...attributes}
                    aria-label={`Drag ${job.job_number} to another station`}
                    className="absolute inset-y-0 start-0 flex w-6 cursor-grab touch-none items-center justify-center rounded-s-md text-gray-300 hover:bg-gray-50 hover:text-gray-500 active:cursor-grabbing"
                >
                    <svg
                        className="h-4 w-4"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path d="M7 4a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm0 6a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm-1.5 7.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM16 4a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm-1.5 7.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM16 16a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                    </svg>
                </button>
            )}
            <JobCardBody job={job} />
            <JobStatusActions
                job={job}
                transitions={job.available_transitions}
            />
        </div>
    );
}

function Column({
    stage,
    jobs,
    draggingJob,
    dragEnabled,
}: {
    stage: StageOption;
    jobs: JobRow[];
    draggingJob: JobRow | null;
    dragEnabled: boolean;
}) {
    const { setNodeRef, isOver } = useDroppable({ id: stage.value });

    const isSource = draggingJob?.stage === stage.value;
    const isValidTarget =
        draggingJob?.available_transitions.some(
            (t) => t.value === stage.value,
        ) ?? false;

    // While dragging, legal destinations light up and everything else dims,
    // so it's obvious where a job can (and can't) go.
    const dropStyle = !draggingJob
        ? 'bg-gray-50'
        : isValidTarget
          ? isOver
              ? 'bg-indigo-100 ring-2 ring-indigo-500'
              : 'bg-indigo-50 ring-2 ring-indigo-200'
          : isSource
            ? 'bg-gray-50'
            : 'bg-gray-50 opacity-50';

    return (
        <div className="overflow-hidden rounded-lg border border-gray-300 shadow-sm">
            <div className="flex items-center justify-between border-b border-gray-300 bg-gray-100 px-3 py-2">
                <h3 className="text-xs font-semibold uppercase tracking-wider text-gray-700">
                    {stage.label}
                </h3>
                <span className="rounded-full bg-white px-2 py-0.5 text-xs font-medium text-gray-500">
                    {jobs.length}
                </span>
            </div>
            <div
                ref={setNodeRef}
                className={`min-h-[28rem] space-y-3 p-2 transition ${dropStyle}`}
            >
                {jobs.map((job) => (
                    <JobCard
                        key={job.id}
                        job={job}
                        // No handle on a card with nowhere to go (e.g. an
                        // Invoiced job, which can only be cancelled).
                        draggable={
                            dragEnabled &&
                            job.available_transitions.some(
                                (t) => t.value !== 'cancelled',
                            )
                        }
                    />
                ))}
            </div>
        </div>
    );
}

/**
 * Why a drop was refused - mirrors Job::canTransitionTo's rules: any later
 * stage is a legal forward move (skipping stages a job doesn't need is
 * allowed on purpose), backward only while the job hasn't shipped yet, plus
 * the user simply not being allowed to move jobs at all.
 */
function invalidMoveReason(
    job: JobRow,
    target: string,
    columns: StageOption[],
): string {
    const from = columns.findIndex((s) => s.value === job.stage);
    const to = columns.findIndex((s) => s.value === target);
    const shipped = columns.findIndex((s) => s.role === 'shipped');

    if (to < from && shipped !== -1 && from >= shipped) {
        return 'once a job ships it can only move forward or be cancelled.';
    }

    return "you don't have permission to make that move.";
}

export default function Index({
    jobs,
    stages,
    dragEnabled,
}: PageProps<{
    jobs: JobRow[];
    stages: StageOption[];
    dragEnabled: boolean;
}>) {
    const cancelledJobs = jobs.filter((j) => j.cancelled);
    const shopId = usePage().props.auth.user.current_shop_id;

    const [draggingJob, setDraggingJob] = useState<JobRow | null>(null);
    const [pendingMove, setPendingMove] = useState<{
        job: JobRow;
        transition: JobTransition;
    } | null>(null);
    const [dropHint, setDropHint] = useState<string | null>(null);

    // Mouse starts dragging after a small movement (so clicks still click);
    // touch needs a short press-and-hold (so swiping still scrolls the board).
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(TouchSensor, {
            activationConstraint: { delay: 200, tolerance: 8 },
        }),
        useSensor(KeyboardSensor),
    );

    // Another user moving a job refetches the board instead of polling -
    // the broadcast itself carries no data, it's just a "go fetch" signal.
    useEffect(() => {
        if (!shopId) return;

        const channel = window.Echo.private(`shop.${shopId}.jobs`).listen(
            '.JobBoardUpdated',
            () => router.reload({ only: ['jobs'] }),
        );

        return () => {
            channel.stopListening('.JobBoardUpdated');
            window.Echo.leave(`shop.${shopId}.jobs`);
        };
    }, [shopId]);

    useEffect(() => {
        if (!dropHint) return;
        const timer = setTimeout(() => setDropHint(null), 4000);
        return () => clearTimeout(timer);
    }, [dropHint]);

    // Screen-reader announcements in the shop's own terms, instead of
    // dnd-kit's default "Draggable item 50 was dropped over droppable area".
    const jobNumber = (id: UniqueIdentifier) =>
        jobs.find((j) => j.id === id)?.job_number ?? 'Job';
    const stationLabel = (id?: UniqueIdentifier) =>
        stages.find((s) => s.value === id)?.label ?? 'no station';
    const announcements: Announcements = {
        onDragStart: ({ active }) => `Picked up ${jobNumber(active.id)}.`,
        onDragOver: ({ active, over }) =>
            `${jobNumber(active.id)} is over ${stationLabel(over?.id)}.`,
        onDragEnd: ({ active, over }) =>
            `Dropped ${jobNumber(active.id)} on ${stationLabel(over?.id)}.`,
        onDragCancel: ({ active }) =>
            `Stopped dragging ${jobNumber(active.id)}.`,
    };

    const onDragStart = (event: DragStartEvent) => {
        setDropHint(null);
        setDraggingJob(jobs.find((j) => j.id === event.active.id) ?? null);
    };

    // A drop never moves the job by itself - a legal destination opens the
    // same confirmation dialog as the buttons; anything else explains why.
    const onDragEnd = (event: DragEndEvent) => {
        const job = jobs.find((j) => j.id === event.active.id);
        setDraggingJob(null);

        if (!job || !event.over || event.over.id === job.stage) return;

        const transition = job.available_transitions.find(
            (t) => t.value === event.over?.id,
        );
        const target = stages.find((s) => s.value === event.over?.id);

        if (transition) {
            setPendingMove({ job, transition });
        } else {
            setDropHint(
                `${job.job_number} can't move from ${job.stage_label} to ${target?.label ?? 'there'}: ${invalidMoveReason(job, event.over.id as string, stages)}`,
            );
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Jobs
                    </h2>
                    {jobs.length > 0 && (
                        <PageHelp>
                            <p>
                                The job board - every job converted from a won
                                quote, tracked from cutting through shipped and
                                invoiced.
                            </p>
                            <p>
                                This is the daily-use screen for the shop floor.
                                Click a job to log status changes, photos,
                                material usage, and qualification records
                                against it.
                            </p>
                        </PageHelp>
                    )}
                </div>
            }
        >
            <Head title="Jobs" />

            <div className="py-12">
                <div className="mx-auto max-w-full space-y-6 px-4 sm:px-6 lg:px-8">
                    {dropHint && (
                        <div
                            role="status"
                            className="rounded-md bg-yellow-50 p-3 text-sm text-yellow-800"
                        >
                            {dropHint}
                        </div>
                    )}

                    {jobs.length === 0 ? (
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <PageHelp empty>
                                <p>
                                    No jobs yet. This is the job board - every
                                    job converted from a won quote, tracked from
                                    cutting through shipped and invoiced.
                                </p>
                                <p>
                                    Convert a won quote to create one - go to
                                    Quotes, mark one won, then convert it.
                                </p>
                            </PageHelp>
                        </div>
                    ) : (
                        <DndContext
                            sensors={sensors}
                            // The column under the pointer is the target - not
                            // whichever one the (wide) dragged card overlaps most.
                            collisionDetection={pointerWithin}
                            onDragStart={onDragStart}
                            onDragEnd={onDragEnd}
                            onDragCancel={() => setDraggingJob(null)}
                            accessibility={{ announcements }}
                        >
                            {/* Cancels the page wrapper's own side padding so
                                the tracks run edge-to-edge with the screen,
                                not just the padded content column - then
                                reapplies the same padding as scroll gutters. */}
                            <div className="-mx-4 overflow-x-auto pb-4 sm:-mx-6 lg:-mx-8">
                                <div
                                    className="grid gap-4 px-4 sm:px-6 lg:px-8"
                                    style={{
                                        // Columns split the available width
                                        // evenly down to a 320px floor each
                                        // (1280px / 4) - past 4 stages that
                                        // floor pushes total width beyond
                                        // 1280px, so the wrapper above
                                        // scrolls horizontally instead of
                                        // squeezing every column thinner.
                                        gridTemplateColumns: `repeat(${stages.length}, minmax(320px, 1fr))`,
                                        minWidth: '1280px',
                                    }}
                                >
                                    {stages.map((stage) => (
                                        <Column
                                            key={stage.value}
                                            stage={stage}
                                            jobs={jobs.filter(
                                                (job) =>
                                                    job.stage ===
                                                        stage.value &&
                                                    !job.cancelled,
                                            )}
                                            draggingJob={draggingJob}
                                            dragEnabled={dragEnabled}
                                        />
                                    ))}
                                </div>
                            </div>

                            {/* Rendered outside the scrolling board so the card
                                isn't clipped while it's dragged across it. */}
                            <DragOverlay>
                                {draggingJob && (
                                    <div className="w-72 cursor-grabbing space-y-2 rounded-md border border-indigo-300 bg-white p-3 shadow-lg">
                                        <JobCardBody job={draggingJob} />
                                    </div>
                                )}
                            </DragOverlay>
                        </DndContext>
                    )}

                    {cancelledJobs.length > 0 && (
                        <details className="rounded-lg bg-white p-4 shadow-sm">
                            <summary className="cursor-pointer text-sm font-medium text-gray-600">
                                Cancelled jobs ({cancelledJobs.length})
                            </summary>
                            <div className="mt-3 space-y-2">
                                {cancelledJobs.map((job) => (
                                    <div
                                        key={job.id}
                                        className="flex items-center justify-between text-sm"
                                    >
                                        <Link
                                            href={route('jobs.show', job.id)}
                                            className="text-indigo-600 hover:text-indigo-900"
                                        >
                                            {job.job_number} - {job.customer}
                                        </Link>
                                        <span className="text-gray-500">
                                            {currency.format(job.total)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </details>
                    )}
                </div>
            </div>

            <JobMoveDialog
                job={pendingMove?.job ?? null}
                transition={pendingMove?.transition ?? null}
                onClose={() => setPendingMove(null)}
            />
        </AuthenticatedLayout>
    );
}
