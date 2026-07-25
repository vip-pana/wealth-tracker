import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';

import { AdvisorWidgets } from '@/Components/Advisor/AdvisorWidgets';
import type { Widget } from '@/Components/Advisor/types';

afterEach(cleanup);

describe('AdvisorWidgets — proposal_offer visibility', () => {
    const offer: Widget[] = [{ type: 'proposal_offer', data: { kind: 'goal' } }];

    it('renders the offer button on the last turn', () => {
        render(<AdvisorWidgets widgets={offer} isLast onPropose={vi.fn()} />);
        expect(screen.getByRole('button', { name: /Genera la proposta di obiettivo/ })).toBeInTheDocument();
    });

    it('renders the offer button when isLast is unspecified (e.g. a proposal reply)', () => {
        // Only an explicit isLast === false suppresses it; undefined keeps it.
        render(<AdvisorWidgets widgets={offer} onPropose={vi.fn()} />);
        expect(screen.getByRole('button', { name: /Genera la proposta di obiettivo/ })).toBeInTheDocument();
    });

    it('suppresses a superseded offer button on a non-last turn', () => {
        render(<AdvisorWidgets widgets={offer} isLast={false} onPropose={vi.fn()} />);
        expect(screen.queryByRole('button', { name: /Genera la proposta/ })).not.toBeInTheDocument();
    });
});
