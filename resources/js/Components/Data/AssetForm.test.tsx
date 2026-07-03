import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useState } from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Asset, AssetPriceInfo, Category } from '@/types/models';

// AssetForm drives its fields through Inertia's useForm and submits via
// post/put. The tests care about the *client* logic — mode switching, the
// live computed value, and the anomaly warning — not the network, so we back
// useForm with a real React state store and spy on post/put/router.

const post = vi.fn();
const put = vi.fn();
const routerPost = vi.fn();

vi.mock('@inertiajs/react', () => {
    return {
        router: { post: (...a: unknown[]) => routerPost(...a) },
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
    };
});

import AssetForm from '@/Components/Data/AssetForm';

const CATEGORIES: Pick<Category, 'id' | 'name' | 'color'>[] = [
    { id: 1, name: 'Liquidità', color: '#0af' },
    { id: 2, name: 'ETF', color: '#fa0' },
];

function renderForm(overrides: Partial<React.ComponentProps<typeof AssetForm>> = {}) {
    const props = {
        open: true,
        onClose: vi.fn(),
        categories: CATEGORIES,
        month: '2025-06-01',
        editAsset: null as Asset | null,
        prices: {} as Record<string, AssetPriceInfo>,
        previousValues: {} as Record<string, number>,
        ...overrides,
    };
    render(<AssetForm {...props} />);
    return props;
}

beforeEach(() => {
    post.mockClear();
    put.mockClear();
    routerPost.mockClear();
});

describe('AssetForm — mode switching', () => {
    it('starts in manual mode with the value field visible', () => {
        renderForm();
        expect(screen.getByText('Valore (€)')).toBeInTheDocument();
        expect(screen.queryByText('Ticker')).not.toBeInTheDocument();
    });

    it('switches to ticker mode and reveals the ticker + quantity fields', async () => {
        renderForm();
        await userEvent.click(screen.getByRole('button', { name: 'Ticker + quantità' }));
        expect(screen.getByText('Ticker')).toBeInTheDocument();
        expect(screen.getByText('Quantità')).toBeInTheDocument();
        expect(screen.queryByText('Valore (€)')).not.toBeInTheDocument();
    });
});

describe('AssetForm — live computed value in ticker mode', () => {
    it('shows qty × price when a known ticker and quantity are entered', async () => {
        renderForm({
            prices: { BTC: { price: 100, fetched_at: null } as AssetPriceInfo },
        });
        await userEvent.click(screen.getByRole('button', { name: 'Ticker + quantità' }));

        const ticker = screen.getByPlaceholderText('es. BTC, SWDA.MI');
        const qty = screen.getByPlaceholderText('es. 0.5');
        await userEvent.type(ticker, 'BTC');
        await userEvent.type(qty, '2');

        // 2 × 100 = 200 → the computed value row appears.
        expect(screen.getByText('Valore calcolato')).toBeInTheDocument();
        expect(screen.getByText(/200/)).toBeInTheDocument();
    });

    it('reports no price for an unknown ticker', async () => {
        renderForm();
        await userEvent.click(screen.getByRole('button', { name: 'Ticker + quantità' }));
        await userEvent.type(screen.getByPlaceholderText('es. BTC, SWDA.MI'), 'NOPE');
        expect(screen.getByText(/Nessun prezzo disponibile/)).toBeInTheDocument();
    });
});

describe('AssetForm — anomaly warning', () => {
    // A manual value that jumps >3× vs last month should warn and require a
    // confirming second submit before it posts. The anomaly key is
    // `${category_id}|${name}`; we seed both via an editAsset so the test
    // doesn't need to drive the Radix category select (which can't receive
    // pointer events under happy-dom). Editing keeps the manual value field
    // enabled and pre-fills category + name.
    const editAsset = {
        id: 7,
        category_id: 1,
        name: 'Conto',
        value: 1000,
        date: '2025-06-01',
    } as Asset;
    const priorProps = {
        editAsset,
        previousValues: { '1|Conto': 1000 },
    };

    async function typeValue(v: string) {
        const field = screen.getByDisplayValue('1000');
        await userEvent.clear(field);
        await userEvent.type(field, v);
    }

    it('warns on a >3× jump and blocks the first submit', async () => {
        renderForm(priorProps);
        await typeValue('5000');
        await userEvent.click(screen.getByRole('button', { name: 'Salva modifiche' }));

        expect(screen.getByText(/Valore insolito/)).toBeInTheDocument();
        expect(put).not.toHaveBeenCalled();
    });

    it('lets the second submit through once confirmed', async () => {
        renderForm(priorProps);
        await typeValue('5000');
        const submit = screen.getByRole('button', { name: 'Salva modifiche' });
        await userEvent.click(submit);
        await userEvent.click(submit);

        expect(put).toHaveBeenCalledWith('/assets/7', expect.any(Object));
    });

    it('does not warn when the value is within range', async () => {
        renderForm(priorProps);
        await typeValue('1500');
        await userEvent.click(screen.getByRole('button', { name: 'Salva modifiche' }));

        expect(screen.queryByText(/Valore insolito/)).not.toBeInTheDocument();
        expect(put).toHaveBeenCalledWith('/assets/7', expect.any(Object));
    });
});

describe('AssetForm — edit mode', () => {
    it('submits with put to the asset id when editing', async () => {
        const editAsset = {
            id: 42,
            category_id: 1,
            name: 'Conto',
            value: 1000,
            date: '2025-06-01',
        } as Asset;
        renderForm({ editAsset });
        await userEvent.click(screen.getByRole('button', { name: 'Salva modifiche' }));
        expect(put).toHaveBeenCalledWith('/assets/42', expect.any(Object));
        expect(post).not.toHaveBeenCalled();
    });
});
