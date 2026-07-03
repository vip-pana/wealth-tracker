import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, act } from '@testing-library/react';
import { ThinkingWithFacts } from '@/Components/Advisor/ThinkingWithFacts';

// The component shuffles facts with Math.random and rotates them on timers.
// Freeze both so the reveal/rotate behaviour is deterministic.
beforeEach(() => {
    vi.useFakeTimers();
    // Return 0 → the Fisher-Yates shuffle keeps the original order.
    vi.spyOn(Math, 'random').mockReturnValue(0);
});
afterEach(() => {
    vi.restoreAllMocks();
    vi.useRealTimers();
});

describe('ThinkingWithFacts', () => {
    it('shows the label immediately', () => {
        render(<ThinkingWithFacts facts={['Fatto A']} label="Sto pensando…" />);
        expect(screen.getByText('Sto pensando…')).toBeInTheDocument();
    });

    it('reveals a fact right away when revealDelay is 0', () => {
        render(<ThinkingWithFacts facts={['Fatto unico']} revealDelay={0} />);
        expect(screen.getByText('Fatto unico')).toBeInTheDocument();
    });

    it('holds the fact back until revealDelay elapses', () => {
        render(<ThinkingWithFacts facts={['Fatto A']} revealDelay={2000} />);
        // Not shown yet.
        expect(screen.queryByText('Fatto A')).not.toBeInTheDocument();
        act(() => { vi.advanceTimersByTime(2000); });
        expect(screen.getByText('Fatto A')).toBeInTheDocument();
    });

    it('rotates to the other fact every 5s once revealed', () => {
        // With Math.random pinned the shuffle is deterministic; assert the shown
        // fact changes to the other one after 5s rather than which comes first.
        render(<ThinkingWithFacts facts={['Fatto A', 'Fatto B']} revealDelay={0} />);
        const first = screen.getByText(/Fatto [AB]/).textContent;
        act(() => { vi.advanceTimersByTime(5000); });
        const second = screen.getByText(/Fatto [AB]/).textContent;
        expect(second).not.toBe(first);
    });

    it('does not rotate with a single fact', () => {
        render(<ThinkingWithFacts facts={['Solo uno']} revealDelay={0} />);
        act(() => { vi.advanceTimersByTime(5000); });
        expect(screen.getByText('Solo uno')).toBeInTheDocument();
    });

    it('renders no fact line when the list is empty', () => {
        const { container } = render(<ThinkingWithFacts facts={[]} revealDelay={0} label="X" />);
        // Only the label paragraph exists; no italic fact paragraph.
        expect(container.querySelector('p.italic')).toBeNull();
    });
});
