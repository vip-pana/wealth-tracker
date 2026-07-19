import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';

import { MilestoneAllocationBar } from '@/Components/Advisor/Widgets/MilestoneAllocationBar';

describe('MilestoneAllocationBar', () => {
    it('renders a coloured segment and a legend label per category', () => {
        render(
            <MilestoneAllocationBar
                segments={[
                    { category: 'Azioni', percentage: 70, color: '#3b82f6' },
                    { category: 'Liquidità', percentage: 30, color: '#22c55e' },
                ]}
            />,
        );

        // Legend labels (adjacent text nodes, so match on normalised textContent).
        const labels = [...document.querySelectorAll('span')].map((s) => s.textContent?.replace(/\s+/g, ' ').trim());
        expect(labels).toContain('Azioni 70%');
        expect(labels).toContain('Liquidità 30%');

        // The two bar segments carry their category width + colour.
        const azioni = screen.getByTitle('Azioni 70%');
        expect(azioni).toHaveStyle({ width: '70%', backgroundColor: '#3b82f6' });
    });

    it('falls back to grey when a segment has no colour', () => {
        render(<MilestoneAllocationBar segments={[{ category: 'Oro', percentage: 100 }]} />);
        expect(screen.getByTitle('Oro 100%')).toHaveStyle({ backgroundColor: '#94a3b8' });
    });

    it('renders nothing when there are no segments', () => {
        const { container } = render(<MilestoneAllocationBar segments={[]} />);
        expect(container).toBeEmptyDOMElement();
    });

    it('shows the effective (post-cap) allocation when a cap binds', () => {
        // Liquidità 15% capped at 50k at a 1M milestone → effective 5%, the freed
        // 10pp spread over Azioni (the only uncapped row) → 95%.
        render(
            <MilestoneAllocationBar
                targetValue={1_000_000}
                segments={[
                    { category: 'Azioni', percentage: 85, color: '#3b82f6' },
                    { category: 'Liquidità', percentage: 15, color: '#22c55e', cap_amount: 50_000 },
                ]}
            />,
        );

        // Bar + legend show the capped 5%, not the nominal 15%.
        expect(screen.getByTitle('Liquidità 5%')).toHaveStyle({ width: '5%' });
        expect(screen.getByTitle('Azioni 95%')).toHaveStyle({ width: '95%' });
        const labels = [...document.querySelectorAll('span')].map((s) => s.textContent?.replace(/\s+/g, ' ').trim());
        expect(labels.some((l) => l?.startsWith('Liquidità 5%'))).toBe(true);
        // The capped row is annotated with its ceiling.
        expect(labels.some((l) => l?.includes('tetto'))).toBe(true);
    });

    it('leaves the nominal percentages when the cap is not yet binding', () => {
        // 15% of 250k = 37.5k ≤ 50k cap → no clamp.
        render(
            <MilestoneAllocationBar
                targetValue={250_000}
                segments={[
                    { category: 'Azioni', percentage: 85, color: '#3b82f6' },
                    { category: 'Liquidità', percentage: 15, color: '#22c55e', cap_amount: 50_000 },
                ]}
            />,
        );
        expect(screen.getByTitle('Liquidità 15%')).toBeInTheDocument();
    });
});
