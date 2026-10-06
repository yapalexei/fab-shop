import { motion, useReducedMotion } from 'framer-motion';
import { useEffect, useState } from 'react';

const columns = ['Not started', 'Cutting', 'Welding', 'Shipped'];

const jobs = [
    { id: 'gate', name: 'Gate repair' },
    { id: 'rail', name: 'Stair rail' },
    { id: 'hitch', name: 'Trailer hitch' },
    { id: 'fire', name: 'Fire pit' },
];

// Each tick every job sits at the column in this table; advancing the tick
// glides cards between columns (framer-motion layout animation), looping.
const timeline = [
    { gate: 0, rail: 0, hitch: 1, fire: 2 },
    { gate: 1, rail: 0, hitch: 2, fire: 3 },
    { gate: 2, rail: 1, hitch: 3, fire: 3 },
    { gate: 3, rail: 2, hitch: 3, fire: 3 },
    { gate: 3, rail: 3, hitch: 0, fire: 0 },
];

/**
 * Decorative job board that plays itself: cards slide forward stage by
 * stage. Illustrative only - not live data.
 */
export default function AnimatedBoard() {
    const reduce = useReducedMotion();
    const [tick, setTick] = useState(0);

    useEffect(() => {
        if (reduce) return;
        const id = setInterval(
            () => setTick((t) => (t + 1) % timeline.length),
            2200,
        );
        return () => clearInterval(id);
    }, [reduce]);

    const positions = timeline[reduce ? 0 : tick];

    return (
        <div
            aria-hidden="true"
            className="mx-auto mt-14 max-w-4xl rounded-xl border bg-card p-3 shadow-xl ring-1 ring-black/5 sm:p-4"
        >
            <div className="mb-3 flex items-center gap-1.5 px-1">
                <span className="size-2.5 rounded-full bg-red-400" />
                <span className="size-2.5 rounded-full bg-amber-400" />
                <span className="size-2.5 rounded-full bg-green-400" />
                <span className="ms-auto flex items-center gap-1.5 text-[10px] font-medium text-muted-foreground">
                    <span className="relative flex size-2">
                        <span className="absolute inline-flex size-full animate-ping rounded-full bg-green-400 opacity-75 motion-reduce:animate-none" />
                        <span className="relative inline-flex size-2 rounded-full bg-green-500" />
                    </span>
                    Live
                </span>
            </div>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {columns.map((name, columnIndex) => (
                    <div
                        key={name}
                        className="min-h-36 rounded-lg bg-muted/60 p-2 text-start"
                    >
                        <p className="px-1 pb-2 text-xs font-semibold text-muted-foreground">
                            {name}
                        </p>
                        <div className="space-y-2">
                            {jobs
                                .filter(
                                    (job) =>
                                        positions[
                                            job.id as keyof typeof positions
                                        ] === columnIndex,
                                )
                                .map((job) => (
                                    <motion.div
                                        key={job.id}
                                        layout
                                        layoutId={job.id}
                                        transition={{
                                            type: 'spring',
                                            stiffness: 260,
                                            damping: 28,
                                        }}
                                        className="rounded-md border bg-background p-2.5 text-xs shadow-xs"
                                    >
                                        <p className="font-medium">
                                            {job.name}
                                        </p>
                                        <div className="mt-2 h-1.5 rounded-full bg-muted">
                                            <div
                                                className="h-full rounded-full bg-primary/70 transition-all duration-700"
                                                style={{
                                                    width: `${(columnIndex + 1) * 25}%`,
                                                }}
                                            />
                                        </div>
                                    </motion.div>
                                ))}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
