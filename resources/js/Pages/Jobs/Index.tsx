import JobAssigneeSelect, { Member } from '@/Components/JobAssigneeSelect';
import JobCalendar from '@/Components/JobCalendar';
import JobMoveDialog from '@/Components/JobMoveDialog';
import JobStatusActions, { JobTransition } from '@/Components/JobStatusActions';
import PageHelp from '@/Components/PageHelp';
import { shortDate, toDay } from '@/lib/dates';
import SecondaryButton from '@/Components/SecondaryButton';
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
import { motion } from 'framer-motion';
import { CSSProperties, useEffect, useRef, useState } from 'react';
import { Alert } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';

const ANIMATIONS_STORAGE_KEY = 'jobBoardAnimationsEnabled';
const VIEW_MODE_STORAGE_KEY = 'jobBoardViewMode';

// Presentation modes for the jobs page - the kanban board is the default.
// (The timeline/gantt view lives under Reports, not here.)
const VIEW_MODES = [
    { value: 'board', label: 'Board' },
    { value: 'calendar', label: 'Calendar' },
] as const;
type ViewMode = (typeof VIEW_MODES)[number]['value'];

// A smooth, non-bouncy curve fits a work tool better than the springy
// default - this isn't a playful interaction, just "a card moved."
const LAYOUT_TRANSITION = { duration: 0.3, ease: [0.4, 0, 0.2, 1] as const };

interface JobRow {
    id: number;
    job_number: string;
    customer: string;
    stage: string;
    stage_label: string;
    cancelled: boolean;
    total: number;
    available_transitions: JobTransition[];
    assigned_to: number | null;
    assignee: string | null;
    assignee_avatar_url: string | null;
    started_on: string | null;
    ended_on: string | null;
    due_date: string | null;
    overdue: boolean;
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

// How long a column's outline glow stays at full strength (see the
// dashboard's "Shipped, not yet invoiced" tile - see Column's `focused`)
// before it starts easing off - matches the fade duration in the
// transition-shadow class applied alongside it.
const FOCUS_HIGHLIGHT_HOLD_MS = 3000;

// How long the kiosk border glow stays at full strength before it starts
// easing off (~5s, then ~3s to fade - see KioskUpdateBorder).
const KIOSK_BORDER_HOLD_MS = 5000;

/**
 * Shown for kiosk viewers only (no header/bell to look at) when someone
 * elsewhere updates the board - full brightness for ~5s, then eases out
 * over ~3s rather than disappearing abruptly. Remounted per pulse (key at
 * the call site) so each update gets its own fresh show-then-fade cycle.
 */
function KioskUpdateBorder() {
    const [visible, setVisible] = useState(true);

    useEffect(() => {
        const timer = setTimeout(() => setVisible(false), KIOSK_BORDER_HOLD_MS);
        return () => clearTimeout(timer);
    }, []);

    return (
        <div
            aria-hidden
            className={`pointer-events-none fixed inset-0 z-50 transition-shadow ease-out ${
                visible
                    ? 'shadow-[inset_0_0_0_10px_rgba(99,102,241,0.6)] duration-300'
                    : 'shadow-[inset_0_0_0_10px_rgba(99,102,241,0)] duration-3000'
            }`}
        />
    );
}

// How long the bell stays highlighted once the browser can actually be
// seen - a viewer who was looking at another tab/app when the update
// arrived shouldn't come back to find it already faded, so the countdown
// only runs while the page is visible and restarts fresh every time focus
// returns (kiosk mode never renders this at all - see the border instead).
const BELL_MIN_VISIBLE_MS = 10000;

/**
 * A quiet fixture in the header that lights up and jiggles when someone
 * else changes the board (see the JobBoardUpdated listener in Index) - a
 * running count of how many times that's happened isn't included on
 * purpose: by the time it could jiggle, this board has already reloaded
 * with the current data, so a tally wouldn't tell a viewer anything they
 * could act on beyond "something changed, and here it already is."
 */
function UpdateBell({ pulse }: { pulse: number }) {
    const [highlighted, setHighlighted] = useState(() => pulse > 0);

    useEffect(() => {
        if (pulse === 0) return;

        // Only the inner <span> below remounts per pulse (its own key) -
        // this effect re-runs on every pulse too (its dependency), but the
        // component instance itself persists, so `highlighted` needs to be
        // set explicitly rather than relying on fresh initial state.
        setHighlighted(true);

        let timer: ReturnType<typeof setTimeout> | undefined;

        const startCountdown = () => {
            if (document.visibilityState !== 'visible') return;
            clearTimeout(timer);
            timer = setTimeout(
                () => setHighlighted(false),
                BELL_MIN_VISIBLE_MS,
            );
        };

        startCountdown();
        window.addEventListener('focus', startCountdown);
        document.addEventListener('visibilitychange', startCountdown);

        return () => {
            clearTimeout(timer);
            window.removeEventListener('focus', startCountdown);
            document.removeEventListener('visibilitychange', startCountdown);
        };
    }, [pulse]);

    return (
        <span
            // Remounted on every pulse so the one-shot jiggle animation
            // restarts instead of only ever playing once.
            key={pulse}
            title={
                highlighted
                    ? 'The board was just updated elsewhere'
                    : 'Updates from elsewhere appear here'
            }
            className={`inline-flex items-center justify-center rounded-full p-1.5 ${
                highlighted
                    ? 'animate-bell-ring bg-indigo-100 text-indigo-600'
                    : 'text-gray-300'
            }`}
        >
            <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10 2a6 6 0 00-6 6v3.586l-1.707 1.707A1 1 0 004 14h12a1 1 0 00.707-1.707L15 10.586V8a6 6 0 00-6-6zM8.05 15a1.95 1.95 0 003.9 0h-3.9z" />
            </svg>
        </span>
    );
}

// Cards take a faded tint by their column's position: the first column is
// blue, the last green, and everything between cycles through a warm
// assortment (yellow, orange, yellow, red) - see .job-card in app.css.
const FIRST_HUE = 200;
const LAST_HUE = 140;
const MIDDLE_HUES = [50, 28, 42, 6];

function cardTint(columnIndex: number, columnCount: number): CSSProperties {
    const hue =
        columnIndex <= 0
            ? FIRST_HUE
            : columnIndex >= columnCount - 1
              ? LAST_HUE
              : MIDDLE_HUES[(columnIndex - 1) % MIDDLE_HUES.length];

    return { '--card-hue': hue } as CSSProperties;
}

function JobCardBody({ job }: { job: JobRow }) {
    return (
        <>
            <div className="flex items-start justify-between">
                <Link
                    href={route('jobs.show', job.id)}
                    className="text-sm font-semibold text-indigo-600 hover:underline"
                >
                    {job.job_number}
                </Link>
                <span className="text-sm text-gray-500">
                    {currency.format(job.total)}
                </span>
            </div>
            <p className="text-sm text-gray-700">{job.customer}</p>
            {job.due_date && (
                <p
                    className={`text-xs ${job.overdue ? 'font-semibold text-red-600' : 'text-gray-500'}`}
                >
                    Due {shortDate(toDay(job.due_date))}
                    {job.overdue && ' · Overdue'}
                </p>
            )}
        </>
    );
}

function JobCard({
    job,
    draggable,
    members,
    canAssign,
    animated,
    onAnimatingChange,
    tint,
}: {
    job: JobRow;
    tint: CSSProperties;
    draggable: boolean;
    members: Member[];
    canAssign: boolean;
    animated: boolean;
    onAnimatingChange: (jobId: number, isAnimating: boolean) => void;
}) {
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
        id: job.id,
        disabled: !draggable,
    });

    return (
        <motion.div
            ref={setNodeRef}
            // A stable layoutId - not the React key - is what lets a card
            // glide from one column to another rather than snapping: React
            // fully unmounts/remounts it (different Column, different
            // parent), and layoutId is Framer's mechanism for animating
            // that same "identity" across two unrelated places in the tree.
            // Works whether the move came from this browser's own
            // drag/confirm or from a JobBoardUpdated broadcast reloading
            // someone else's change - either way it's one React commit with
            // the card moving parents, which is exactly what layoutId
            // tracks.
            layout={animated}
            layoutId={animated ? `job-${job.id}` : undefined}
            transition={{
                layout: animated ? LAYOUT_TRANSITION : { duration: 0 },
            }}
            // The destination column clips overflow for its rounded corners
            // and vertical scrollbar - while this card is mid-flight it's
            // still visually near its *old* column, so it (and the column
            // it's landing in) both need clipping/stacking relaxed for a
            // moment or it renders underneath/behind neighboring columns.
            // See Column's hasAnimatingJob.
            onLayoutAnimationStart={() => onAnimatingChange(job.id, true)}
            onLayoutAnimationComplete={() => onAnimatingChange(job.id, false)}
            style={tint}
            className={`job-card relative space-y-2 rounded-md border p-3 shadow-xs ${isDragging ? 'opacity-40' : ''} ${draggable ? 'ps-7' : ''}`}
        >
            {draggable && (
                // Only the handle starts a drag, so links/buttons on the card
                // and touch-scrolling the board keep working normally.
                <button
                    type="button"
                    {...listeners}
                    {...attributes}
                    aria-label={`Drag ${job.job_number} to another station`}
                    className="absolute inset-y-0 inset-s-0 flex w-6 cursor-grab touch-none items-center justify-center rounded-s-md text-gray-300 hover:bg-accent hover:text-foreground active:cursor-grabbing m-0"
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
            <div className="flex items-center justify-between gap-2">
                <JobAssigneeSelect
                    job={job}
                    members={members}
                    editable={canAssign}
                />
                <JobStatusActions
                    job={job}
                    transitions={job.available_transitions}
                />
            </div>
        </motion.div>
    );
}

function Column({
    stage,
    jobs,
    draggingJob,
    dragEnabled,
    members,
    canAssign,
    animated,
    animatingJobIds,
    onAnimatingChange,
    focused,
    columnIndex,
    columnCount,
}: {
    columnIndex: number;
    columnCount: number;
    stage: StageOption;
    jobs: JobRow[];
    draggingJob: JobRow | null;
    dragEnabled: boolean;
    members: Member[];
    canAssign: boolean;
    animated: boolean;
    animatingJobIds: Set<number>;
    onAnimatingChange: (jobId: number, isAnimating: boolean) => void;
    focused: boolean;
}) {
    const { setNodeRef, isOver } = useDroppable({ id: stage.value });
    const columnRef = useRef<HTMLDivElement>(null);

    // Arriving from the dashboard's "Shipped, not yet invoiced" tile (or
    // similar) - scroll this column into view and glow its outline for a
    // few seconds, then ease it back off rather than leaving a permanent
    // highlight or vanishing it abruptly.
    const [highlightVisible, setHighlightVisible] = useState(focused);

    useEffect(() => {
        if (!focused) return;

        columnRef.current?.scrollIntoView({
            behavior: 'smooth',
            inline: 'center',
            block: 'nearest',
        });

        const timer = setTimeout(
            () => setHighlightVisible(false),
            FOCUS_HIGHLIGHT_HOLD_MS,
        );
        return () => clearTimeout(timer);
    }, [focused]);

    // A card lands in its new column's DOM immediately but is still
    // visually easing in from its old spot - while that's happening, this
    // column can't clip it to its own rounded/scrolling box or let a
    // neighboring column's opaque background paint over it, or the card
    // appears to slide underneath the board instead of over it.
    const hasAnimatingJob = jobs.some((job) => animatingJobIds.has(job.id));

    const isSource = draggingJob?.stage === stage.value;
    const isValidTarget =
        draggingJob?.available_transitions.some(
            (t) => t.value === stage.value,
        ) ?? false;

    // While dragging, legal destinations light up and everything else dims,
    // so it's obvious where a job can (and can't) go.
    const dropStyle = !draggingJob
        ? ''
        : isValidTarget
          ? isOver
              ? 'bg-indigo-100 ring-2 ring-indigo-500'
              : 'bg-indigo-50 ring-2 ring-indigo-200'
          : isSource
            ? ''
            : ' opacity-50';

    // A plain `shadow-xs` alongside a conditional `shadow-[...]` would fight
    // over the same box-shadow property - whichever rule Tailwind happens
    // to emit later in the stylesheet wins, regardless of class order in
    // the markup - so the resting shadow is folded into the same arbitrary
    // value instead of coexisting as a separate utility.
    const columnShadow = !focused
        ? 'shadow-xs'
        : highlightVisible
          ? 'shadow-[0_0_0_4px_rgba(99,102,241,0.6),0_1px_2px_0_rgba(0,0,0,0.05)] duration-300'
          : 'shadow-[0_0_0_4px_rgba(99,102,241,0),0_1px_2px_0_rgba(0,0,0,0.05)] duration-1500';

    return (
        <div
            ref={columnRef}
            // Elevated above sibling columns while animating, so an
            // unclipped card can visually pass over them instead of being
            // painted behind them (grid items z-index against each other
            // regardless of position:static).
            className={`relative flex h-full flex-col rounded-lg transition-shadow ease-out bg-[#fbfbfb] dark:bg-[#1c1c1c] ${columnShadow} ${hasAnimatingJob ? 'z-10 overflow-visible' : 'overflow-hidden'}`}
        >
            <div className="flex shrink-0 items-center justify-between px-3 py-2">
                <h3 className="text-xs font-semibold uppercase tracking-wider text-gray-700">
                    {stage.label}
                </h3>
                <span className="rounded-full px-2 py-0.5 text-xs font-medium text-gray-500">
                    {jobs.length}
                </span>
            </div>
            <div
                ref={setNodeRef}
                // h-full/flex-1 only bite when an ancestor actually has a
                // real height (kiosk mode's h-screen chain) - otherwise
                // they resolve to auto and min-h-112 is what governs,
                // same as before. overflow-y-auto only ever activates once
                // something constrains this box shorter than its content,
                // so it's inert in the normal (auto-height) case too.
                // Briefly dropped to visible (losing vertical scroll for
                // that moment) while a card here is mid-animation, for the
                // same reason as the wrapper above.
                className={`min-h-112 flex-1 space-y-3 p-2 transition ${dropStyle} ${hasAnimatingJob ? 'overflow-visible' : 'overflow-y-auto'}`}
            >
                {jobs.map((job) => (
                    <JobCard
                        key={job.id}
                        job={job}
                        tint={cardTint(columnIndex, columnCount)}
                        // No handle on a card with nowhere to go (e.g. an
                        // Invoiced job, which can only be cancelled).
                        draggable={
                            dragEnabled &&
                            job.available_transitions.some(
                                (t) => t.value !== 'cancelled',
                            )
                        }
                        members={members}
                        canAssign={canAssign}
                        animated={animated}
                        onAnimatingChange={onAnimatingChange}
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
    members,
    canAssign,
    today,
}: PageProps<{
    jobs: JobRow[];
    stages: StageOption[];
    dragEnabled: boolean;
    members: Member[];
    canAssign: boolean;
    today: string;
}>) {
    const cancelledJobs = jobs.filter((j) => j.cancelled);
    const shopId = usePage().props.auth.user.current_shop_id;

    // Bookmarkable/refresh-safe (a wall-mounted display can just open the
    // ?kiosk=1 URL directly) rather than plain component state - kept in
    // sync with the URL via replaceState, not an Inertia visit, so toggling
    // never round-trips to the server for a purely client-side layout mode.
    const [kiosk, setKiosk] = useState(() =>
        new URLSearchParams(window.location.search).has('kiosk'),
    );

    useEffect(() => {
        const url = new URL(window.location.href);
        if (kiosk) {
            url.searchParams.set('kiosk', '1');
        } else {
            url.searchParams.delete('kiosk');
        }
        window.history.replaceState(window.history.state, '', url);
    }, [kiosk]);

    // Pressing Esc leaves real browser fullscreen without going through our
    // exit button - drop the kiosk layout too so nav doesn't stay hidden.
    useEffect(() => {
        const onFullscreenChange = () => {
            if (!document.fullscreenElement) setKiosk(false);
        };
        document.addEventListener('fullscreenchange', onFullscreenChange);
        return () =>
            document.removeEventListener(
                'fullscreenchange',
                onFullscreenChange,
            );
    }, []);

    // A dashboard tile (e.g. "Shipped, not yet invoiced") links here with
    // ?focus=<role> so the matching column can be scrolled to and
    // highlighted on arrival - role rather than a shop-specific
    // job_stage_id, since role is the stable, shop-independent key the rest
    // of the app already keys this same "shipped" concept off of.
    const [focusRole] = useState(() =>
        new URLSearchParams(window.location.search).get('focus'),
    );
    const focusedStageValue = focusRole
        ? (stages.find((s) => s.role === focusRole)?.value ?? null)
        : null;

    const enterKiosk = () => {
        setKiosk(true);
        // Best-effort - some contexts (embedded iframes, denied permission)
        // block the Fullscreen API entirely. The layout still hides nav and
        // goes edge-to-edge either way.
        document.documentElement.requestFullscreen?.().catch(() => {});
    };

    const exitKiosk = () => {
        setKiosk(false);
        if (document.fullscreenElement) {
            document.exitFullscreen?.().catch(() => {});
        }
    };

    // Which jobs are currently mid layout-animation - lets the column a
    // card is landing in temporarily drop its overflow clipping/z-index so
    // the card isn't cut off or painted behind a neighboring column while
    // it's still visually easing in from its old spot.
    const [animatingJobIds, setAnimatingJobIds] = useState<Set<number>>(
        new Set(),
    );
    const onAnimatingChange = (jobId: number, isAnimating: boolean) => {
        setAnimatingJobIds((prev) => {
            const next = new Set(prev);
            if (isAnimating) {
                next.add(jobId);
            } else {
                next.delete(jobId);
            }
            return next;
        });
    };

    // Per-device preference, not shop data - a wall-mounted kiosk and
    // someone's laptop might want this set differently, and it should
    // survive a kiosk display's page reloads without being reconfigured.
    const [animated, setAnimated] = useState(() => {
        try {
            return localStorage.getItem(ANIMATIONS_STORAGE_KEY) !== 'off';
        } catch {
            return true;
        }
    });

    useEffect(() => {
        try {
            localStorage.setItem(
                ANIMATIONS_STORAGE_KEY,
                animated ? 'on' : 'off',
            );
        } catch {
            // Private browsing / blocked storage - the toggle still works
            // for the rest of this session, it just won't be remembered.
        }
    }, [animated]);

    // Remembered per browser (for now - not yet a server-side user setting),
    // so whichever view someone picks becomes their default next visit.
    const [viewMode, setViewMode] = useState<ViewMode>(() => {
        try {
            const saved = localStorage.getItem(VIEW_MODE_STORAGE_KEY);
            return VIEW_MODES.some((m) => m.value === saved)
                ? (saved as ViewMode)
                : 'board';
        } catch {
            return 'board';
        }
    });

    useEffect(() => {
        try {
            localStorage.setItem(VIEW_MODE_STORAGE_KEY, viewMode);
        } catch {
            // Blocked storage - the choice still holds for this session.
        }
    }, [viewMode]);

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

    // Bumped on every JobBoardUpdated broadcast (never by this browser's
    // own actions - the backend broadcasts with toOthers()) and used as a
    // React key so the bell/border-flash below remount and replay their
    // once-through CSS animation each time, rather than only the first.
    const [updatePulse, setUpdatePulse] = useState(0);

    // Another user moving a job refetches the board instead of polling -
    // the broadcast itself carries no data, it's just a "go fetch" signal.
    useEffect(() => {
        if (!shopId) return;

        const channel = window.Echo.private(`shop.${shopId}.jobs`).listen(
            '.JobBoardUpdated',
            () => {
                router.reload({ only: ['jobs'] });
                setUpdatePulse((prev) => prev + 1);
            },
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

    const boardContent = (
        // flex/h-full only take effect when an ancestor gives this a real
        // height (kiosk mode's h-screen chain) - in the normal page flow
        // there's no such ancestor, so this collapses back to auto-height
        // stacking, same as the fragment it replaced.
        <div className="flex h-full flex-col gap-6">
            {dropHint && (
                <Alert
                    role="status"
                    variant="warning" className="shrink-0 p-3 text-sm"
                >
                    {dropHint}
                </Alert>
            )}

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
                    {/* Cancels the immediate wrapper's own side padding so
                        the tracks run edge-to-edge with the screen, not just
                        the padded content column - then reapplies the same
                        padding as scroll gutters. Works identically in the
                        normal and kiosk shells since both give this
                        component the same px-4 sm:px-6 lg:px-8 to cancel.
                        flex-1/min-h-0/h-full again only bite in kiosk mode -
                        in normal mode this whole block just sizes to its
                        content and the page scrolls, same as before. */}
                    <div className="relative flex min-h-0 flex-1 flex-col">
                    <div className="-mx-4 min-h-0 flex-1 overflow-x-auto pb-4 sm:-mx-6 lg:-mx-8">
                        <div
                            className="grid h-full gap-4 px-4 sm:px-6 lg:px-8"
                            style={{
                                // Columns split the available width evenly
                                // down to a 320px floor each (1280px / 4) -
                                // past 4 stages that floor pushes the grid
                                // beyond 1280px, so the wrapper above
                                // scrolls horizontally instead of squeezing
                                // every column thinner. The 1fr row lets
                                // columns stretch to fill a real height
                                // (kiosk) while still sizing to content in
                                // an auto-height ancestor (normal mode).
                                gridTemplateColumns: `repeat(${stages.length}, minmax(320px, 1fr))`,
                                gridTemplateRows: '1fr',
                                minWidth: '1280px',
                                // Room for the empty-board message in the normal page flow.
                                minHeight: jobs.length === 0 ? '24rem' : undefined,
                            }}
                        >
                            {stages.map((stage, index) => (
                                <Column
                                    key={stage.value}
                                    columnIndex={index}
                                    columnCount={stages.length}
                                    stage={stage}
                                    jobs={jobs.filter(
                                        (job) =>
                                            job.stage === stage.value &&
                                            !job.cancelled,
                                    )}
                                    draggingJob={draggingJob}
                                    dragEnabled={dragEnabled}
                                    members={members}
                                    canAssign={canAssign}
                                    animated={animated}
                                    animatingJobIds={animatingJobIds}
                                    onAnimatingChange={onAnimatingChange}
                                    focused={stage.value === focusedStageValue}
                                />
                            ))}
                        </div>
                    </div>

                    {jobs.length === 0 && (
                        // The empty board stays visible behind a message, so
                        // a new shop sees the shape of what it will fill.
                        <div className="absolute inset-0 flex items-center justify-center bg-gray-100/70 p-4">
                            <div className="max-w-md rounded-lg bg-white p-6 text-center shadow-lg">
                                <p className="text-sm font-medium text-gray-900">
                                    No jobs yet. This is the job board - every
                                    job converted from a won quote, tracked
                                    from cutting through shipped and invoiced.
                                </p>
                                <p className="mt-3 text-sm text-gray-600">
                                    Convert a won quote to create one - go to{' '}
                                    <Link
                                        href={route('quotes.index')}
                                        className="font-medium text-indigo-600 hover:underline"
                                    >
                                        Quotes
                                    </Link>
                                    , mark one won, then convert it.
                                </p>
                            </div>
                        </div>
                    )}
                    </div>

                    {/* Rendered outside the scrolling board so the card
                        isn't clipped while it's dragged across it. */}
                    <DragOverlay>
                        {draggingJob && (
                            <div
                                className="job-card w-72 cursor-grabbing space-y-2 rounded-md border p-3 shadow-lg"
                                style={cardTint(
                                    stages.findIndex(
                                        (s) => s.value === draggingJob.stage,
                                    ),
                                    stages.length,
                                )}
                            >
                                <JobCardBody job={draggingJob} />
                            </div>
                        )}
                    </DragOverlay>
                </DndContext>
        </div>
    );

    const cancelledJobsSection = cancelledJobs.length > 0 && (
        <details className="rounded-lg bg-white p-4 shadow-xs">
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
                            className="text-indigo-600 hover:underline"
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
    );

    const jobMoveDialog = (
        <JobMoveDialog
            job={pendingMove?.job ?? null}
            transition={pendingMove?.transition ?? null}
            onClose={() => setPendingMove(null)}
        />
    );

    const viewModeToggle = (
        <div
            role="group"
            aria-label="Jobs view"
            className="inline-flex rounded-md border p-0.5"
        >
            {VIEW_MODES.map((mode) => (
                <Button
                    key={mode.value}
                    type="button"
                    size="sm"
                    variant={viewMode === mode.value ? 'default' : 'ghost'}
                    aria-pressed={viewMode === mode.value}
                    onClick={() => setViewMode(mode.value)}
                >
                    {mode.label}
                </Button>
            ))}
        </div>
    );

    const animationsToggle = (
        <SecondaryButton onClick={() => setAnimated((prev) => !prev)}>
            Animations: {animated ? 'On' : 'Off'}
        </SecondaryButton>
    );

    // Kiosk: no top nav, no page chrome beyond a minimal exit affordance -
    // just the board, full screen (and real browser fullscreen too, when
    // the Fullscreen API is available - see enterKiosk).
    if (kiosk) {
        return (
            <>
                <Head title="Jobs" />
                {/* h-screen (not min-h-screen) + flex-col is the actual
                    height source the whole board/column/scrollbar chain
                    below stretches into, so the board's bottom - and its
                    horizontal scrollbar, once there are more than 4 stages
                    - lands flush with the bottom of the screen instead of
                    floating above leftover empty space. */}
                <div className="flex h-screen flex-col overflow-hidden bg-gray-100">
                    <div className="flex shrink-0 items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
                        <h1 className="text-lg font-semibold text-gray-700">
                            Jobs
                        </h1>
                        <div className="flex items-center gap-2">
                            {animationsToggle}
                            <SecondaryButton onClick={exitKiosk}>
                                Exit kiosk mode
                            </SecondaryButton>
                        </div>
                    </div>
                    <div className="min-h-0 flex-1 px-4 sm:px-6 lg:px-8">
                        {boardContent}
                    </div>
                </div>
                {/* A small header bell wouldn't register from across a
                    room - the whole screen's border glows instead, for
                    anyone watching a wall-mounted kiosk when someone
                    elsewhere updates the board. Remounted per pulse (key)
                    so each update gets its own fresh show-then-fade cycle. */}
                {updatePulse > 0 && <KioskUpdateBorder key={updatePulse} />}
                {jobMoveDialog}
            </>
        );
    }

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Jobs
                    </h2>
                    {jobs.length > 0 && (
                        <>
                            <PageHelp>
                                <p>
                                    The job board - every job converted from a
                                    won quote, tracked from cutting through
                                    shipped and invoiced.
                                </p>
                                <p>
                                    This is the daily-use screen for the shop
                                    floor. Click a job to log status changes,
                                    photos, material usage, and qualification
                                    records against it.
                                </p>
                            </PageHelp>
                            <UpdateBell pulse={updatePulse} />
                            {viewModeToggle}
                            {viewMode === 'board' && (
                                <>
                                    {animationsToggle}
                                    <SecondaryButton onClick={enterKiosk}>
                                        Kiosk mode
                                    </SecondaryButton>
                                </>
                            )}
                        </>
                    )}
                </div>
            }
        >
            <Head title="Jobs" />

            <div className="py-12">
                <div className="mx-auto max-w-full space-y-6 px-4 sm:px-6 lg:px-8">
                    {viewMode === 'calendar' && jobs.length > 0 ? (
                        <JobCalendar jobs={jobs} today={today} />
                    ) : (
                        <>
                            {boardContent}
                            {cancelledJobsSection}
                        </>
                    )}
                </div>
            </div>

            {jobMoveDialog}
        </AuthenticatedLayout>
    );
}
