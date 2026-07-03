import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MilestoneAccordionItem } from '@/Components/Goal/MilestoneAccordionItem';

const milestone = (over: Partial<{ id: number; target_value: number; target_date: string; notes: string | null }> = {}) => ({
    id: 1,
    target_value: 50000,
    target_date: '2030-06-01',
    notes: 'Prima tappa',
    ...over,
});

describe('MilestoneAccordionItem', () => {
    it('shows the target year', () => {
        render(<MilestoneAccordionItem milestone={milestone()} achieved={false} defaultOpen={false} />);
        expect(screen.getByText('2030')).toBeInTheDocument();
    });

    it('strikes through the label when achieved', () => {
        render(<MilestoneAccordionItem milestone={milestone()} achieved defaultOpen={false} />);
        // line-through sits on the outer label span (the one carrying flex-1).
        const label = screen.getByText('2030').closest('span.flex-1');
        expect(label?.className).toContain('line-through');
    });

    it('does not strike through when not achieved', () => {
        render(<MilestoneAccordionItem milestone={milestone()} achieved={false} defaultOpen={false} />);
        const label = screen.getByText('2030').closest('span.flex-1');
        expect(label?.className).not.toContain('line-through');
    });

    it('reveals the notes when opened by default', () => {
        render(<MilestoneAccordionItem milestone={milestone({ notes: 'Dettaglio tappa' })} achieved={false} defaultOpen />);
        expect(screen.getByText('Dettaglio tappa')).toBeInTheDocument();
    });

    it('toggles the notes open and closed on click', async () => {
        render(<MilestoneAccordionItem milestone={milestone({ notes: 'Dettaglio tappa' })} achieved={false} defaultOpen={false} />);
        expect(screen.queryByText('Dettaglio tappa')).not.toBeInTheDocument();
        await userEvent.click(screen.getByRole('button'));
        expect(screen.getByText('Dettaglio tappa')).toBeInTheDocument();
        await userEvent.click(screen.getByRole('button'));
        expect(screen.queryByText('Dettaglio tappa')).not.toBeInTheDocument();
    });

    it('has no expand chevron when there are no notes', async () => {
        render(<MilestoneAccordionItem milestone={milestone({ notes: null })} achieved={false} defaultOpen={false} />);
        // Clicking still toggles state, but there is nothing to reveal.
        await userEvent.click(screen.getByRole('button'));
        expect(screen.queryByText('Prima tappa')).not.toBeInTheDocument();
    });
});
