import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import TransactionsDialog from '@/Components/Data/TransactionsDialog';
import type { PositionSummary, TransactionRow } from '@/types/models';

// The dialog fetches /assets/:id/transactions on open; stub global fetch.
const fetchMock = vi.fn();

function payload(over: { transactions?: TransactionRow[]; position?: Partial<PositionSummary> } = {}) {
    const position: PositionSummary = {
        shares: 10,
        average_cost: 100,
        cost_basis: 1000,
        realised_pnl: 0,
        current_value: 1200,
        unrealised_pnl: 200,
        unrealised_pnl_pct: 20,
        ...over.position,
    };
    return { transactions: over.transactions ?? [], position };
}

function resolveWith(data: unknown) {
    fetchMock.mockResolvedValue({ json: async () => data });
}

beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
});
afterEach(() => {
    vi.unstubAllGlobals();
});

describe('TransactionsDialog — fetch on open', () => {
    it('does not fetch while the asset is null (closed)', () => {
        render(<TransactionsDialog asset={null} onClose={vi.fn()} />);
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('fetches the asset transactions when opened', async () => {
        resolveWith(payload());
        render(<TransactionsDialog asset={{ id: 7, name: 'VWCE' }} onClose={vi.fn()} />);
        await waitFor(() =>
            expect(fetchMock).toHaveBeenCalledWith('/assets/7/transactions', expect.any(Object)),
        );
    });

    it('renders the position summary once loaded', async () => {
        resolveWith(payload({ position: { shares: 42 } }));
        render(<TransactionsDialog asset={{ id: 1, name: 'VWCE' }} onClose={vi.fn()} />);
        await waitFor(() => expect(screen.getByText('42')).toBeInTheDocument());
        expect(screen.getByText('Prezzo medio di carico')).toBeInTheDocument();
    });
});

describe('TransactionsDialog — transaction list', () => {
    it('shows the empty message when there are no transactions', async () => {
        resolveWith(payload({ transactions: [] }));
        render(<TransactionsDialog asset={{ id: 1, name: 'VWCE' }} onClose={vi.fn()} />);
        await waitFor(() =>
            expect(screen.getByText(/Nessuna transazione importata/)).toBeInTheDocument(),
        );
    });

    it('renders a buy row with its PAC badge', async () => {
        const tx: TransactionRow = {
            id: 1,
            type: 'buy',
            source: 'savings_plan',
            shares: 1.5,
            price_per_share: 80,
            fees: null,
            date: '2025-06-01',
            notes: null,
        };
        resolveWith(payload({ transactions: [tx] }));
        render(<TransactionsDialog asset={{ id: 1, name: 'VWCE' }} onClose={vi.fn()} />);
        await waitFor(() => expect(screen.getByText('Acquisto')).toBeInTheDocument());
        expect(screen.getByText('PAC')).toBeInTheDocument();
    });

    it('labels a sell row', async () => {
        const tx: TransactionRow = {
            id: 2,
            type: 'sell',
            source: 'single',
            shares: 2,
            price_per_share: 90,
            fees: null,
            date: '2025-07-01',
            notes: null,
        };
        resolveWith(payload({ transactions: [tx] }));
        render(<TransactionsDialog asset={{ id: 1, name: 'VWCE' }} onClose={vi.fn()} />);
        await waitFor(() => expect(screen.getByText('Vendita')).toBeInTheDocument());
        expect(screen.queryByText('PAC')).not.toBeInTheDocument();
    });
});

describe('TransactionsDialog — pnl tone', () => {
    it('shows a positive unrealised pnl with its percentage', async () => {
        resolveWith(payload({ position: { unrealised_pnl: 200, unrealised_pnl_pct: 20 } }));
        render(<TransactionsDialog asset={{ id: 1, name: 'VWCE' }} onClose={vi.fn()} />);
        await waitFor(() => expect(screen.getByText('Plus/minus latente')).toBeInTheDocument());
        expect(screen.getByText(/\(20\.00%\)/)).toBeInTheDocument();
    });
});
