import { motion, useScroll, useTransform } from 'framer-motion';
import { ReactNode, useRef } from 'react';

/** SVG path for a gear: `teeth` trapezoid teeth around a circle of radius `inner`. */
function gearPath(teeth: number, inner: number, outer: number): string {
    const step = (Math.PI * 2) / teeth;
    const points: string[] = [];

    for (let i = 0; i < teeth; i++) {
        const a = i * step;
        const corners = [
            [a, inner],
            [a + step * 0.2, outer],
            [a + step * 0.5, outer],
            [a + step * 0.7, inner],
        ];
        corners.forEach(([angle, radius]) => {
            points.push(
                `${(50 + Math.cos(angle) * radius).toFixed(2)},${(50 + Math.sin(angle) * radius).toFixed(2)}`,
            );
        });
    }

    return `M${points.join('L')}Z`;
}

const bigGear = gearPath(12, 38, 48);
const smallGear = gearPath(8, 36, 47);

function Gear({ teeth }: { teeth: 'big' | 'small' }) {
    return (
        <svg
            viewBox="0 0 100 100"
            className="size-full"
            fill="none"
            stroke="currentColor"
            strokeWidth="2.5"
            strokeLinejoin="round"
        >
            <path d={teeth === 'big' ? bigGear : smallGear} />
            <circle cx="50" cy="50" r={teeth === 'big' ? 14 : 12} />
            <circle cx="50" cy="50" r="4" fill="currentColor" />
        </svg>
    );
}

function HexNut() {
    return (
        <svg
            viewBox="0 0 100 100"
            className="size-full"
            fill="none"
            stroke="currentColor"
            strokeWidth="3"
            strokeLinejoin="round"
        >
            <polygon points="50,6 88,28 88,72 50,94 12,72 12,28" />
            <circle cx="50" cy="50" r="18" />
        </svg>
    );
}

function Bolt() {
    return (
        <svg
            viewBox="0 0 100 100"
            className="size-full"
            fill="none"
            stroke="currentColor"
            strokeWidth="3"
            strokeLinejoin="round"
            strokeLinecap="round"
        >
            <polygon points="30,8 70,8 78,22 70,36 30,36 22,22" />
            <path d="M38 36V92M62 36V92M38 92H62" />
            <path d="M38 48H62M38 60H62M38 72H62M38 84H62" strokeWidth="2" />
        </svg>
    );
}

function IBeam() {
    return (
        <svg
            viewBox="0 0 100 100"
            className="size-full"
            fill="none"
            stroke="currentColor"
            strokeWidth="3"
            strokeLinejoin="round"
        >
            <path d="M10 14H90V30H56V70H90V86H10V70H44V30H10Z" />
        </svg>
    );
}

function Angle() {
    return (
        <svg
            viewBox="0 0 100 100"
            className="size-full"
            fill="none"
            stroke="currentColor"
            strokeWidth="3"
            strokeLinejoin="round"
        >
            <path d="M14 10H34V66H90V86H14Z" />
            <circle cx="24" cy="24" r="3" />
            <circle cx="24" cy="50" r="3" />
            <circle cx="52" cy="76" r="3" />
            <circle cx="76" cy="76" r="3" />
        </svg>
    );
}

interface Part {
    node: ReactNode;
    /** Position and size, as Tailwind classes. */
    className: string;
    /** Wrapper animation: drift or spin. */
    motion: string;
    /** Scroll parallax distance in px (negative moves up as you scroll). */
    parallax: number;
}

const parts: Part[] = [
    {
        node: <Gear teeth="big" />,
        className: 'left-[3%] top-[10%] size-28 sm:size-44',
        motion: 'animate-spin-slow',
        parallax: -60,
    },
    {
        node: <Gear teeth="small" />,
        className: 'left-[14%] top-[34%] hidden size-20 sm:block sm:size-28',
        motion: 'animate-spin-slow-reverse',
        parallax: -90,
    },
    {
        node: <HexNut />,
        className: 'right-[6%] top-[8%] size-16 sm:size-24',
        motion: 'animate-drift-a',
        parallax: -40,
    },
    {
        node: <IBeam />,
        className: 'right-[3%] top-[44%] hidden size-28 sm:block sm:size-36',
        motion: 'animate-drift-b',
        parallax: -110,
    },
    {
        node: <Bolt />,
        className: 'left-[8%] bottom-[8%] size-16 sm:size-24',
        motion: 'animate-drift-b',
        parallax: -30,
    },
    {
        node: <Angle />,
        className: 'right-[18%] bottom-[4%] hidden size-24 sm:block',
        motion: 'animate-drift-a',
        parallax: -70,
    },
];

const sparks = [
    { left: '46%', top: '18%', x: '-34px', y: '-46px', delay: '0s' },
    { left: '50%', top: '18%', x: '10px', y: '-58px', delay: '0.5s' },
    { left: '54%', top: '18%', x: '40px', y: '-38px', delay: '1.1s' },
];

/**
 * Decorative backdrop for the hero: outlined machine parts drifting and
 * turning, each at its own scroll-parallax speed, plus a few weld sparks.
 * Pure SVG + CSS (no WebGL), faint enough to sit behind text, and every
 * animation stops under prefers-reduced-motion.
 */
export default function FloatingParts() {
    const ref = useRef<HTMLDivElement>(null);
    const { scrollY } = useScroll();

    return (
        <div
            ref={ref}
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 -z-10 overflow-hidden text-primary/25 dark:text-primary/30"
        >
            {parts.map((part, index) => (
                <Parallax
                    key={index}
                    distance={part.parallax}
                    scrollY={scrollY}
                    className={`absolute ${part.className}`}
                >
                    <div
                        className={`size-full motion-reduce:animate-none ${part.motion}`}
                    >
                        {part.node}
                    </div>
                </Parallax>
            ))}

            {sparks.map((spark, index) => (
                <span
                    key={index}
                    className="absolute size-1.5 rounded-full bg-amber-400 opacity-0 animate-spark motion-reduce:animate-none"
                    style={
                        {
                            left: spark.left,
                            top: spark.top,
                            animationDelay: spark.delay,
                            '--spark-x': spark.x,
                            '--spark-y': spark.y,
                        } as React.CSSProperties
                    }
                />
            ))}
        </div>
    );
}

function Parallax({
    distance,
    scrollY,
    className,
    children,
}: {
    distance: number;
    scrollY: ReturnType<typeof useScroll>['scrollY'];
    className: string;
    children: ReactNode;
}) {
    const y = useTransform(scrollY, [0, 800], [0, distance]);

    return (
        <motion.div style={{ y }} className={className}>
            {children}
        </motion.div>
    );
}
