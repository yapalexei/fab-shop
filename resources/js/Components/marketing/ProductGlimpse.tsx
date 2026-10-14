import { cn } from '@/lib/utils';
import { motion } from 'framer-motion';
import { Check, FileText, Hammer, LucideIcon, Receipt } from 'lucide-react';
import { ReactNode, useEffect, useState } from 'react';

/** Advances 0..length-1 on an interval; stays on the last frame under reduced motion. */
function useLoop(length: number, ms: number): number {
    // Read after mount, not during render: the server can't know the
    // preference, and a first client render that differs from the server's
    // HTML would break hydration.
    const [reduce, setReduce] = useState(false);
    const [tick, setTick] = useState(0);

    useEffect(() => {
        setReduce(
            window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        );
    }, []);

    useEffect(() => {
        if (reduce) return;
        const id = setInterval(() => setTick((t) => (t + 1) % length), ms);
        return () => clearInterval(id);
    }, [reduce, length, ms]);

    return reduce ? length - 1 : tick;
}

function GlimpseCard({
    icon: Icon,
    label,
    caption,
    children,
}: {
    icon: LucideIcon;
    label: string;
    caption: string;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col rounded-xl border bg-card p-4 text-start shadow-lg ring-1 ring-black/5">
            <div className="flex items-center gap-2">
                <span className="flex size-7 items-center justify-center rounded-md bg-primary/10 text-primary">
                    <Icon className="size-4" />
                </span>
                <span className="text-sm font-semibold">{label}</span>
            </div>
            <p className="mt-1 text-xs text-muted-foreground">{caption}</p>
            <div aria-hidden="true" className="mt-4 flex-1">
                {children}
            </div>
        </div>
    );
}

const quoteLines = [
    { name: '1/4" steel plate', amount: 184 },
    { name: 'Cutting, 2 hr', amount: 140 },
    { name: 'Welding, 4 hr', amount: 320 },
];

/** Quote lines appear one by one and the total counts up. */
function QuotePreview() {
    const tick = useLoop(quoteLines.length + 2, 1300);
    const shown = Math.min(tick, quoteLines.length);
    const total = quoteLines
        .slice(0, shown)
        .reduce((sum, line) => sum + line.amount, 0);

    return (
        <div className="space-y-2 text-xs">
            {quoteLines.map((line, index) => (
                <div
                    key={line.name}
                    className={cn(
                        'flex justify-between rounded-md border bg-background px-2.5 py-2 transition-all duration-500',
                        index < shown
                            ? 'translate-y-0 opacity-100'
                            : 'translate-y-1 opacity-20',
                    )}
                >
                    <span>{line.name}</span>
                    <span className="font-medium">${line.amount}</span>
                </div>
            ))}
            <div className="flex justify-between px-1 pt-1 text-sm font-semibold">
                <span>Total</span>
                <span>${total}</span>
            </div>
        </div>
    );
}

const boardColumns = ['Not started', 'Cutting', 'Shipped'];
const boardJobs = ['Gate repair', 'Stair rail', 'Fire pit'];
const boardTimeline = [
    [0, 0, 1],
    [1, 0, 2],
    [2, 1, 2],
    [2, 2, 0],
];

/** Cards glide forward through the board's columns. */
function BoardPreview() {
    const positions = boardTimeline[useLoop(boardTimeline.length, 2000)];

    return (
        <div className="grid grid-cols-3 gap-1.5">
            {boardColumns.map((name, columnIndex) => (
                <div
                    key={name}
                    className="min-h-28 rounded-md bg-muted/60 p-1.5"
                >
                    <p className="truncate pb-1.5 text-[10px] font-semibold text-muted-foreground">
                        {name}
                    </p>
                    <div className="space-y-1.5">
                        {boardJobs.map(
                            (job, jobIndex) =>
                                positions[jobIndex] === columnIndex && (
                                    <motion.div
                                        key={job}
                                        layoutId={`glimpse-${job}`}
                                        transition={{
                                            type: 'spring',
                                            stiffness: 260,
                                            damping: 28,
                                        }}
                                        className="rounded border bg-background px-1.5 py-1.5 text-[10px] font-medium shadow-xs"
                                    >
                                        {job}
                                    </motion.div>
                                ),
                        )}
                    </div>
                </div>
            ))}
        </div>
    );
}

const invoiceRows = ['Bracket set', 'Planter boxes', 'Trailer hitch'];

/** Shipped jobs get ticked off as they're flagged ready to invoice. */
function InvoicePreview() {
    const done = useLoop(invoiceRows.length + 2, 1300);

    return (
        <div className="space-y-2 text-xs">
            <p className="px-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                Ready to invoice
            </p>
            {invoiceRows.map((job, index) => {
                const ready = index < done;

                return (
                    <div
                        key={job}
                        className="flex items-center justify-between rounded-md border bg-background px-2.5 py-2"
                    >
                        <span>{job}</span>
                        <span
                            className={cn(
                                'flex size-4 items-center justify-center rounded-full transition-all duration-500',
                                ready
                                    ? 'scale-100 bg-green-500 text-white'
                                    : 'scale-75 bg-muted text-transparent',
                            )}
                        >
                            <Check className="size-3" />
                        </span>
                    </div>
                );
            })}
        </div>
    );
}

/**
 * Three small, labeled, self-playing previews (quote, job board, invoicing)
 * so the hero shows the whole flow rather than a single screen. Illustrative
 * sample data, not live data.
 */
export default function ProductGlimpse() {
    return (
        <div className="mt-14">
            <div className="grid gap-4 md:grid-cols-3">
                <GlimpseCard
                    icon={FileText}
                    label="Quotes"
                    caption="Price a job from your own materials, labor and markup."
                >
                    <QuotePreview />
                </GlimpseCard>
                <GlimpseCard
                    icon={Hammer}
                    label="Jobs"
                    caption="Follow each job through the stages you define."
                >
                    <BoardPreview />
                </GlimpseCard>
                <GlimpseCard
                    icon={Receipt}
                    label="Invoicing"
                    caption="Shipped work is flagged so nothing goes unbilled."
                >
                    <InvoicePreview />
                </GlimpseCard>
            </div>
            <p className="mt-3 text-center text-xs text-muted-foreground">
                Sample data, for illustration.
            </p>
        </div>
    );
}
