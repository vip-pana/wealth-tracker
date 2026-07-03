import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useState } from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { PensionCategory, PensionEntry } from '@/Components/Pension/types';

const post = vi.fn();
const put = vi.fn();

vi.mock('@inertiajs/react', () => ({
    useForm: (initial: Record<string, unknown>) => {
        const [data, setState] = useState(initial);
        const setData = (key: string | Record<string, unknown>, value?: unknown) => {
            if (typeof key === 'object') setState(key);
            else setState((d: Record<string, unknown>) => ({ ...d, [key]: value }));
        };
        return { data, setData, post, put, processing: false, errors: {}, reset: () => setState(initial) };
    },
}));

import { PensionFormDialog } from '@/Components/Pension/PensionFormDialog';

const CATEGORIES: PensionCategory[] = [
    { id: 1, name: 'Fondo A', color: '#111', macro_category: 'Fondo Pensione' },
    { id: 2, name: 'Fondo B', color: '#222', macro_category: 'Fondo Pensione' },
];
const YEARS = [2026, 2025, 2024];

function renderDialog(entry: PensionEntry | null = null, onClose = vi.fn()) {
    render(
        <PensionFormDialog
            open
            onClose={onClose}
            categories={CATEGORIES}
            availableYears={YEARS}
            entry={entry}
        />,
    );
    return { onClose };
}

const entry: PensionEntry = {
    id: 9,
    name: 'Report 2025',
    value: 15000,
    year: 2025,
    date: '2025-01-01',
    notes: null,
    category_id: 2,
    category: { id: 2, name: 'Fondo B', color: '#222' },
};

beforeEach(() => {
    post.mockClear();
    put.mockClear();
});

describe('PensionFormDialog — defaults', () => {
    it('seeds the year with the first available year when creating', () => {
        renderDialog(null);
        // availableYears[0] is 2026 → the native <select> shows it selected.
        expect((screen.getByDisplayValue('2026') as HTMLSelectElement).value).toBe('2026');
    });
});

describe('PensionFormDialog — submit path', () => {
    it('posts to /pension when creating', async () => {
        renderDialog(null);
        await userEvent.type(screen.getByPlaceholderText('es. Report 2026'), 'Report 2026');
        await userEvent.type(screen.getByPlaceholderText('15000'), '20000');
        await userEvent.click(screen.getByRole('button', { name: 'Aggiungi' }));
        expect(post).toHaveBeenCalledWith('/pension', expect.any(Object));
        expect(put).not.toHaveBeenCalled();
    });

    it('puts to /pension/:id when editing', async () => {
        renderDialog(entry);
        await userEvent.click(screen.getByRole('button', { name: 'Salva' }));
        expect(put).toHaveBeenCalledWith('/pension/9', expect.any(Object));
        expect(post).not.toHaveBeenCalled();
    });
});

describe('PensionFormDialog — edit seeds the form', () => {
    it('prefills the fields from the edited entry', () => {
        renderDialog(entry);
        expect(screen.getByDisplayValue('Report 2025')).toBeInTheDocument();
        expect(screen.getByDisplayValue('15000')).toBeInTheDocument();
        // The fund <select> reflects the entry's category_id.
        expect((screen.getByDisplayValue('Fondo B') as HTMLSelectElement).value).toBe('2');
    });
});
