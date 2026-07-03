import { useState, useEffect } from 'react';

/**
 * The "thinking" state shown while the model works — for both a chat reply and
 * the opening report. Typing dots plus a single rotating insight about the
 * user's own data, so a slow answer never feels like dead air. `revealDelay`
 * holds the insight back briefly for chat (its stream often starts fast, so a
 * flash of fact would be noise); the report reveals it right away since its
 * wait is always long.
 */
export function ThinkingWithFacts({ facts, revealDelay = 2000, label }: { facts: string[]; revealDelay?: number; label?: string }) {
    const [ordered] = useState(() => {
        const a = [...facts];
        for (let i = a.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [a[i], a[j]] = [a[j], a[i]];
        }
        return a;
    });
    const [show, setShow] = useState(revealDelay === 0);
    const [idx, setIdx] = useState(0);

    useEffect(() => {
        if (ordered.length === 0 || revealDelay === 0) return;
        const reveal = setTimeout(() => setShow(true), revealDelay);
        return () => clearTimeout(reveal);
    }, [ordered.length, revealDelay]);

    useEffect(() => {
        if (!show || ordered.length < 2) return;
        const t = setInterval(() => setIdx((i) => (i + 1) % ordered.length), 5000);
        return () => clearInterval(t);
    }, [show, ordered.length]);

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <TypingDots />
                {label && <span>{label}</span>}
            </div>
            {show && ordered.length > 0 && (
                <p key={idx} className="text-xs italic text-muted-foreground/80 animate-fade-in">{ordered[idx]}</p>
            )}
        </div>
    );
}

function TypingDots() {
    return (
        <span className="inline-flex items-center gap-1">
            <span className="h-1.5 w-1.5 rounded-full bg-current animate-bounce [animation-delay:-0.3s]" />
            <span className="h-1.5 w-1.5 rounded-full bg-current animate-bounce [animation-delay:-0.15s]" />
            <span className="h-1.5 w-1.5 rounded-full bg-current animate-bounce" />
        </span>
    );
}
