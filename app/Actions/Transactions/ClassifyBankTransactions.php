<?php

declare(strict_types=1);

namespace App\Actions\Transactions;

use App\Actions\Action;
use App\Models\BankTransaction;

class ClassifyBankTransactions extends Action
{
    /**
     * Note substrings that mark an internal Isybank<->Revolut transfer. Matched
     * against remittance_information, case-insensitively. Detected by signature
     * text, not by amount: amount-matching wrongly caught real P2P payments from
     * friends (Revolut "Payment from <name>" top-ups).
     *
     * @var list<string>
     */
    private const array TRANSFER_MARKERS = [
        'Revolut**',
        'Apple Pay Top-Up by *8222',
    ];

    /**
     * Auto-classify every row the user has not touched (is_manual = false),
     * setting flow_type from the note. Manual rows are left intact so the daily
     * re-import never overwrites a human decision. Never sets `excluded` — that
     * out-of-the-ordinary flag is a human-only choice.
     *
     * @return array{classified: int}
     */
    public function run(): array
    {
        $classified = 0;

        foreach (BankTransaction::query()->where('is_manual', false)->cursor() as $transaction) {
            $transaction->flow_type = $this->classify($transaction);
            $transaction->save();
            $classified++;
        }

        return ['classified' => $classified];
    }

    private function classify(BankTransaction $transaction): string
    {
        $note = $this->note($transaction);

        if ($this->isTransfer($note)) {
            return BankTransaction::FLOW_TRANSFER;
        }

        if ($transaction->amount < 0) {
            return BankTransaction::FLOW_EXPENSE;
        }

        return BankTransaction::FLOW_INCOME;
    }

    private function isTransfer(string $note): bool
    {
        foreach (self::TRANSFER_MARKERS as $marker) {
            if (stripos($note, $marker) !== false) {
                return true;
            }
        }

        $selfName = config('transactions.self_transfer_name');

        if (! is_string($selfName) || $selfName === '') {
            return false;
        }

        // Revolut top-up from one's own account: "Payment from <name>".
        if (stripos($note, 'Payment from '.$selfName) !== false) {
            return true;
        }

        // Isybank standing order to self: "ORDINE PERMANENTE DI BONIFICO ... <name>".
        return stripos($note, 'ORDINE PERMANENTE DI BONIFICO') !== false
            && stripos($note, $selfName) !== false;
    }

    private function note(BankTransaction $transaction): string
    {
        $remittance = $transaction->raw['remittance_information'] ?? null;

        if (is_array($remittance)) {
            return implode(' ', array_map(fn (mixed $part): string => is_scalar($part) ? (string) $part : '', $remittance));
        }

        return is_string($remittance) ? $remittance : '';
    }
}
