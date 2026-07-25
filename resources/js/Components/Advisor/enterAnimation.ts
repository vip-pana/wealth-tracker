// Navigating between advisor sessions is a full page visit, so a page-enter
// animation on the container would replay on every session switch — which reads
// as noise. Internal navigations stamp this flag so the fresh mount skips the
// animation; arriving from the sidebar (no stamp) animates once, like every
// other page.
const SKIP_ENTER_ANIM_KEY = 'advisor:skip-enter-anim';

export function markInternalNavigation(): void {
    try { sessionStorage.setItem(SKIP_ENTER_ANIM_KEY, '1'); } catch { /* ignore */ }
}

export function claimEnterAnimation(): boolean {
    try {
        if (sessionStorage.getItem(SKIP_ENTER_ANIM_KEY) === '1') {
            sessionStorage.removeItem(SKIP_ENTER_ANIM_KEY);
            return false;
        }
    } catch { /* ignore */ }
    return true;
}
