import { cn } from '@/lib/utils';
import { ReactNode, useEffect, useRef, useState } from 'react';

/**
 * Fades and lifts its children in the first time they scroll into view.
 *
 * The content is visible in the server-rendered HTML and on first paint -
 * crawlers, no-JS visitors and the page's first screen never wait on this
 * script. Only after mount, and only for blocks still below the fold, are
 * they tucked away and then revealed on scroll. (It used to start every block
 * at opacity 0, which would have shipped the whole page invisible.)
 */
export default function Reveal({
    children,
    delay = 0,
    className,
}: {
    children: ReactNode;
    delay?: number;
    className?: string;
}) {
    const ref = useRef<HTMLDivElement>(null);
    const [hidden, setHidden] = useState(false);

    useEffect(() => {
        const el = ref.current;

        if (
            !el ||
            !('IntersectionObserver' in window) ||
            window.matchMedia('(prefers-reduced-motion: reduce)').matches ||
            el.getBoundingClientRect().top < window.innerHeight - 60
        ) {
            return;
        }

        setHidden(true);

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setHidden(false);
                    observer.disconnect();
                }
            },
            { rootMargin: '0px 0px -60px 0px' },
        );
        observer.observe(el);

        return () => observer.disconnect();
    }, []);

    return (
        <div
            ref={ref}
            className={cn(
                className,
                'transition duration-500 ease-out',
                hidden && 'translate-y-6 opacity-0',
            )}
            style={{ transitionDelay: hidden ? undefined : `${delay}s` }}
        >
            {children}
        </div>
    );
}
