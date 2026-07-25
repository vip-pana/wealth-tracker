import { useState, useEffect } from 'react';

// The title typewriter plays only for a session the user just created. On
// creation we stamp its id here (survives the `router.visit` navigation, unlike
// module state, and a normal page refresh doesn't set it) and TypewriterText
// consumes the stamp — so a refresh, a rename, or reopening an old session all
// render the title plainly.
const PENDING_TITLE_ANIM_KEY = 'advisor:animate-title-id';

export function markSessionForTitleAnimation(id: number): void {
    try {
        sessionStorage.setItem(PENDING_TITLE_ANIM_KEY, String(id));
    } catch {
        // sessionStorage unavailable (private mode / SSR) — skip the animation.
    }
}

// Ids currently mid-reveal this page-load. The two title spots (header + list
// row) mount together and must agree, so the first to resolve the stamp records
// `true` here and the sibling reads it. Once the reveal has actually started it
// is marked `false`, so any LATER remount of the same id (e.g. the chat poll
// re-rendering the list while a reply generates) renders the title plainly
// instead of replaying the typewriter.
const claimedTitleAnims = new Map<number, boolean>();

function claimTitleAnimation(id: number): boolean {
    const cached = claimedTitleAnims.get(id);
    if (cached !== undefined) return cached;

    let claimed = false;
    try {
        if (sessionStorage.getItem(PENDING_TITLE_ANIM_KEY) === String(id)) {
            sessionStorage.removeItem(PENDING_TITLE_ANIM_KEY);
            claimed = true;
        }
    } catch {
        // ignore
    }
    claimedTitleAnims.set(id, claimed);
    return claimed;
}

function consumeTitleAnimation(id: number): void {
    claimedTitleAnims.set(id, false);
}

/**
 * Types a title out character by character, but only for the freshly created
 * session (claimed once from sessionStorage). Every other case — refresh,
 * rename, reopening an old session — renders the title plainly.
 */
export function TypewriterText({ id, text, className }: { id: number; text: string; className?: string }) {
    // Claim once at mount: the first render for the just-created id animates,
    // and the claim is cleared so a later refresh won't replay it.
    const [shouldAnimate] = useState(() => claimTitleAnimation(id));
    // Chars revealed so far while animating; ignored (full text shown) when not.
    const [revealed, setRevealed] = useState(0);

    useEffect(() => {
        if (!shouldAnimate) return;
        // Mark the reveal consumed so future remounts of this id (chat poll
        // re-rendering the list) don't replay it.
        consumeTitleAnimation(id);
        let i = 0;
        const timer = setInterval(() => {
            i += 1;
            setRevealed(i);
            if (i >= text.length) clearInterval(timer);
        }, 32);
        return () => clearInterval(timer);
        // Keyed on id only: a title that changes mid-life (rename) must not
        // retrigger the typewriter.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    const shown = shouldAnimate ? text.slice(0, revealed) : text;

    return (
        <span className={className}>
            {shown}
            {shouldAnimate && shown.length < text.length && (
                <span className="inline-block w-px animate-pulse">|</span>
            )}
        </span>
    );
}
