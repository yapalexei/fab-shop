import { motion, useReducedMotion } from 'framer-motion';
import { ReactNode } from 'react';

/** Fades and lifts its children in the first time they scroll into view. */
export default function Reveal({
    children,
    delay = 0,
    className,
}: {
    children: ReactNode;
    delay?: number;
    className?: string;
}) {
    const reduce = useReducedMotion();

    return (
        <motion.div
            className={className}
            initial={reduce ? false : { opacity: 0, y: 24 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true, margin: '-60px' }}
            transition={{ duration: 0.5, delay, ease: 'easeOut' }}
        >
            {children}
        </motion.div>
    );
}
