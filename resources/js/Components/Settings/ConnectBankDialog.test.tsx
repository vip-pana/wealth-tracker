import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { BankOption } from '@/Components/Settings/types';

// The dialog posts the chosen bank via router.post; mock it and assert on the
// payload. The bank list is plain <button>s (not Radix), so it drives cleanly.
const routerPost = vi.fn();
vi.mock('@inertiajs/react', () => ({ router: { post: (...a: unknown[]) => routerPost(...a) } }));

import { ConnectBankDialog } from '@/Components/Settings/ConnectBankDialog';

const bank = (name: string, country = 'IT'): BankOption => ({ name, country });

function renderDialog(banks: BankOption[], redirectReady = true) {
    render(<ConnectBankDialog open onClose={vi.fn()} banks={banks} redirectReady={redirectReady} />);
}

beforeEach(() => routerPost.mockReset());

describe('ConnectBankDialog — bank filtering', () => {
    it('shows the first 30 banks when the query is empty', () => {
        const banks = Array.from({ length: 40 }, (_, i) => bank(`Banca ${i}`));
        renderDialog(banks);
        // 30 shown, the 31st hidden.
        expect(screen.getByText('Banca 29')).toBeInTheDocument();
        expect(screen.queryByText('Banca 30')).not.toBeInTheDocument();
    });

    it('filters case-insensitively by substring', async () => {
        renderDialog([bank('Intesa Sanpaolo'), bank('UniCredit'), bank('ING Direct')]);
        await userEvent.type(screen.getByPlaceholderText('Cerca la tua banca…'), 'unicredit');
        expect(screen.getByText('UniCredit')).toBeInTheDocument();
        expect(screen.queryByText('Intesa Sanpaolo')).not.toBeInTheDocument();
        expect(screen.queryByText('ING Direct')).not.toBeInTheDocument();
    });

    it('caps filtered results at 30', () => {
        const banks = Array.from({ length: 50 }, (_, i) => bank(`Banca Roma ${i}`));
        renderDialog(banks);
        // All match "Banca Roma" but only 30 render.
        expect(screen.getAllByRole('button').filter((b) => b.textContent?.startsWith('Banca Roma'))).toHaveLength(30);
    });

    it('shows an empty message when nothing matches', async () => {
        renderDialog([bank('Intesa Sanpaolo')]);
        await userEvent.type(screen.getByPlaceholderText('Cerca la tua banca…'), 'zzz');
        expect(screen.getByText('Nessuna banca trovata.')).toBeInTheDocument();
    });
});

describe('ConnectBankDialog — connect', () => {
    it('posts the chosen bank name and country', async () => {
        renderDialog([bank('ING Direct', 'IT')]);
        await userEvent.click(screen.getByRole('button', { name: /ING Direct/ }));
        expect(routerPost).toHaveBeenCalledWith('/banking/connect', { aspsp_name: 'ING Direct', aspsp_country: 'IT' });
    });

    it('locks the list after a click (disables the bank buttons)', async () => {
        renderDialog([bank('ING Direct'), bank('UniCredit')]);
        await userEvent.click(screen.getByRole('button', { name: /ING Direct/ }));
        expect(screen.getByRole('button', { name: /UniCredit/ })).toBeDisabled();
        // Feedback line names the bank being connected.
        expect(screen.getByText(/Reindirizzamento a ING Direct/)).toBeInTheDocument();
    });
});

describe('ConnectBankDialog — tunnel warning', () => {
    it('warns when the redirect tunnel is not ready', () => {
        renderDialog([bank('ING Direct')], false);
        expect(screen.getByText(/Serve un tunnel HTTPS attivo/)).toBeInTheDocument();
    });

    it('omits the warning when the tunnel is ready', () => {
        renderDialog([bank('ING Direct')], true);
        expect(screen.queryByText(/Serve un tunnel HTTPS attivo/)).not.toBeInTheDocument();
    });
});
