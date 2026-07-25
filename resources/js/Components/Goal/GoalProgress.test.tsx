import { describe, it, expect, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import { GoalProgress } from '@/Components/Goal/GoalProgress';
import type { Goal, GoalCategoryAllocation, GoalMilestone, Category } from '@/types/models';
import type { CurrentAllocationItem, CurrentMacroAllocationItem } from '@/Components/Goal/types';

// Inertia's <Head> is the only Inertia surface GoalProgress touches; stub it to
// a no-op so the component renders in isolation.
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
}));

const CATEGORIES: Pick<Category, 'id' | 'name' | 'color' | 'macro_category'>[] = [
    { id: 1, name: 'Liquidità', color: '#60a5fa', macro_category: 'Liquidità' },
    { id: 2, name: 'ETF', color: '#34d399', macro_category: 'ETF' },
];

function makeGoal(over: Partial<Goal> = {}): Goal {
    return {
        id: 1,
        name: 'FIRE',
        description: null,
        target_value: 100000,
        target_date: null,
        categoryAllocations: [],
        macroAllocations: [],
        milestones: [],
        ...over,
    };
}

function renderProgress(over: {
    goal?: Partial<Goal>;
    currentNetWorth?: number | null;
    currentAllocation?: CurrentAllocationItem[];
    currentMacroAllocation?: CurrentMacroAllocationItem[];
    today?: string;
} = {}) {
    render(
        <GoalProgress
            goal={makeGoal(over.goal)}
            categories={CATEGORIES}
            currentNetWorth={'currentNetWorth' in over ? (over.currentNetWorth ?? null) : 50000}
            currentAllocation={over.currentAllocation ?? []}
            currentMacroAllocation={over.currentMacroAllocation ?? []}
            today={over.today ?? '2026-01-01'}
            onEdit={vi.fn()}
            onDelete={vi.fn()}
        />,
    );
}

describe('GoalProgress — progress percentage', () => {
    // "{pct}% raggiunto" is two adjacent JSX text nodes; assert the combined
    // text is present somewhere rather than pinning an exact node.
    const hasReached = (pct: string) =>
        Array.from(document.querySelectorAll('span')).some(
            (s) => s.textContent === `${pct}% raggiunto`,
        );

    it('computes the reached percentage', () => {
        renderProgress({ currentNetWorth: 50000, goal: { target_value: 100000 } });
        expect(hasReached('50.0')).toBe(true);
    });

    it('caps progress at 100% when current exceeds target', () => {
        renderProgress({ currentNetWorth: 150000, goal: { target_value: 100000 } });
        expect(hasReached('100.0')).toBe(true);
    });

    it('treats a null net worth as zero progress', () => {
        renderProgress({ currentNetWorth: null, goal: { target_value: 100000, categoryAllocations: [] } });
        expect(hasReached('0.0')).toBe(true);
    });
});

describe('GoalProgress — required growth', () => {
    it('shows monthly and annual growth when a future target date is set', () => {
        // Target date far in the future keeps monthsUntil positive regardless of
        // the real clock, so the growth block always renders.
        renderProgress({
            currentNetWorth: 50000,
            goal: { target_value: 100000, target_date: '2099-01-01' },
        });
        expect(screen.getByText('Crescita mensile necessaria')).toBeInTheDocument();
        expect(screen.getByText('Equivalente annuale')).toBeInTheDocument();
    });

    it('omits the growth block when there is no target date', () => {
        renderProgress({ goal: { target_date: null } });
        expect(screen.queryByText('Crescita mensile necessaria')).not.toBeInTheDocument();
    });
});

describe('GoalProgress — allocation deviation table', () => {
    const categoryAllocations: GoalCategoryAllocation[] = [
        { category_id: 1, macro_category: null, percentage: 30 },
        { category_id: 2, macro_category: null, percentage: 70 },
    ];

    it('renders one row per target allocation with current vs target percentages', () => {
        renderProgress({
            currentNetWorth: 50000,
            goal: { categoryAllocations },
            // Current split 50/50 → Liquidità is over target (30), ETF under (70).
            currentAllocation: [
                { category_id: 1, value: 25000 },
                { category_id: 2, value: 25000 },
            ],
        });
        const table = screen.getByRole('table');
        // Liquidità row: current 50%, target 30%, delta +20pp.
        const liqRow = within(table).getByText('Liquidità').closest('tr')!;
        expect(within(liqRow).getByText('50%')).toBeInTheDocument();
        expect(within(liqRow).getByText('30%')).toBeInTheDocument();
        expect(within(liqRow).getByText('+20%')).toBeInTheDocument();
    });

    it('prompts for a snapshot when net worth is null', () => {
        renderProgress({ currentNetWorth: null, goal: { categoryAllocations } });
        expect(document.body.textContent).toContain('Nessuno snapshot disponibile');
    });
});

describe('GoalProgress — milestones', () => {
    const milestones: GoalMilestone[] = [
        { id: 2, notes: 'Secondo', target_value: 80000, target_date: '2030-01-01' },
        { id: 1, notes: 'Primo', target_value: 40000, target_date: '2025-01-01' },
    ];

    it('renders the milestone card sorted by date (earliest first)', () => {
        renderProgress({ currentNetWorth: 50000, goal: { milestones } });
        const notes = screen.getAllByText(/Primo|Secondo/).map((n) => n.textContent);
        // "Primo" (2025) must appear before "Secondo" (2030).
        expect(notes.indexOf('Primo')).toBeLessThan(notes.indexOf('Secondo'));
    });

    it('does not render the milestone card when there are none', () => {
        renderProgress({ goal: { milestones: [] } });
        expect(screen.queryByText('Milestone')).not.toBeInTheDocument();
    });
});
