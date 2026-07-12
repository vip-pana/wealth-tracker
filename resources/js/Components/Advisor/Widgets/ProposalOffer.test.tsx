import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { ProposalOffer } from '@/Components/Advisor/Widgets/ProposalOffer';

describe('ProposalOffer', () => {
    it('shows the profile button and calls onPropose with the kind on click', async () => {
        const user = userEvent.setup();
        const onPropose = vi.fn();
        render(<ProposalOffer data={{ kind: 'profile' }} onPropose={onPropose} />);

        await user.click(screen.getByRole('button', { name: /Genera la proposta di profilo/ }));

        expect(onPropose).toHaveBeenCalledTimes(1);
        expect(onPropose).toHaveBeenCalledWith('profile');
    });

    it('shows the goal label for a goal offer', () => {
        render(<ProposalOffer data={{ kind: 'goal' }} onPropose={vi.fn()} />);
        expect(screen.getByRole('button', { name: /Genera la proposta di obiettivo/ })).toBeInTheDocument();
    });

    it('disables after one click so a double-tap fires one generation', async () => {
        const user = userEvent.setup();
        const onPropose = vi.fn();
        render(<ProposalOffer data={{ kind: 'profile' }} onPropose={onPropose} />);

        const btn = screen.getByRole('button');
        await user.click(btn);
        await user.click(btn);

        expect(onPropose).toHaveBeenCalledTimes(1);
        expect(btn).toBeDisabled();
    });

    it('renders nothing without an onPropose handler', () => {
        const { container } = render(<ProposalOffer data={{ kind: 'profile' }} />);
        expect(container).toBeEmptyDOMElement();
    });
});
