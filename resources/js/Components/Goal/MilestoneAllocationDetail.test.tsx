import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MilestoneAllocationDetail, type Segment } from '@/Components/Goal/MilestoneAllocationDetail';

const glide: Segment[] = [
    { category: 'Azioni', percentage: 55, color: '#3b82f6' },
    { category: 'Bitcoin', percentage: 25, color: '#f59e0b' },
    { category: 'Liquidità', percentage: 15, color: '#22c55e' },
    { category: 'Oro', percentage: 5, color: '#eab308' },
    { category: 'Obbligazioni', percentage: 0, color: '#a855f7' },
];

/** The category-label texts, in render order. */
function rowOrder(): string[] {
    return [...document.querySelectorAll('span.truncate')].map((s) => s.textContent ?? '');
}

describe('MilestoneAllocationDetail — rows', () => {
    it('renders nothing without segments', () => {
        const { container } = render(<MilestoneAllocationDetail segments={[]} targetValue={100000} />);
        expect(container.firstChild).toBeNull();
    });

    it('renders one row per category with its share', () => {
        render(<MilestoneAllocationDetail segments={glide} targetValue={100000} />);
        expect(screen.getByText('Azioni')).toBeInTheDocument();
        expect(screen.getByText('55%')).toBeInTheDocument();
        expect(screen.getByText('25%')).toBeInTheDocument();
    });

    it('keeps a 0% category visible — "no bonds yet" is information', () => {
        render(<MilestoneAllocationDetail segments={glide} targetValue={100000} />);
        expect(screen.getByText('Obbligazioni')).toBeInTheDocument();
        expect(screen.getByText('0%')).toBeInTheDocument();
    });

    it('orders rows by share, descending', () => {
        render(<MilestoneAllocationDetail segments={glide} targetValue={100000} />);
        expect(rowOrder()).toEqual(['Azioni', 'Bitcoin', 'Liquidità', 'Oro', 'Obbligazioni']);
    });
});

describe('MilestoneAllocationDetail — delta vs the previous step', () => {
    it('shows no delta on the first step', () => {
        render(<MilestoneAllocationDetail segments={glide} targetValue={100000} previous={null} />);
        expect(document.body.textContent).not.toContain('▲');
        expect(document.body.textContent).not.toContain('▼');
    });

    it('marks a rising share up and a falling share down', () => {
        render(
            <MilestoneAllocationDetail
                segments={[
                    { category: 'Azioni', percentage: 45 },
                    { category: 'Obbligazioni', percentage: 25 },
                ]}
                targetValue={500000}
                previous={[
                    { category: 'Azioni', percentage: 55 },
                    { category: 'Obbligazioni', percentage: 15 },
                ]}
                previousTargetValue={200000}
            />,
        );
        expect(document.body.textContent).toContain('▼−10%');
        expect(document.body.textContent).toContain('▲+10%');
    });

    it('dashes an unchanged share', () => {
        render(
            <MilestoneAllocationDetail
                segments={[{ category: 'Oro', percentage: 5 }]}
                targetValue={500000}
                previous={[{ category: 'Oro', percentage: 5 }]}
                previousTargetValue={200000}
            />,
        );
        expect(screen.getByText('—')).toBeInTheDocument();
    });

    it('gives no delta to a category absent from the previous step', () => {
        render(
            <MilestoneAllocationDetail
                segments={[{ category: 'Cripto', percentage: 10 }]}
                targetValue={500000}
                previous={[{ category: 'Azioni', percentage: 100 }]}
                previousTargetValue={200000}
            />,
        );
        expect(document.body.textContent).not.toContain('▲');
        expect(screen.queryByText('—')).not.toBeInTheDocument();
    });

    it('compares effective shares, not nominal ones, when a cap binds', () => {
        // Previous: Liquidità nominally 50% of 100k = 50k, capped at 20k → 20%,
        // and the freed 30 points go to Azioni (50% → 80%).
        // Current: same nominal split of 500k, where the 20k cap binds at 4%.
        // On nominal figures both steps read 50/50 and every delta would be 0.
        render(
            <MilestoneAllocationDetail
                segments={[
                    { category: 'Azioni', percentage: 50 },
                    { category: 'Liquidità', percentage: 50, cap_amount: 20000 },
                ]}
                targetValue={500000}
                previous={[
                    { category: 'Azioni', percentage: 50 },
                    { category: 'Liquidità', percentage: 50, cap_amount: 20000 },
                ]}
                previousTargetValue={100000}
            />,
        );
        // Liquidità falls from 20% to 4%, Azioni rises from 80% to 96%.
        expect(document.body.textContent).toContain('▼−16%');
        expect(document.body.textContent).toContain('▲+16%');
    });
});

describe('MilestoneAllocationDetail — caps', () => {
    it('annotates a capped category with its ceiling', () => {
        render(
            <MilestoneAllocationDetail
                segments={[
                    { category: 'Azioni', percentage: 60 },
                    { category: 'Liquidità', percentage: 40, cap_amount: 20000 },
                ]}
                targetValue={500000}
            />,
        );
        expect(document.body.textContent).toMatch(/Liquidità: tetto/);
    });

    it('adds no cap note when nothing is capped', () => {
        render(<MilestoneAllocationDetail segments={glide} targetValue={100000} />);
        expect(document.body.textContent).not.toContain('tetto');
    });
});
