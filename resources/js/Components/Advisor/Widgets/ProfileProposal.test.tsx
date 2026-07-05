import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

const post = vi.fn();
vi.mock('@inertiajs/react', () => ({
    router: { post: (...args: unknown[]) => post(...args) },
}));

import { ProfileProposal } from '@/Components/Advisor/Widgets/ProfileProposal';

describe('ProfileProposal', () => {
    beforeEach(() => post.mockReset());

    it('shows only the proposed fields with readable labels', () => {
        render(<ProfileProposal data={{ horizon: 'long', risk_tolerance: 'medium' }} />);
        expect(screen.getByText('Orizzonte')).toBeInTheDocument();
        expect(screen.getByText(/Lungo/)).toBeInTheDocument();
        expect(screen.getByText('Media')).toBeInTheDocument();
        // A field not proposed is not rendered.
        expect(screen.queryByText('Allocazione target')).not.toBeInTheDocument();
    });

    it('POSTs the proposal to /advisor/profile on Applica', async () => {
        const user = userEvent.setup();
        render(<ProfileProposal data={{ horizon: 'short', objective: 'Casa' }} />);

        await user.click(screen.getByRole('button', { name: 'Applica' }));

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toBe('/advisor/profile');
        expect(post.mock.calls[0][1]).toEqual({ horizon: 'short', objective: 'Casa' });
    });

    it('shows a confirmation and stops offering Applica once applied', async () => {
        // Drive the onSuccess callback so the applied state is reached.
        post.mockImplementation((_url: unknown, _data: unknown, opts: { onSuccess?: () => void }) => opts?.onSuccess?.());
        const user = userEvent.setup();
        render(<ProfileProposal data={{ risk_tolerance: 'high' }} />);

        await user.click(screen.getByRole('button', { name: 'Applica' }));

        expect(screen.getByText(/Profilo aggiornato/)).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Applica' })).not.toBeInTheDocument();
    });

    it('dismisses without posting on Annulla', async () => {
        const user = userEvent.setup();
        render(<ProfileProposal data={{ horizon: 'medium' }} />);

        await user.click(screen.getByRole('button', { name: 'Annulla' }));

        expect(post).not.toHaveBeenCalled();
        expect(screen.getByText(/Proposta annullata/)).toBeInTheDocument();
    });
});
