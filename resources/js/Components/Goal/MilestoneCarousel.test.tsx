import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MilestoneCarousel, type MilestoneEntry } from '@/Components/Goal/MilestoneCarousel';

function entry(over: Partial<MilestoneEntry> & { id?: number; year?: string; value?: number } = {}): MilestoneEntry {
    const { id = 1, year = '2030', value = 50000, ...rest } = over;
    return {
        milestone: {
            id,
            target_value: value,
            target_date: `${year}-06-01`,
            notes: `Tappa ${id}`,
            ...(rest.milestone ?? {}),
        },
        achieved: rest.achieved ?? false,
        segments: rest.segments ?? [],
    };
}

const three: MilestoneEntry[] = [
    entry({ id: 1, year: '2028', value: 50000, achieved: true }),
    entry({ id: 2, year: '2030', value: 100000 }),
    entry({ id: 3, year: '2034', value: 200000 }),
];

afterEach(() => {
    vi.useRealTimers();
});

describe('MilestoneCarousel — opening step', () => {
    it('opens on the given index, not the first', () => {
        render(<MilestoneCarousel milestones={three} initialIndex={1} />);
        expect(screen.getByText('Tappa 2')).toBeInTheDocument();
        expect(screen.getByText('2/3')).toBeInTheDocument();
    });

    it('clamps an out-of-range index to the last step', () => {
        render(<MilestoneCarousel milestones={three} initialIndex={99} />);
        expect(screen.getByText('Tappa 3')).toBeInTheDocument();
    });

    it('renders nothing without milestones', () => {
        const { container } = render(<MilestoneCarousel milestones={[]} initialIndex={0} />);
        expect(container.firstChild).toBeNull();
    });
});

describe('MilestoneCarousel — one step at a time', () => {
    it('shows only the selected step detail', () => {
        render(<MilestoneCarousel milestones={three} initialIndex={1} />);
        expect(screen.getByText('Tappa 2')).toBeInTheDocument();
        expect(screen.queryByText('Tappa 1')).not.toBeInTheDocument();
        expect(screen.queryByText('Tappa 3')).not.toBeInTheDocument();
    });

    it('falls back to a message when the step has no detail', () => {
        render(
            <MilestoneCarousel
                milestones={[entry({ id: 9, milestone: { id: 9, target_value: 1000, target_date: '2030-01-01', notes: null } })]}
                initialIndex={0}
            />,
        );
        expect(screen.getByText('Nessun dettaglio per questa tappa.')).toBeInTheDocument();
    });

    it('shows the target allocation per category when the step has segments', () => {
        render(
            <MilestoneCarousel
                milestones={[entry({ id: 1, segments: [{ category: 'Azioni', percentage: 70, color: '#3b82f6' }, { category: 'Liquidità', percentage: 30, color: '#22c55e' }] })]}
                initialIndex={0}
            />,
        );
        expect(screen.getByText(/Allocazione target/)).toBeInTheDocument();
        expect(screen.getByText('Azioni')).toBeInTheDocument();
        expect(screen.getByText('70%')).toBeInTheDocument();
        expect(screen.getByText('30%')).toBeInTheDocument();
    });

    it('measures the allocation delta against the previous step', async () => {
        const user = userEvent.setup();
        render(
            <MilestoneCarousel
                milestones={[
                    entry({ id: 1, year: '2028', value: 100000, segments: [{ category: 'Azioni', percentage: 60 }, { category: 'Obbligazioni', percentage: 40 }] }),
                    entry({ id: 2, year: '2032', value: 200000, segments: [{ category: 'Azioni', percentage: 45 }, { category: 'Obbligazioni', percentage: 55 }] }),
                ]}
                initialIndex={0}
            />,
        );
        // First step: nothing to compare against.
        expect(document.body.textContent).not.toContain('▲');

        await user.click(screen.getByLabelText('Milestone successiva'));
        expect(document.body.textContent).toContain('▼−15%');
        expect(document.body.textContent).toContain('▲+15%');
    });
});

describe('MilestoneCarousel — navigation', () => {
    it('steps forward with the arrow', async () => {
        const user = userEvent.setup();
        render(<MilestoneCarousel milestones={three} initialIndex={0} />);

        await user.click(screen.getByLabelText('Milestone successiva'));
        expect(screen.getByText('Tappa 2')).toBeInTheDocument();
    });

    it('steps backward with the arrow, wrapping to the last', async () => {
        const user = userEvent.setup();
        render(<MilestoneCarousel milestones={three} initialIndex={0} />);

        await user.click(screen.getByLabelText('Milestone precedente'));
        expect(screen.getByText('Tappa 3')).toBeInTheDocument();
        expect(screen.getByText('3/3')).toBeInTheDocument();
    });

    it('wraps forward past the last step', async () => {
        const user = userEvent.setup();
        render(<MilestoneCarousel milestones={three} initialIndex={2} />);

        await user.click(screen.getByLabelText('Milestone successiva'));
        expect(screen.getByText('Tappa 1')).toBeInTheDocument();
    });

    it('jumps straight to a step from the timeline', async () => {
        const user = userEvent.setup();
        render(<MilestoneCarousel milestones={three} initialIndex={0} />);

        await user.click(screen.getByLabelText('Milestone 2034'));
        expect(screen.getByText('Tappa 3')).toBeInTheDocument();
    });

    it('hides the arrows and the timeline with a single step', () => {
        render(<MilestoneCarousel milestones={[entry()]} initialIndex={0} />);
        expect(screen.queryByLabelText('Milestone successiva')).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Milestone 2030')).not.toBeInTheDocument();
    });
});

describe('MilestoneCarousel — no auto-rotation', () => {
    it('stays on the same step no matter how much time passes', () => {
        vi.useFakeTimers();
        render(<MilestoneCarousel milestones={three} initialIndex={1} />);

        expect(screen.getByText('Tappa 2')).toBeInTheDocument();
        act(() => vi.advanceTimersByTime(60000));
        // Unlike the dashboard carousel, this one only moves when asked to.
        expect(screen.getByText('Tappa 2')).toBeInTheDocument();
        expect(screen.getByText('2/3')).toBeInTheDocument();
    });
});

describe('MilestoneCarousel — achieved steps', () => {
    it('strikes through a reached step in the timeline', () => {
        render(<MilestoneCarousel milestones={three} initialIndex={1} />);

        const reached = screen.getByLabelText('Milestone 2028');
        expect(reached.querySelector('span.line-through')).not.toBeNull();
    });

    it('does not strike through an unreached step', () => {
        render(<MilestoneCarousel milestones={three} initialIndex={1} />);

        const pending = screen.getByLabelText('Milestone 2034');
        expect(pending.querySelector('span.line-through')).toBeNull();
    });
});
