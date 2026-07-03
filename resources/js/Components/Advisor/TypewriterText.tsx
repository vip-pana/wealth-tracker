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

// Ids resolved to "animate" this page-load. Both title spots (header + list
// row) share the same id, so we resolve the sessionStorage stamp once and let
// every instance agree — otherwise the first to mount would consume the stamp
// and the other would render plainly.
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

/**
 * Types a title out character by character, but only for the freshly created
 * session (claimed once from sessionStorage). Every other case — refresh,
 * rename, reopening an old session — renders the title plainly.
 */
export function TypewriterText({ id, text, className }: { id: number; text: string; className?: string }) {
    // Claim once at mount: the first render for the just-created id animates,
    // and the claim is cleared so a later refresh won't replay it.
    const [shouldAnimate] = useState(() => claimTitleAnimation(id));
    const [shown, setShown] = useState(shouldAnimate ? '' : text);

    useEffect(() => {
        if (!shouldAnimate) {
            setShown(text);
            return;
        }
        let i = 0;
        const timer = setInterval(() => {
            i += 1;
            setShown(text.slice(0, i));
            if (i >= text.length) clearInterval(timer);
        }, 32);
        return () => clearInterval(timer);
        // Keyed on id only: a title that changes mid-life (rename) must not
        // retrigger the typewriter.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    return (
        <span className={className}>
            {shown}
            {shouldAnimate && shown.length < text.length && (
                <span className="inline-block w-[1px] animate-pulse">|</span>
            )}
        </span>
    );
}
