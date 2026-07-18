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
        { label: 'Metà', target_value: 500000, target_date: '2080-01-01' },
        { label: null, target_value: 750000, target_date: '2090-01-01' },
    ],
};

describe('GoalMilestonesProposal', () => {
    beforeEach(() => post.mockReset());

    it('lists the proposed milestones with a fallback label', () => {
        render(<GoalMilestonesProposal data={data} />);
        expect(screen.getByText('Metà')).toBeInTheDocument();
        expect(screen.getByText('Tappa 2')).toBeInTheDocument();
    });

    it('POSTs the milestones with label mapped to notes on Applica', async () => {
        const user = userEvent.setup();
        render(<GoalMilestonesProposal data={data} />);

        await user.click(screen.getByRole('button', { name: 'Applica' }));

        expect(post.mock.calls[0][0]).toBe('/advisor/goal/milestones');
        expect(post.mock.calls[0][1]).toEqual({
            milestones: [
                { notes: 'Metà', target_value: 500000, target_date: '2080-01-01' },
                { notes: null, target_value: 750000, target_date: '2090-01-01' },
            ],
        });
    });

    it('renders as already applied when values and dates match (labels ignored)', () => {
        render(
            <GoalMilestonesProposal
                data={data}
                goal={{
                    name: 'G', description: null, target_value: 1000000, target_date: null,
                    milestones: [
                        { notes: 'diverso', target_value: 500000, target_date: '2080-01-01' },
                        { notes: null, target_value: 750000, target_date: '2090-01-01' },
                    ],
                    allocations: [],
                }}
            />,
        );
        expect(screen.getByText(/Tappe salvate/)).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Applica' })).not.toBeInTheDocument();
    });
});
