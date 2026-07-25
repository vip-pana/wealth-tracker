import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

const post = vi.fn();
vi.mock('@inertiajs/react', () => ({
    router: { post: (...args: unknown[]) => post(...args) },
}));

import { GoalCoreProposal } from '@/Components/Advisor/Widgets/GoalCoreProposal';

describe('GoalCoreProposal', () => {
    beforeEach(() => post.mockReset());

    it('shows only the proposed fields', () => {
        render(<GoalCoreProposal data={{ target_value: 1000000, description: 'Primo milione' }} />);
        expect(screen.getByText('Importo target')).toBeInTheDocument();
        expect(screen.getByText('Primo milione')).toBeInTheDocument();
        expect(screen.queryByText('Data target')).not.toBeInTheDocument();
    });

    it('POSTs the proposal to /advisor/goal on Applica', async () => {
        const user = userEvent.setup();
        render(<GoalCoreProposal data={{ target_value: 500000, target_date: '2099-12-31' }} />);

        await user.click(screen.getByRole('button', { name: 'Applica' }));

        expect(post.mock.calls[0][0]).toBe('/advisor/goal');
        expect(post.mock.calls[0][1]).toEqual({ target_value: 500000, target_date: '2099-12-31' });
        expect(post.mock.calls[0][2]).toMatchObject({ preserveState: true });
    });

    it('renders as already applied when the goal already matches (survives refresh)', () => {
        render(
            <GoalCoreProposal
                data={{ target_value: 1000000, target_date: '2099-12-31' }}
                goal={{
                    name: 'G', description: null, target_value: 1000000, target_date: '2099-12-31',
                    milestones: [], allocations: [],
                }}
            />,
        );
        expect(screen.getByText(/Obiettivo aggiornato/)).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Applica' })).not.toBeInTheDocument();
    });

    it('stays clickable when the goal only partially matches', () => {
        render(
            <GoalCoreProposal
                data={{ target_value: 1000000, target_date: '2099-12-31' }}
                goal={{
                    name: 'G', description: null, target_value: 500000, target_date: '2099-12-31',
                    milestones: [], allocations: [],
                }}
            />,
        );
        expect(screen.getByRole('button', { name: 'Applica' })).toBeInTheDocument();
    });
});
