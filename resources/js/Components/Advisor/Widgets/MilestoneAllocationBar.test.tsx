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
});
