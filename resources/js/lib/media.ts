import { useEffect, useState } from 'react';

/**
 * Viewport width as React state. Only for adaptation that CSS cannot express:
 * Recharts sizes its axes, legends and radii through props, so a Tailwind
 * breakpoint has no way to reach them. Anything expressible as a class stays a
 * class.
 *
 * There is no SSR (no server render, React mounts into an empty div), so the
 * initial value can be read synchronously — unlike a `useState(true)` seed,
 * this paints the correct branch on the first frame instead of flashing the
 * desktop one for a tick.
 */
export function useMediaQuery(query: string): boolean {
    const [matches, setMatches] = useState(() => window.matchMedia(query).matches);

    useEffect(() => {
        const mq = window.matchMedia(query);
        const update = () => setMatches(mq.matches);
        update();
        mq.addEventListener('change', update);
        return () => mq.removeEventListener('change', update);
    }, [query]);

    return matches;
}

/**
 * True below Tailwind's `sm` breakpoint. 639px is deliberately one pixel under
 * 640px so the JS switch and every `sm:` class flip on the same pixel — a
 * mismatch would leave a width where the layout stacks but the chart still
 * renders its wide-screen axes.
 */
export function useIsMobile(): boolean {
    return useMediaQuery('(max-width: 639px)');
}
