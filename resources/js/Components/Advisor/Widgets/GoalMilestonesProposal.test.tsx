import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

const post = vi.fn();
vi.mock('@inertiajs/react', () => ({
    router: { post: (...args: unknown[]) => post(...args) },
}));

import { GoalMilestonesProposal } from '@/Components/Advisor/Widgets/GoalMilestonesProposal';

const data = {
    milestones: [
        { label: 'Metà', action: 'Sposta 5% da Bitcoin a Obbligazioni.', rationale: 'Riduce la volatilità.', target_value: 500000, target_date: '2080-01-01', allocation: [{ category: 'Azioni', percentage: 70 }, { category: 'Liquidità', percentage: 30 }] },
        { label: null, action: null, rationale: null, target_value: 750000, target_date: '2090-01-01', allocation: [] },
    ],
};

describe('GoalMilestonesProposal', () => {
    beforeEach(() => post.mockReset());

    it('lists the proposed milestones with a fallback label and shows action/rationale/allocation', () => {
        render(<GoalMilestonesProposal data={data} />);
        expect(screen.getByText('Metà')).toBeInTheDocument();
        expect(screen.getByText('Tappa 2')).toBeInTheDocument();
        expect(screen.getByText(/Sposta 5% da Bitcoin/)).toBeInTheDocument();
        expect(screen.getByText(/Riduce la volatilità/)).toBeInTheDocument();
        // The allocation renders as a stacked bar with a legend (heading +
        // per-category label spans), not the old "Azioni 70% · …" text.
        expect(screen.getByText('Allocazione target')).toBeInTheDocument();
        const legend = [...document.querySelectorAll('span')].map((s) => s.textContent?.replace(/\s+/g, ' ').trim());
        expect(legend).toContain('Azioni 70%');
        expect(legend).toContain('Liquidità 30%');
    });

    it('POSTs the milestones with label/action/rationale/allocation on Applica', async () => {
        const user = userEvent.setup();
        render(<GoalMilestonesProposal data={data} />);

        await user.click(screen.getByRole('button', { name: 'Applica' }));

        expect(post.mock.calls[0][0]).toBe('/advisor/goal/milestones');
        expect(post.mock.calls[0][1]).toEqual({
            milestones: [
                { notes: 'Metà', action: 'Sposta 5% da Bitcoin a Obbligazioni.', rationale: 'Riduce la volatilità.', target_value: 500000, target_date: '2080-01-01', allocation: [{ category: 'Azioni', percentage: 70 }, { category: 'Liquidità', percentage: 30 }] },
                { notes: null, action: null, rationale: null, target_value: 750000, target_date: '2090-01-01', allocation: [] },
            ],
        });
    });

    it('renders as already applied when values, dates AND allocation match (labels ignored)', () => {
        render(
            <GoalMilestonesProposal
                data={data}
                goal={{
                    name: 'G', description: null, target_value: 1000000, target_date: null,
                    milestones: [
                        { notes: 'diverso', target_value: 500000, target_date: '2080-01-01', allocation: [{ category: 'Azioni', percentage: 70 }, { category: 'Liquidità', percentage: 30 }] },
                        { notes: null, target_value: 750000, target_date: '2090-01-01', allocation: [] },
                    ],
                    allocations: [],
                }}
            />,
        );
        expect(screen.getByText(/Tappe salvate/)).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Applica' })).not.toBeInTheDocument();
    });

    it('is NOT already applied when the proposal only adds a cap to matching milestones', () => {
        // Same amounts/dates/percentages as stored, but the proposal introduces a
        // liquidity cap. The card must let the user apply it, not show "saved".
        const withCap = {
            milestones: [
                { label: 'Metà', action: null, rationale: null, target_value: 500000, target_date: '2080-01-01', allocation: [{ category: 'Azioni', percentage: 70 }, { category: 'Liquidità', percentage: 30, cap_amount: 50000 }] },
            ],
        };
        render(
            <GoalMilestonesProposal
                data={withCap}
                goal={{
                    name: 'G', description: null, target_value: 1000000, target_date: null,
                    milestones: [
                        // Stored without a cap.
                        { notes: null, target_value: 500000, target_date: '2080-01-01', allocation: [{ category: 'Azioni', percentage: 70 }, { category: 'Liquidità', percentage: 30 }] },
                    ],
                    allocations: [],
                }}
            />,
        );
        expect(screen.queryByText(/Tappe salvate/)).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Applica' })).toBeInTheDocument();
    });
});
