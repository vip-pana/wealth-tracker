import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useState } from 'react';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Category, Goal } from '@/types/models';

// GoalFormDialog manages its allocation and milestone lists locally through
// Inertia's useForm, submitting via post (create) or put (edit). We back
// useForm with real React state and spy on the two submit paths.

const post = vi.fn();
const put = vi.fn();

vi.mock('@inertiajs/react', () => ({
    useForm: (initial: Record<string, unknown>) => {
        const [data, setState] = useState(initial);
        const setData = (key: string | Record<string, unknown>, value?: unknown) => {
            if (typeof key === 'object') {
                setState(key);
            } else {
                setState((d: Record<string, unknown>) => ({ ...d, [key]: value }));
            }
        };
        return {
            data,
            setData,
            post,
            put,
            processing: false,
            errors: {},
            reset: () => setState(initial),
        };
    },
    Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
}));

import { GoalFormDialog } from '@/Components/Goal/GoalFormDialog';

const CATEGORIES: Pick<Category, 'id' | 'name' | 'color' | 'macro_category'>[] = [
    { id: 1, name: 'Liquidità', color: '#0af', macro_category: 'liquidity' },
    { id: 2, name: 'ETF', color: '#fa0', macro_category: 'investment' },
];

function renderDialog(overrides: Partial<React.ComponentProps<typeof GoalFormDialog>> = {}) {
    const props = {
        open: true,
        onClose: vi.fn(),
        categories: CATEGORIES,
        goal: null as Goal | null,
        ...overrides,
    };
    render(<GoalFormDialog {...props} />);
    return props;
}

beforeEach(() => {
    post.mockClear();
    put.mockClear();
});

describe('GoalFormDialog — per-milestone allocation', () => {
    it('adds an allocation row inside a milestone', async () => {
        renderDialog();
        // No allocation UI until a milestone exists.
        expect(screen.queryByText('Seleziona categoria')).not.toBeInTheDocument();
        await userEvent.click(screen.getByRole('button', { name: /Aggiungi milestone/ }));
        // The milestone now carries its own allocation section with an adder.
        await userEvent.click(screen.getByRole('button', { name: 'Aggiungi' }));
        expect(screen.getByText('Seleziona categoria')).toBeInTheDocument();
    });

    it('shows the running allocation total and flags completion at 100%', async () => {
        renderDialog();
        await userEvent.click(screen.getByRole('button', { name: /Aggiungi milestone/ }));
        await userEvent.click(screen.getByRole('button', { name: 'Aggiungi' }));
        const pctInput = screen.getByPlaceholderText('0');
        await userEvent.type(pctInput, '100');
        expect(screen.getByText('Allocazione completa')).toBeInTheDocument();
    });

    it('removes an allocation row', async () => {
        renderDialog();
        await userEvent.click(screen.getByRole('button', { name: /Aggiungi milestone/ }));
        await userEvent.click(screen.getByRole('button', { name: 'Aggiungi' }));
        expect(screen.getByText('Seleziona categoria')).toBeInTheDocument();
        const select = screen.getByText('Seleziona categoria').closest('div')!;
        const row = select.parentElement!.parentElement!;
        const rowButtons = within(row).getAllByRole('button');
        const trash = rowButtons[rowButtons.length - 1];
        await userEvent.click(trash);
        expect(screen.queryByText('Seleziona categoria')).not.toBeInTheDocument();
    });
});

describe('GoalFormDialog — submit', () => {
    it('posts to /goal when creating', async () => {
        renderDialog();
        await userEvent.type(screen.getByPlaceholderText('es. FIRE, Pensione, Prima casa'), 'FIRE');
        await userEvent.click(screen.getByRole('button', { name: 'Crea obiettivo' }));
        expect(post).toHaveBeenCalledWith('/goal', expect.any(Object));
        expect(put).not.toHaveBeenCalled();
    });

    it('puts to /goal/:id when editing', async () => {
        const goal = {
            id: 5,
            name: 'FIRE',
            description: '',
            target_value: 500000,
            target_date: '2045-01-01',
            categoryAllocations: [],
            milestones: [],
        } as unknown as Goal;
        renderDialog({ goal });
        await userEvent.click(screen.getByRole('button', { name: 'Salva modifiche' }));
        expect(put).toHaveBeenCalledWith('/goal/5', expect.any(Object));
        expect(post).not.toHaveBeenCalled();
    });
});

describe('GoalFormDialog — target year field', () => {
    it('shows the year slice of an existing goal target date', async () => {
        const goal = {
            id: 5,
            name: 'FIRE',
            description: '',
            target_value: 500000,
            target_date: '2045-01-01',
            categoryAllocations: [],
            milestones: [],
        } as unknown as Goal;
        renderDialog({ goal });
        // The year input binds to target_date.slice(0, 4).
        expect(screen.getByDisplayValue('2045')).toBeInTheDocument();
    });
});
