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
    status: string;
    status_label: string;
    total: number;
    available_transitions: JobTransition[];
}

interface StatusOption {
    value: string;
    label: string;
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
    status,
    jobs,
    draggingJob,
    dragEnabled,
}: {
    status: StatusOption;
    jobs: JobRow[];
    draggingJob: JobRow | null;
    dragEnabled: boolean;
}) {
    const { setNodeRef, isOver } = useDroppable({ id: status.value });

    const isSource = draggingJob?.status === status.value;
    const isValidTarget =
        draggingJob?.available_transitions.some(
            (t) => t.value === status.value,
        ) ?? false;

    // While dragging, legal destinations light up and everything else dims,
    // so it's obvious where a job can (and can't) go.
    const dropStyle = !draggingJob
        ? 'bg-gray-100'
        : isValidTarget
          ? isOver
              ? 'bg-indigo-100 ring-2 ring-indigo-500'
              : 'bg-indigo-50 ring-2 ring-indigo-200'
          : isSource
            ? 'bg-gray-100'
            : 'bg-gray-100 opacity-50';

    return (
        <div className="w-72 shrink-0">
            <div className="mb-2 flex items-center justify-between px-1">
                <h3 className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                    {status.label}
                </h3>
                <span className="text-xs text-gray-400">{jobs.length}</span>
            </div>
            <div
                ref={setNodeRef}
                className={`min-h-[4rem] space-y-3 rounded-lg p-2 transition ${dropStyle}`}
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
 * Why a drop was refused - mirrors Job::canTransitionTo's rules (one
 * station forward at a time; backward only while the job is still in hand,
 * up to Welding) plus the user simply not being allowed to move jobs back.
 */
function invalidMoveReason(
    job: JobRow,
    target: string,
    columns: StatusOption[],
): string {
    const from = columns.findIndex((s) => s.value === job.status);
    const to = columns.findIndex((s) => s.value === target);
    const welding = columns.findIndex((s) => s.value === 'welding');

    if (to > from + 1) {
        return 'jobs move forward one station at a time.';
    }
    if (to < from && from > welding) {
        return 'once a job reaches Finishing it can only move forward or be cancelled.';
    }

    return "you don't have permission to make that move.";
}

export default function Index({
    jobs,
    statuses,
    dragEnabled,
}: PageProps<{
    jobs: JobRow[];
    statuses: StatusOption[];
    dragEnabled: boolean;
}>) {
    const boardStatuses = statuses.filter((s) => s.value !== 'cancelled');
    const cancelledJobs = jobs.filter((j) => j.status === 'cancelled');
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
        boardStatuses.find((s) => s.value === id)?.label ?? 'no station';
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

        if (!job || !event.over || event.over.id === job.status) return;

        const transition = job.available_transitions.find(
            (t) => t.value === event.over?.id,
        );
        const target = boardStatuses.find((s) => s.value === event.over?.id);

        if (transition) {
            setPendingMove({ job, transition });
        } else {
            setDropHint(
                `${job.job_number} can't move from ${job.status_label} to ${target?.label ?? 'there'}: ${invalidMoveReason(job, event.over.id as string, boardStatuses)}`,
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
                            <div className="flex gap-4 overflow-x-auto pb-4">
                                {boardStatuses.map((status) => (
                                    <Column
                                        key={status.value}
                                        status={status}
                                        jobs={jobs.filter(
                                            (job) =>
                                                job.status === status.value,
                                        )}
                                        draggingJob={draggingJob}
                                        dragEnabled={dragEnabled}
                                    />
                                ))}
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
