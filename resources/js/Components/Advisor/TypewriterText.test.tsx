import { describe, it, expect, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { TypewriterText, markSessionForTitleAnimation } from '@/Components/Advisor/TypewriterText';

describe('TypewriterText', () => {
    beforeEach(() => {
        sessionStorage.clear();
    });

    it('renders the full title immediately when not marked for animation', () => {
        render(<TypewriterText id={1} text="Analisi di giugno" />);
        expect(screen.getByText('Analisi di giugno')).toBeInTheDocument();
    });

    it('does not replay the animation for the same id on a remount', () => {
        markSessionForTitleAnimation(7);
        // First mount claims the animation (starts empty).
        const { unmount } = render(<TypewriterText id={7} text="Nuova chat" />);
        unmount();
        // A later remount (e.g. the chat poll re-rendering the list) must show
        // the title outright, not animate again.
        render(<TypewriterText id={7} text="Nuova chat" />);
        expect(screen.getByText('Nuova chat')).toBeInTheDocument();
    });
});
