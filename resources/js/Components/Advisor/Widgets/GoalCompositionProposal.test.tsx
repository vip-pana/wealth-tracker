import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

const post = vi.fn();
vi.mock('@inertiajs/react', () => ({
    router: { post: (...args: unknown[]) => post(...args) },
}));

import { GoalCompositionProposal } from '@/Components/Advisor/Widgets/GoalCompositionProposal';

const data = {
    buckets: [
        { category: 'Azioni', percentage: 70 },
        { category: 'Liquidità', percentage: 30 },
    ],
    rationale: 'Peso azionario alto per orizzonte lungo.',
    total_pct: 100,
};

describe('GoalCompositionProposal', () => {
    beforeEach(() => post.mockReset());

    it('shows the rationale and the suggested buckets', () => {
        render(<GoalCompositionProposal data={data} />);
        expect(screen.getByText(/orizzonte lungo/)).toBeInTheDocument();
        expect(screen.getByLabelText('Percentuale Azioni')).toHaveValue(70);
    });

    it('POSTs the USER-EDITED percentages, not the suggested ones', async () => {
        const user = userEvent.setup();
        render(<GoalCompositionProposal data={data} />);

        const azioni = screen.getByLabelText('Percentuale Azioni');
        await user.clear(azioni);
        await user.type(azioni, '60');

        await user.click(screen.getByRole('button', { name: 'Applica' }));

        expect(post.mock.calls[0][0]).toBe('/advisor/goal/composition');
        expect(post.mock.calls[0][1]).toEqual({
            allocations: [
                { category: 'Azioni', percentage: 60 },
                { category: 'Liquidità', percentage: 30 },
            ],
        });
    });

    it('warns when the total is not 100 but still allows applying', async () => {
        const user = userEvent.setup();
        render(<GoalCompositionProposal data={data} />);

        const azioni = screen.getByLabelText('Percentuale Azioni');
        await user.clear(azioni);
        await user.type(azioni, '50'); // total now 80

        expect(screen.getByText(/di solito una composizione somma al 100%/)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Applica' })).toBeEnabled();
    });
});
