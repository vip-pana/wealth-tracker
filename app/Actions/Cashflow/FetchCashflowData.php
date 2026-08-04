<?php

declare(strict_types=1);

namespace App\Actions\Cashflow;

use App\Actions\Action;
use App\Actions\Dashboard\ComputeEmergencyBuffer;
use App\Actions\Transactions\ComputeMonthlyExpense;
use App\Actions\Transactions\ComputeMonthlySalary;
use App\Actions\Transactions\FetchTransactionMonths;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\InvestorProfile;
use Illuminate\Support\Carbon;

class FetchCashflowData extends Action
{
    public function __construct(
        private readonly ComputeEmergencyBuffer $computeBuffer,
        private readonly ComputeMonthlyExpense $computeMonthlyExpense,
        private readonly ComputeMonthlySalary $computeMonthlySalary,
        private readonly FetchTransactionMonths $fetchTransactionMonths,
        private readonly BuildMonthlyFlowSeries $buildMonthlyFlowSeries,
    ) {}

    /**
     * The page shows one month of transactions at a time. The monthly averages
     * and the emergency-fund coverage are whole-history figures computed
     * server-side, so they stay stable as you page through the months.
     *
     * @return array<string, mixed>
     */
    public function run(string $month): array
    {
        $accounts = BankAccount::query()
            ->orderBy('id')
            ->get()
            ->map(fn (BankAccount $a): array => [
                'id' => $a->id,
                'label' => $a->linked_name ?? $a->name ?? $a->iban,
                'last_sync_at' => $a->last_sync_at?->toIso8601String(),
                'last_sync_status' => $a->last_sync_status,
                'last_sync_error' => $a->last_sync_error,
            ]);

        $start = Carbon::parse($month)->startOfMonth();

        $rows = BankTransaction::query()
            ->whereBetween('booking_date', [$start->format('Y-m-d'), $start->copy()->endOfMonth()->format('Y-m-d')])
            ->orderByDesc('booking_date')
            ->orderByDesc('id')
            ->get();

        $transactions = $rows->map(fn (BankTransaction $t): array => [
            'id' => $t->id,
            'account_id' => $t->bank_account_id,
            'date' => $t->booking_date->format('Y-m-d'),
            'amount' => $t->amount,
            'description' => $this->note($t),
            'flow_type' => $t->flow_type,
            'excluded' => $t->excluded,
            'is_manual' => $t->is_manual,
            // Whether the user has been through this row. The rows are all sent
            // either way — the month's totals are computed over the whole set —
            // and the review dialog is what filters on it.
            'reviewed' => $t->reviewed_at !== null,
            // Salary credits carry a "STIPENDIO" marker in the note.
            'is_salary' => stripos($this->note($t), 'STIPENDIO') !== false,
        ]);

        return [
            'accounts' => $accounts->values(),
            'transactions' => $transactions->values(),
            // Counted off the rows already loaded rather than with a second
            // query: it drives the button that says how much is left to review.
            'pendingReview' => $rows->whereNull('reviewed_at')->count(),
            'month' => $month,
            'availableMonths' => $this->fetchTransactionMonths->run(),
            'monthlySalary' => $this->computeMonthlySalary->run(),
            'emergencyFund' => [
                'buffer' => round($this->computeBuffer->run(), 2),
                'targetMonths' => InvestorProfile::query()->first()?->emergency_fund_months,
                'monthlyExpense' => $this->computeMonthlyExpense->run(),
            ],
            // Whole history, so the shown month can be read against the others.
            'monthlyFlow' => $this->buildMonthlyFlowSeries->run(),
        ];
    }

    private function note(BankTransaction $t): string
    {
        $remittance = $t->raw['remittance_information'] ?? null;

        if (is_array($remittance)) {
            return trim(implode(' ', array_map(fn (mixed $p): string => is_scalar($p) ? (string) $p : '', $remittance)));
        }

        if (is_string($remittance) && $remittance !== '') {
            return $remittance;
        }

        return $t->counterparty ?? $t->description ?? '';
    }
}
