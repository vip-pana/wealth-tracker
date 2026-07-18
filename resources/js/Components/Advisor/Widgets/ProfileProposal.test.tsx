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
        render(<ProfileProposal data={{ horizon: 'short', notes: 'Prudente' }} />);

        await user.click(screen.getByRole('button', { name: 'Applica' }));

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toBe('/advisor/profile');
        expect(post.mock.calls[0][1]).toEqual({ horizon: 'short', notes: 'Prudente' });
        // Partial reload of the profile prop so the profile dialog updates, while
        // preserveState keeps the open chat from remounting.
        expect(post.mock.calls[0][2]).toMatchObject({ preserveState: true, only: ['profile'] });
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

    it('renders as already applied when the profile already matches the proposal (survives refresh)', () => {
        render(
            <ProfileProposal
                data={{ horizon: 'long', risk_tolerance: 'high' }}
                profile={{ horizon: 'long', risk_tolerance: 'high', notes: null }}
            />,
        );
        // Local state is gone after a refresh; matching the current profile means
        // it was applied, so no clickable button is offered again.
        expect(screen.getByText(/Profilo aggiornato/)).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Applica' })).not.toBeInTheDocument();
    });

    it('stays clickable when the profile only partially matches the proposal', () => {
        render(
            <ProfileProposal
                data={{ horizon: 'long', risk_tolerance: 'high' }}
                profile={{ horizon: 'long', risk_tolerance: 'low', notes: null }}
            />,
        );
        // risk_tolerance differs, so the proposal was not fully applied.
        expect(screen.getByRole('button', { name: 'Applica' })).toBeInTheDocument();
    });
});
