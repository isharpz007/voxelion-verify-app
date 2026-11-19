/**
 * Scroll-triggered reveal: Reveal fades/slides a block in on intersect; RevealGroup staggers children by 100ms. Uses IntersectionObserver.
 */
import { Children, useEffect, useRef, useState } from 'react';

/**
 * Scroll-triggered reveal: starts invisible + shifted down, then fades and
 * slides up over .6s once it enters the viewport (IntersectionObserver).
 * `delay` staggers siblings.
 */
export default function Reveal({ children, delay = 0, className = '' }) {
    const ref = useRef(null);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const el = ref.current;
        if (!el) return undefined;
        const io = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setVisible(true);
                    io.disconnect();
                }
            },
            { threshold: 0.15, rootMargin: '0px 0px -8% 0px' },
        );
        io.observe(el);
        return () => io.disconnect();
    }, []);

    return (
        <div ref={ref} className={`reveal${visible ? ' reveal-visible' : ''} ${className}`} style={{ transitionDelay: `${delay}ms` }}>
            {children}
        </div>
    );
}

/** Wraps each direct child in a Reveal with a 100ms stagger. */
export function RevealGroup({ children, stagger = 100 }) {
    return (
        <>
            {Children.map(children, (child, i) =>
                child ? <Reveal delay={i * stagger}>{child}</Reveal> : child,
            )}
        </>
    );
}
