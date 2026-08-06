import { describe, it, expect, vi } from 'vitest';
import { cleanup, render, screen, within } from '@testing-library/react';
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

// AssetTable renders both layouts and lets CSS pick one (`hidden sm:block` /
// `sm:hidden`). happy-dom applies no CSS, so both are in the DOM and an
// unscoped getByText matches twice. Scope to the branch under test.
const table = () => document.querySelector('table') as HTMLElement;
const cardList = () => document.querySelector('[data-testid="asset-cards"]') as HTMLElement;
const cardSummary = () => document.querySelector('[data-testid="asset-cards-summary"]') as HTMLElement;

const CAT = (id: number, name: string, color = '#123456'): Asset['category'] => ({
    id,
    name,
    color,
    macro_category: null,
});

// Net worth matching the month's total, i.e. nothing carried forward — the
// default for tests that aren't about the reconciliation row.
const RECON = {
    currentNetWorth: 4000,
    reconciliation: {
        total: 4000,
        currentMonthTotal: 4000,
        carriedForwardTotal: 0,
        carriedForward: [],
    },
};

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
        render(<AssetTable assets={[]} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...RECON} />);
        expect(screen.getByText(/Nessun asset per questo mese/)).toBeInTheDocument();
    });
});

describe('AssetTable — flat rows and totals', () => {
    const assets: Asset[] = [
        asset({ id: 1, category_id: 1, category: CAT(1, 'Liquidità'), name: 'Conto', value: 1000 }),
        asset({ id: 2, category_id: 1, category: CAT(1, 'Liquidità'), name: 'Libretto', value: 500 }),
        asset({ id: 3, category_id: 2, category: CAT(2, 'ETF'), name: 'VWCE', value: 2500 }),
    ];

    it('names the category on every row, with no group header', () => {
        render(<AssetTable assets={assets} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...RECON} />);
        // One "Liquidità" per asset in that category, not one shared header.
        expect(within(table()).getAllByText('Liquidità')).toHaveLength(2);
        expect(within(table()).getAllByText('ETF')).toHaveLength(1);
        // The member count belonged to the collapsed group header.
        expect(screen.queryByText('(2)')).not.toBeInTheDocument();
    });

    it('keeps assets of the same category consecutive', () => {
        render(<AssetTable assets={assets} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...RECON} />);
        const names = Array.from(document.querySelectorAll('tbody tr'))
            .map((tr) => tr.querySelector('td')!.textContent);
        expect(names).toEqual(['Conto', 'Libretto', 'VWCE']);
    });

    it('lists every asset', () => {
        render(<AssetTable assets={assets} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...RECON} />);
        expect(within(table()).getByText('Conto')).toBeInTheDocument();
        expect(within(table()).getByText('Libretto')).toBeInTheDocument();
        expect(within(table()).getByText('VWCE')).toBeInTheDocument();
    });

    it('fires onEdit with the asset when its pencil is clicked', async () => {
        const { default: userEvent } = await import('@testing-library/user-event');
        const onEdit = vi.fn();
        render(<AssetTable assets={[asset({ name: 'Conto' })]} onEdit={onEdit} prices={{}} previousValues={{}} previousMonth={null} {...RECON} />);
        const row = within(table()).getByText('Conto').closest('tr')!;
        // Deleting moved into the edit dialog, so the pencil is the row's only
        // action for a plain asset.
        const buttons = within(row).getAllByRole('button');
        expect(buttons).toHaveLength(1);
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
    const rowText = (name: string) => normalise(within(table()).getByText(name).closest('tr')!.textContent!);
    const footerText = () => normalise(document.querySelector('tfoot')!.textContent!);

    it('names the compared month in the column header', () => {
        // previousValues come from the latest TRACKED month, which may be older
        // than the previous calendar month — the header must say which.
        const a = asset({ category_id: 1, name: 'Conto', value: 1100 });
        render(<AssetTable assets={[a]} onEdit={vi.fn()} prices={{}} previousValues={{ '1|Conto': 1000 }} previousMonth="2025-04-01" {...RECON} />);

        expect(within(table()).getByText(/vs aprile 2025/i)).toBeInTheDocument();
    });

    it('falls back to a neutral header when there is nothing to compare', () => {
        render(<AssetTable assets={[asset()]} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...RECON} />);

        expect(within(table()).getByText('Variazione')).toBeInTheDocument();
    });

    it('shows a gain against the previous month, with the percentage', () => {
        const a = asset({ category_id: 1, name: 'Conto', value: 1100 });
        render(<AssetTable assets={[a]} onEdit={vi.fn()} prices={{}} previousValues={{ '1|Conto': 1000 }} previousMonth={null} {...RECON} />);

        // +100 € on 1000 € => +10.0%
        expect(rowText('Conto')).toContain('+100€');
        expect(rowText('Conto')).toContain('+10.0%');
    });

    it('shows a loss in the negative direction', () => {
        const a = asset({ category_id: 1, name: 'Conto', value: 800 });
        render(<AssetTable assets={[a]} onEdit={vi.fn()} prices={{}} previousValues={{ '1|Conto': 1000 }} previousMonth={null} {...RECON} />);

        // -200 € on 1000 € => -20.0%
        expect(rowText('Conto')).toContain('-200€');
        expect(rowText('Conto')).toContain('-20.0%');
    });

    it('reads "invariato" when the value did not move', () => {
        const a = asset({ category_id: 1, name: 'Conto', value: 1000 });
        render(<AssetTable assets={[a]} onEdit={vi.fn()} prices={{}} previousValues={{ '1|Conto': 1000 }} previousMonth={null} {...RECON} />);

        // The footer sums to the same non-move, so both the row and the total
        // read "invariato".
        expect(rowText('Conto')).toContain('invariato');
    });

    it('reads "invariato" for a sub-cent float difference', () => {
        // A quantity-held asset re-priced identically lands a hair off zero; that
        // must not render as "+0,00 €".
        const a = asset({ category_id: 1, name: 'Conto', value: 1000.000000001 });
        render(<AssetTable assets={[a]} onEdit={vi.fn()} prices={{}} previousValues={{ '1|Conto': 1000 }} previousMonth={null} {...RECON} />);

        expect(rowText('Conto')).toContain('invariato');
    });

    it('shows a dash for an asset with no previous month', () => {
        // An asset added this month has nothing to compare against — a dash, not
        // a misleading +100%.
        const a = asset({ category_id: 1, name: 'Nuovo', value: 500 });
        render(<AssetTable assets={[a]} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...RECON} />);

        const row = within(table()).getByText('Nuovo').closest('tr')!;
        expect(within(row).getByTitle('Nessun valore nel mese precedente')).toBeInTheDocument();
    });

    it('omits the percentage when the previous value was zero', () => {
        const a = asset({ category_id: 1, name: 'Conto', value: 500 });
        render(<AssetTable assets={[a]} onEdit={vi.fn()} prices={{}} previousValues={{ '1|Conto': 0 }} previousMonth={null} {...RECON} />);

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
                previousValues={{ '1|Conto': 1000, '2|Conto': 1000 }} previousMonth={null} {...RECON}
            />,
        );

        const rows = within(table()).getAllByText('Conto').map((e) => normalise(e.closest('tr')!.textContent!));
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
        render(<AssetTable assets={assets} onEdit={vi.fn()} prices={{}} previousValues={{ '1|Conto': 1000 }} previousMonth={null} {...RECON} />);

        expect(footerText()).toContain('+100€');
        expect(footerText()).not.toContain('+600€');
    });
});

describe('AssetTable — footer totals', () => {
    const normalise = (text: string) =>
        text
            .replace(/(\d)\.(?=\d{3}\b)/g, '$1')
            .replace(/,\d+/g, '')
            .replace(/\p{Zs}/gu, '');
    // The totals row is always the first of the footer; net worth may add a second.
    const footer = () => document.querySelectorAll('tfoot tr')[0];
    const cells = () => Array.from(footer().querySelectorAll('td')).map((c) => normalise(c.textContent!));

    const assets: Asset[] = [
        asset({ id: 1, category_id: 1, category: CAT(1, 'Liquidità'), name: 'Conto', value: 1000 }),
        asset({ id: 2, category_id: 1, category: CAT(1, 'Liquidità'), name: 'Libretto', value: 500 }),
        asset({ id: 3, category_id: 2, category: CAT(2, 'ETF'), name: 'VWCE', value: 2500 }),
    ];

    it('counts the assets and the categories they span', () => {
        render(<AssetTable assets={assets} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...RECON} />);

        expect(cells()[0]).toContain('3asset');
        expect(cells()[1]).toContain('2categorie');
    });

    it('puts the value total under the value column', () => {
        render(<AssetTable assets={assets} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...RECON} />);

        expect(cells()[2]).toContain('4000');
    });

    it('shows the total change with its percentage under the change column', () => {
        render(
            <AssetTable
                assets={assets}
                onEdit={vi.fn()}
                prices={{}}
                previousValues={{ '1|Conto': 900, '1|Libretto': 500, '2|VWCE': 2100 }}
                previousMonth="2025-05-01" {...RECON}
            />,
        );

        // +100 on Conto, +400 on VWCE over a comparable base of 3500 => +14.3%.
        expect(cells()[3]).toContain('+500');
        expect(cells()[3]).toContain('+14.3%');
    });

    it('bases the percentage on the comparable assets, not on the month total', () => {
        // 'Nuovo' inflates the value total but has no previous value: counting it
        // in the base would report +9.1% instead of +10.0%.
        const withNew = [
            asset({ id: 1, category_id: 1, name: 'Conto', value: 1100 }),
            asset({ id: 2, category_id: 1, name: 'Nuovo', value: 100 }),
        ];
        render(<AssetTable assets={withNew} onEdit={vi.fn()} prices={{}} previousValues={{ '1|Conto': 1000 }} previousMonth="2025-05-01" {...RECON} />);

        expect(cells()[3]).toContain('+10.0%');
    });

    it('shows a dash when nothing is comparable', () => {
        render(<AssetTable assets={assets} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...RECON} />);

        expect(within(footer() as HTMLElement).getByTitle('Nessun valore nel mese precedente')).toBeInTheDocument();
    });
});

describe('AssetTable — read-only month', () => {
    it('drops the edit action from every row', () => {
        render(<AssetTable assets={[asset({ name: 'Conto' })]} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...RECON} readOnly />);

        const row = within(table()).getByText('Conto').closest('tr')!;
        expect(within(row).queryAllByRole('button')).toHaveLength(0);
    });

    it('keeps the transactions action, which only reads', () => {
        const a = asset({ name: 'VWCE', transaction_managed: true } as Partial<Asset>);
        render(<AssetTable assets={[a]} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...RECON} readOnly />);

        const row = within(table()).getByText('VWCE').closest('tr')!;
        expect(within(row).getByTitle('Vedi transazioni')).toBeInTheDocument();
    });

    it('does not tell an empty past month to use the add button', () => {
        render(<AssetTable assets={[]} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...RECON} readOnly pastMonth />);

        expect(screen.getByText('Nessun asset registrato in questo mese.')).toBeInTheDocument();
    });

    it('still points at the add button when the lock is only temporary', () => {
        // readOnly without pastMonth is a price refresh in flight: the month is
        // still editable once it finishes, so the guidance stands.
        render(<AssetTable assets={[]} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...RECON} readOnly />);

        expect(screen.getByText(/Aggiungine uno con il pulsante sopra/)).toBeInTheDocument();
    });
});

describe('AssetTable — net worth row', () => {
    const normalise = (text: string) =>
        text.replace(/(\d)\.(?=\d{3}\b)/g, '$1').replace(/,\d+/g, '').replace(/\p{Zs}/gu, '');
    const rows = () => document.querySelectorAll('tfoot tr');

    const carried = {
        currentNetWorth: 3500,
        reconciliation: {
            total: 3500,
            currentMonthTotal: 1000,
            carriedForwardTotal: 2500,
            carriedForward: [
                { categoryId: 4, category: 'Bitcoin', color: '#f7931a', value: 2500, asOf: '2025-05-01' },
            ],
        },
    };

    it('adds a net worth row when a category is carried forward', () => {
        render(<AssetTable assets={[asset()]} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...carried} />);

        expect(rows()).toHaveLength(2);
        const netWorth = normalise(rows()[1].textContent!);
        expect(netWorth).toContain('Patrimonio');
        expect(netWorth).toContain('3500');
    });

    it('explains why net worth exceeds the month total', () => {
        render(<AssetTable assets={[asset()]} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...carried} />);

        expect(rows()[1].textContent).toContain('Bitcoin');
        expect(rows()[1].textContent).toContain('maggio 2025');
    });

    it('omits the row when net worth matches the month total', () => {
        // Otherwise the same figure would appear twice with nothing to explain
        // the repetition.
        render(<AssetTable assets={[asset()]} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...RECON} />);

        expect(rows()).toHaveLength(1);
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
                previousValues={{}} previousMonth={null} {...RECON}
            />,
        );
        // The ticker symbol renders as its own pill.
        expect(within(table()).getAllByText('BTC').length).toBeGreaterThan(0);
    });

    it('shows a bank badge for a bank-linked asset', () => {
        const a = asset({ bank_linked: true, synced_at: iso(60_000) });
        render(<AssetTable assets={[a]} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...RECON} />);
        expect(within(table()).getByText('Banca')).toBeInTheDocument();
    });

    it('flags a stalled broker sync as not updated', () => {
        // A broker sync older than two days is stale per brokerFreshness.
        const a = asset({ sync_source: 'broker', synced_at: iso(3 * 24 * 60 * 60_000) });
        render(<AssetTable assets={[a]} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...RECON} />);
        expect(within(table()).getByText(/Scalable · non aggiornato/)).toBeInTheDocument();
    });

    it('shows a plain broker badge when the sync is fresh', () => {
        const a = asset({ sync_source: 'broker', synced_at: iso(60_000) });
        render(<AssetTable assets={[a]} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...RECON} />);
        expect(within(table()).getByText('Scalable')).toBeInTheDocument();
        expect(screen.queryByText(/non aggiornato/)).not.toBeInTheDocument();
    });
});

describe('AssetTable — mobile card list', () => {
    const normalise = (text: string) =>
        text.replace(/(\d)\.(?=\d{3}\b)/g, '$1').replace(/,\d+/g, '').replace(/\p{Zs}/gu, '');

    const assets: Asset[] = [
        asset({ id: 1, category_id: 1, category: CAT(1, 'Liquidità'), name: 'Conto', value: 1000 }),
        asset({ id: 2, category_id: 1, category: CAT(1, 'Liquidità'), name: 'Libretto', value: 500 }),
        asset({ id: 3, category_id: 2, category: CAT(2, 'ETF'), name: 'VWCE', value: 2500 }),
    ];

    it('renders every asset exactly once as a card', () => {
        render(<AssetTable assets={assets} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...RECON} />);

        for (const name of ['Conto', 'Libretto', 'VWCE']) {
            expect(within(cardList()).getByText(name)).toBeInTheDocument();
        }
    });

    it('carries the sync badges over to the card branch', () => {
        // AssetIdentity is shared with the table; this guards the sharing.
        const a = asset({ bank_linked: true, synced_at: new Date().toISOString() });
        render(<AssetTable assets={[a]} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...RECON} />);

        expect(within(cardList()).getByText('Banca')).toBeInTheDocument();
    });

    it('states the compared month once, not per card', () => {
        render(<AssetTable assets={assets} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth="2025-04-01" {...RECON} />);

        expect(within(cardList()).getAllByText(/vs aprile 2025/i)).toHaveLength(1);
    });

    it('sums the assets and the categories they span in the pinned summary', () => {
        render(<AssetTable assets={assets} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...RECON} />);

        const summary = normalise(cardSummary().textContent!);
        expect(summary).toContain('3asset');
        expect(summary).toContain('2categorie');
        expect(summary).toContain('4000');
    });

    it('adds the net worth line to the summary only when a category is carried forward', () => {
        const carried = {
            currentNetWorth: 3500,
            reconciliation: {
                total: 3500,
                currentMonthTotal: 1000,
                carriedForwardTotal: 2500,
                carriedForward: [
                    { categoryId: 4, category: 'Bitcoin', color: '#f7931a', value: 2500, asOf: '2025-05-01' },
                ],
            },
        };
        render(<AssetTable assets={[asset()]} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...carried} />);
        expect(within(cardSummary()).getByText('Patrimonio')).toBeInTheDocument();
        expect(cardSummary().textContent).toContain('Bitcoin');

        cleanup();
        render(<AssetTable assets={[asset()]} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...RECON} />);
        expect(within(cardSummary()).queryByText('Patrimonio')).not.toBeInTheDocument();
    });

    it('drops the edit action from the cards on a read-only month', () => {
        render(<AssetTable assets={[asset({ name: 'Conto' })]} onEdit={vi.fn()} prices={{}} previousValues={{}} previousMonth={null} {...RECON} readOnly />);

        expect(within(cardList()).queryAllByRole('button')).toHaveLength(0);
    });

    it('fires onEdit from a card pencil', async () => {
        const { default: userEvent } = await import('@testing-library/user-event');
        const onEdit = vi.fn();
        render(<AssetTable assets={[asset({ name: 'Conto' })]} onEdit={onEdit} prices={{}} previousValues={{}} previousMonth={null} {...RECON} />);

        const buttons = within(cardList()).getAllByRole('button');
        expect(buttons).toHaveLength(1);
        await userEvent.click(buttons[0]);
        expect(onEdit).toHaveBeenCalledWith(expect.objectContaining({ name: 'Conto' }));
    });
});
