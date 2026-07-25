import { describe, it, expect, beforeEach } from 'vitest';
import { markInternalNavigation, claimEnterAnimation } from '@/Components/Advisor/enterAnimation';

describe('enterAnimation', () => {
    beforeEach(() => {
        sessionStorage.clear();
    });

    it('animates by default (no stamp)', () => {
        expect(claimEnterAnimation()).toBe(true);
    });

    it('skips the animation once after an internal navigation, then animates again', () => {
        markInternalNavigation();
        // First claim after an internal navigation consumes the flag → no animation.
        expect(claimEnterAnimation()).toBe(false);
        // The flag is one-shot: a subsequent mount animates like any other page.
        expect(claimEnterAnimation()).toBe(true);
    });

    it('clears the stored flag when claimed', () => {
        markInternalNavigation();
        claimEnterAnimation();
        expect(sessionStorage.getItem('advisor:skip-enter-anim')).toBeNull();
    });
});
