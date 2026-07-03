import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useState, useCallback } from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Category } from '@/types/models';

const post = vi.fn();
const put = vi.fn();

vi.mock('@inertiajs/react', () => ({
    useForm: (initial: Record<string, unknown>) => {
        const [data, setState] = useState(initial);
        // CategoryDialog lists setData in a useEffect dependency array, so it must
        // be referentially stable or the effect re-runs every render → update loop.
        const setData = useCallback((key: string | Record<string, unknown>, value?: unknown) => {
            if (typeof key === 'object') setState(key);
            else setState((d: Record<string, unknown>) => ({ ...d, [key]: value }));
        }, []);
        return { data, setData, post, put, processing: false, errors: {}, reset: () => setState(initial) };
    },
}));

import { CategoryDialog } from '@/Components/Settings/CategoryDialog';

beforeEach(() => {
    post.mockClear();
    put.mockClear();
});

describe('CategoryDialog — submit path', () => {
    it('posts to /categories when creating', async () => {
        render(<CategoryDialog open onClose={vi.fn()} editCategory={null} />);
        await userEvent.type(screen.getByPlaceholderText('es. Obbligazioni'), 'Obbligazioni');
        await userEvent.click(screen.getByRole('button', { name: 'Crea' }));
        expect(post).toHaveBeenCalledWith('/categories', expect.any(Object));
        expect(put).not.toHaveBeenCalled();
    });

    it('puts to /categories/:id when editing', async () => {
        const editCategory = { id: 8, name: 'ETF', color: '#0ce708', sort_order: 0, macro_category: 'ETF' } as Category;
        render(<CategoryDialog open onClose={vi.fn()} editCategory={editCategory} />);
        await userEvent.click(screen.getByRole('button', { name: 'Salva' }));
        expect(put).toHaveBeenCalledWith('/categories/8', expect.any(Object));
        expect(post).not.toHaveBeenCalled();
    });
});

describe('CategoryDialog — color palette', () => {
    it('marks the swatch matching the current color as active', () => {
        const editCategory = { id: 1, name: 'X', color: '#F7931A', sort_order: 0, macro_category: null } as Category;
        render(<CategoryDialog open onClose={vi.fn()} editCategory={editCategory} />);
        // Match is case-insensitive: the seeded #F7931A swatch is ring-highlighted.
        const swatch = screen.getByRole('button', { name: 'Colore #f7931a' });
        expect(swatch.className).toContain('ring-2');
    });

    it('selecting a swatch updates the color input', async () => {
        render(<CategoryDialog open onClose={vi.fn()} editCategory={null} />);
        await userEvent.click(screen.getByRole('button', { name: 'Colore #ef4444' }));
        // Both the native color picker and the text input reflect the new value.
        expect(screen.getAllByDisplayValue('#ef4444').length).toBeGreaterThan(0);
    });
});

describe('CategoryDialog — edit seeds the form', () => {
    it('prefills the name from the edited category', () => {
        // Name distinct from the macro label so the assertion isn't ambiguous
        // with the Radix Select's rendered value.
        const editCategory = { id: 2, name: 'Le mie cripto', color: '#f7931a', sort_order: 0, macro_category: 'Cripto' } as Category;
        render(<CategoryDialog open onClose={vi.fn()} editCategory={editCategory} />);
        expect(screen.getByDisplayValue('Le mie cripto')).toBeInTheDocument();
    });
});
