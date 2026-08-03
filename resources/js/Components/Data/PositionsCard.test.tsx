import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PositionsCard from '@/Components/Data/PositionsCard';
import type { PositionReturn, PositionReturns } from '@/types/analytics';

// Opening a row mounts TransactionsDialog, which fetches on open.
const fetchMock = vi.fn();

beforeEach(() => {
    fetchMock.mockReset();
    fetchMock.mockResolvedValue({
        json: async () => ({
            transactions: [],
            position: {
                shares: 20, average_cost: 100, cost_basis: 2000, realised_pnl: 0,
                current_value: 2400, unrealised_pnl: 400, unrealised_pnl_pct: 20,
            },
        }),
    });
    vi.stubGlobal('fetch', fetchMock);
});
afterEach(() => {
    vi.unstubAllGlobals();
});

function position(over: Partial<PositionReturn> = {}): PositionReturn {
    return {
        id: 7,
        name: 'ACWI',
        shares: 20,
        average_cost: 100,
        cost_basis: 2000,
        current_value: 2400,
        unrealised_pnl: 400,
        unrealised_pnl_pct: 20,
        realised_pnl: 0,
        ...over,
    };
}

function returns(over: { positions?: PositionReturn[]; aggregate?: Partial<PositionReturns['aggregate']> } = {}): PositionReturns {
    return {
        positions: over.positions ?? [position()],
        aggregate: {
            cost_basis: 2000,
            current_value: 2400,
            unrealised_pnl: 400,
            unrealised_pnl_pct: 20,
            realised_pnl: 0,
            ...over.aggregate,
        },
    };
}

describe('PositionsCard', () => {
    it('renders nothing when there are no transaction-managed positions', () => {
        const { container } = render(<PositionsCard returns={null} />);
        expect(container).toBeEmptyDOMElement();
    });

    it('says the figures span the whole history, not the selected month', () => {
        render(<PositionsCard returns={returns()} />);
        expect(screen.getByText('Posizioni a quote')).toBeInTheDocument();
        expect(document.body.textContent).toContain('non segue il mese selezionato');
    });

    it('shows the aggregate cost basis, value and return with its percentage', () => {
        render(<PositionsCard returns={returns()} />);
        // "Versato" labels both the aggregate stat and the table column.
        expect(screen.getAllByText('Versato').length).toBeGreaterThan(0);
        expect(screen.getByText('Valore attuale')).toBeInTheDocument();
        // Group separators are unreliable under happy-dom's ICU; match digits only.
        expect(document.body.textContent).toMatch(/2400,00|2\.400,00/);
        expect(document.body.textContent).toContain('+20.00%');
    });

    it('hides the realised figure when nothing has been sold', () => {
        render(<PositionsCard returns={returns()} />);
        expect(screen.queryByText('Realizzato')).not.toBeInTheDocument();
    });

    it('shows the realised figure once a sell has happened', () => {
        render(<PositionsCard returns={returns({ aggregate: { realised_pnl: 150 } })} />);
        expect(screen.getByText('Realizzato')).toBeInTheDocument();
    });

    it('lists a position with its shares and average cost', () => {
        render(<PositionsCard returns={returns()} />);
        expect(screen.getAllByText('ACWI').length).toBeGreaterThan(0);
        expect(document.body.textContent).toContain('20');
        expect(screen.getByText('Prezzo medio')).toBeInTheDocument();
    });

    it('dashes the value and return of an unpriced position', () => {
        render(
            <PositionsCard
                returns={returns({
                    positions: [position({ current_value: null, unrealised_pnl: null, unrealised_pnl_pct: null })],
                })}
            />,
        );
        // Two dashes: one for Valore, one for Rendimento (desktop table).
        expect(screen.getAllByText('—').length).toBeGreaterThanOrEqual(2);
    });

    it('opens the transaction history when a position is clicked', async () => {
        render(<PositionsCard returns={returns()} />);

        await userEvent.click(screen.getAllByText('ACWI')[0]);

        await waitFor(() =>
            expect(fetchMock).toHaveBeenCalledWith('/assets/7/transactions', expect.any(Object)),
        );
    });
});
