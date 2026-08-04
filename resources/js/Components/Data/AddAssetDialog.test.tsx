import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useState } from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import AddAssetDialog, { type CopyableAsset } from '@/Components/Data/AddAssetDialog';

const post = vi.fn();

// Back useForm with a real state store so selecting a checkbox actually moves
// data.asset_ids, and spy on post to see what the copy submits.
vi.mock('@inertiajs/react', () => ({
    useForm: (initial: Record<string, unknown>) => {
        const [data, setAll] = useState(initial);
        return {
            data,
            setData: (key: string, value: unknown) => setAll((d) => ({ ...d, [key]: value })),
            post,
            processing: false,
        };
    },
}));

function copyable(over: Partial<CopyableAsset> = {}): CopyableAsset {
    return {
        id: 1,
        name: 'Bitcoin Wallet',
        category_id: 4,
        category: 'Bitcoin',
        color: '#f7931a',
        value: 10433.23,
        ...over,
    };
}

const props = {
    open: true,
    onClose: vi.fn(),
    onManual: vi.fn(),
    month: '2026-08-01',
    previousMonth: '2026-07-01',
    copyableAssets: [copyable()],
};

beforeEach(() => {
    vi.clearAllMocks();
});

describe('AddAssetDialog — action list', () => {
    it('offers both actions when the previous month has assets this one lacks', () => {
        render(<AddAssetDialog {...props} />);
        expect(screen.getByText('Inserisci a mano')).toBeInTheDocument();
        expect(screen.getByText('Copia da luglio 2026')).toBeInTheDocument();
    });

    it('disables the copy action on the first tracked month', () => {
        render(<AddAssetDialog {...props} previousMonth={null} copyableAssets={[]} />);

        expect(screen.getByText(/primo mese tracciato/)).toBeInTheDocument();
        expect(screen.getByText('Copia dal mese precedente').closest('button')).toBeDisabled();
    });

    it('disables the copy action when every asset is already carried over', () => {
        render(<AddAssetDialog {...props} copyableAssets={[]} />);

        expect(screen.getByText(/sono già in questo mese/)).toBeInTheDocument();
        expect(screen.getByText('Copia da luglio 2026').closest('button')).toBeDisabled();
    });

    it('says how many assets are copyable', () => {
        render(<AddAssetDialog {...props} />);
        expect(screen.getByText(/1 asset non è ancora in agosto 2026/)).toBeInTheDocument();
    });

    it('hands off to the manual form and closes itself', async () => {
        const onManual = vi.fn();
        const onClose = vi.fn();
        render(<AddAssetDialog {...props} onManual={onManual} onClose={onClose} />);

        await userEvent.click(screen.getByText('Inserisci a mano'));

        expect(onManual).toHaveBeenCalled();
        expect(onClose).toHaveBeenCalled();
    });
});

describe('AddAssetDialog — copy list', () => {
    const open = async () => {
        await userEvent.click(screen.getByText('Copia da luglio 2026'));
    };

    it('goes back to the action list', async () => {
        render(<AddAssetDialog {...props} />);
        await open();
        expect(screen.queryByText('Inserisci a mano')).not.toBeInTheDocument();

        await userEvent.click(screen.getByText('Indietro'));
        expect(screen.getByText('Inserisci a mano')).toBeInTheDocument();
    });

    it('lists only the assets missing from this month', async () => {
        render(<AddAssetDialog {...props} />);
        await open();

        expect(screen.getByText('Bitcoin Wallet')).toBeInTheDocument();
        expect(screen.getAllByRole('checkbox')).toHaveLength(1);
    });

    it('keeps the submit disabled until something is selected', async () => {
        render(<AddAssetDialog {...props} />);
        await open();

        const submit = screen.getByRole('button', { name: /^Copia$/ });
        expect(submit).toBeDisabled();

        await userEvent.click(screen.getByRole('checkbox'));
        expect(screen.getByRole('button', { name: /Copia 1/ })).toBeEnabled();
    });

    it('posts the selected ids and the source month', async () => {
        render(<AddAssetDialog {...props} />);
        await open();
        await userEvent.click(screen.getByRole('checkbox'));
        await userEvent.click(screen.getByRole('button', { name: /Copia 1/ }));

        expect(post).toHaveBeenCalledWith(
            '/assets/copy-from-month?month=2026-08-01',
            expect.anything(),
        );
    });

    it('selects and clears every asset at once', async () => {
        const many = [copyable(), copyable({ id: 2, name: 'Conto' }), copyable({ id: 3, name: 'Oro' })];
        render(<AddAssetDialog {...props} copyableAssets={many} />);
        await open();

        await userEvent.click(screen.getByText('Seleziona tutti'));
        expect(screen.getAllByRole('checkbox').every((c) => (c as HTMLInputElement).checked)).toBe(true);

        await userEvent.click(screen.getByText('Deseleziona tutti'));
        expect(screen.getAllByRole('checkbox').some((c) => (c as HTMLInputElement).checked)).toBe(false);
    });

    it('does not offer "select all" for a single asset', async () => {
        render(<AddAssetDialog {...props} />);
        await open();

        expect(screen.queryByText('Seleziona tutti')).not.toBeInTheDocument();
    });
});
