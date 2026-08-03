import { describe, it, expect, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import AssetTable from '@/Components/Data/AssetTable';
import type { Asset, AssetPriceInfo } from '@/types/models';

// AssetTable only touches Inertia through DeleteButton's useForm; the delete
// flow itself isn't under test here, so a no-op useForm is enough. The nested
// TransactionsDialog fetches on open — stub it so mounting the table stays inert.
vi.mock('@inertiajs/react', () => ({
    useForm: () => ({ delete: vi.fn(), processing: false }),
}));
vi.mock('@/Components/Data/TransactionsDialog', () => ({
    default: () => null,
}));

const CAT = (id: number, name: string, color = '#123456'): Asset['category'] => ({
    id,
    name,
    color,
    macro_category: null,
});

function asset(over: Partial<Asset> = {}): Asset {
    return {
        id: 1,
        category_id: 1,
        category: CAT(1, 'Liquidità'),
        name: 'Conto',
        ticker: null,
        isin: null,
        expense_ratio: null,
        wallet_address: null,
        quantity: null,
        price: null,
        value: 1000,
        synced_at: null,
        sync_source: null,
        bank_linked: false,
        date: '2025-06-01',
        notes: null,
        ...over,
    };
}

describe('AssetTable — empty state', () => {
    it('shows the empty message when there are no assets', () => {
        render(<AssetTable assets={[]} onEdit={vi.fn()} prices={{}} previousValues={{}} />);
        expect(screen.getByText(/Nessun asset per questo mese/)).toBeInTheDocument();
    });
});

describe('AssetTable — grouping and totals', () => {
    const assets: Asset[] = [
        asset({ id: 1, category_id: 1, category: CAT(1, 'Liquidità'), name: 'Conto', value: 1000 }),
        asset({ id: 2, category_id: 1, category: CAT(1, 'Liquidità'), name: 'Libretto', value: 500 }),
        asset({ id: 3, category_id: 2, category: CAT(2, 'ETF'), name: 'VWCE', value: 2500 }),
    ];

    it('renders one group header per category, in first-seen order', () => {
        render(<AssetTable assets={assets} onEdit={vi.fn()} prices={{}} previousValues={{}} />);
        // Group header cells carry the category name + a member count.
        expect(screen.getByText('Liquidità')).toBeInTheDocument();
        expect(screen.getByText('ETF')).toBeInTheDocument();
        // Two assets under Liquidità, one under ETF.
        expect(screen.getByText('(2)')).toBeInTheDocument();
        expect(screen.getByText('(1)')).toBeInTheDocument();
    });

    it('shows a per-category subtotal and a month grand total', () => {
        render(<AssetTable assets={assets} onEdit={vi.fn()} prices={{}} previousValues={{}} />);
        const has = (t: string) =>
            Array.from(document.querySelectorAll('*')).some((e) => e.textContent?.replace(/[  ]/g, ' ').includes(t));
        // Liquidità subtotal 1500, ETF 2500, month total 4000.
        expect(has('1.500') || has('1500')).toBe(true);
        expect(has('4.000') || has('4000')).toBe(true);
    });

    it('lists each asset row under its group', () => {
        render(<AssetTable assets={assets} onEdit={vi.fn()} prices={{}} previousValues={{}} />);
        expect(screen.getByText('Conto')).toBeInTheDocument();
        expect(screen.getByText('Libretto')).toBeInTheDocument();
        expect(screen.getByText('VWCE')).toBeInTheDocument();
    });

    it('fires onEdit with the asset when its pencil is clicked', async () => {
        const { default: userEvent } = await import('@testing-library/user-event');
        const onEdit = vi.fn();
        render(<AssetTable assets={[asset({ name: 'Conto' })]} onEdit={onEdit} prices={{}} previousValues={{}} />);
        const row = screen.getByText('Conto').closest('tr')!;
        // The row has an edit (pencil) and a delete (trash) button; the pencil is
        // the first of the two.
        const buttons = within(row).getAllByRole('button');
        await userEvent.click(buttons[0]);
        expect(onEdit).toHaveBeenCalledWith(expect.objectContaining({ name: 'Conto' }));
    });
});

describe('AssetTable — month-over-month change', () => {
    // Money and percentages format differently here: Intl gives "-200,00 €"
    // (comma decimals, NBSP before €) while the percentage comes from toFixed(1),
    // so "-20.0%" with a DOT. Normalise only the money figures — strip their
    // grouping dots, spaces and decimals — and leave the percentage untouched:
    // "800,00 €-200,00 € (-20.0%)" => "800€-200€(-20.0%)".
    const normalise = (text: string) =>
        text
            .replace(/(\d)\.(?=\d{3}\b)/g, '$1')
            .replace(/,\d+/g, '')
            .replace(/\p{Zs}/gu, '');
    const rowText = (name: string) => normalise(screen.getByText(name).closest('tr')!.textContent!);

    it('shows a gain against the previous month, with the percentage', () => {
        const a = asset({ category_id: 1, name: 'Conto', value: 1100 });
        render(<AssetTable assets={[a]} onEdit={vi.fn()} prices={{}} previousValues={{ '1|Conto': 1000 }} />);

        // +100 € on 1000 € => +10.0%
        expect(rowText('Conto')).toContain('+100€');
        expect(rowText('Conto')).toContain('+10.0%');
    });

    it('shows a loss in the negative direction', () => {
        const a = asset({ category_id: 1, name: 'Conto', value: 800 });
        render(<AssetTable assets={[a]} onEdit={vi.fn()} prices={{}} previousValues={{ '1|Conto': 1000 }} />);

        // -200 € on 1000 € => -20.0%
        expect(rowText('Conto')).toContain('-200€');
        expect(rowText('Conto')).toContain('-20.0%');
    });

    it('reads "invariato" when the value did not move', () => {
        const a = asset({ category_id: 1, name: 'Conto', value: 1000 });
        render(<AssetTable assets={[a]} onEdit={vi.fn()} prices={{}} previousValues={{ '1|Conto': 1000 }} />);

        expect(screen.getByText('invariato')).toBeInTheDocument();
    });

    it('reads "invariato" for a sub-cent float difference', () => {
        // A quantity-held asset re-priced identically lands a hair off zero; that
        // must not render as "+0,00 €".
        const a = asset({ category_id: 1, name: 'Conto', value: 1000.000000001 });
        render(<AssetTable assets={[a]} onEdit={vi.fn()} prices={{}} previousValues={{ '1|Conto': 1000 }} />);

        expect(screen.getByText('invariato')).toBeInTheDocument();
    });

    it('shows a dash for an asset with no previous month', () => {
        // An asset added this month has nothing to compare against — a dash, not
        // a misleading +100%.
        const a = asset({ category_id: 1, name: 'Nuovo', value: 500 });
        render(<AssetTable assets={[a]} onEdit={vi.fn()} prices={{}} previousValues={{}} />);

        expect(screen.getByTitle('Nessun valore nel mese precedente')).toBeInTheDocument();
    });

    it('omits the percentage when the previous value was zero', () => {
        const a = asset({ category_id: 1, name: 'Conto', value: 500 });
        render(<AssetTable assets={[a]} onEdit={vi.fn()} prices={{}} previousValues={{ '1|Conto': 0 }} />);

        expect(rowText('Conto')).toContain('+500€');
        expect(rowText('Conto')).not.toContain('%');
    });

    it('matches the previous value per category, not by name alone', () => {
        // Two assets share a name across categories; each must read its own row.
        const assets = [
            asset({ id: 1, category_id: 1, category: CAT(1, 'Liquidità'), name: 'Conto', value: 1100 }),
            asset({ id: 2, category_id: 2, category: CAT(2, 'Estero'), name: 'Conto', value: 900 }),
        ];
        render(
            <AssetTable
                assets={assets}
                onEdit={vi.fn()}
                prices={{}}
                previousValues={{ '1|Conto': 1000, '2|Conto': 1000 }}
            />,
        );

        const rows = screen.getAllByText('Conto').map((e) => normalise(e.closest('tr')!.textContent!));
        expect(rows.some((t) => t.includes('+100€'))).toBe(true);
        expect(rows.some((t) => t.includes('-100€'))).toBe(true);
    });

    it('excludes assets without a previous value from the total change', () => {
        // 'Conto' grew by 100; 'Nuovo' is new this month and must not count as
        // +500 of growth, so the month delta is +100, not +600.
        const assets = [
            asset({ id: 1, category_id: 1, name: 'Conto', value: 1100 }),
            asset({ id: 2, category_id: 1, name: 'Nuovo', value: 500 }),
        ];
        render(<AssetTable assets={assets} onEdit={vi.fn()} prices={{}} previousValues={{ '1|Conto': 1000 }} />);

        const footer = normalise(screen.getByText('Totale mese').closest('div')!.textContent!);
        expect(footer).toContain('+100€');
        expect(footer).not.toContain('+600€');
    });
});

describe('AssetTable — freshness badges', () => {
    const now = new Date();
    const iso = (msAgo: number) => new Date(now.getTime() - msAgo).toISOString();

    it('shows the ticker badge for a ticker asset', () => {
        const a = asset({ ticker: 'BTC', quantity: 0.5, price: 100, value: 50 });
        render(
            <AssetTable
                assets={[a]}
                onEdit={vi.fn()}
                prices={{ BTC: { price: 100, fetched_at: iso(60_000) } as AssetPriceInfo }}
                previousValues={{}}
            />,
        );
        // The ticker symbol renders as its own pill.
        expect(screen.getAllByText('BTC').length).toBeGreaterThan(0);
    });

    it('shows a bank badge for a bank-linked asset', () => {
        const a = asset({ bank_linked: true, synced_at: iso(60_000) });
        render(<AssetTable assets={[a]} onEdit={vi.fn()} prices={{}} previousValues={{}} />);
        expect(screen.getByText('Banca')).toBeInTheDocument();
    });

    it('flags a stalled broker sync as not updated', () => {
        // A broker sync older than two days is stale per brokerFreshness.
        const a = asset({ sync_source: 'broker', synced_at: iso(3 * 24 * 60 * 60_000) });
        render(<AssetTable assets={[a]} onEdit={vi.fn()} prices={{}} previousValues={{}} />);
        expect(screen.getByText(/Scalable · non aggiornato/)).toBeInTheDocument();
    });

    it('shows a plain broker badge when the sync is fresh', () => {
        const a = asset({ sync_source: 'broker', synced_at: iso(60_000) });
        render(<AssetTable assets={[a]} onEdit={vi.fn()} prices={{}} previousValues={{}} />);
        expect(screen.getByText('Scalable')).toBeInTheDocument();
        expect(screen.queryByText(/non aggiornato/)).not.toBeInTheDocument();
    });
});
