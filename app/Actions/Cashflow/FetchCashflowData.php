<?php

declare(strict_types=1);

namespace App\Actions\Cashflow;

use App\Actions\Action;
use App\Actions\Advisor\ComputePositionReturns;
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
        private readonly ComputePositionReturns $computePositionReturns,
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
            // Salary credits carry a "STIPENDIO" marker in the note.
            'is_salary' => stripos($this->note($t), 'STIPENDIO') !== false,
        ]);

        return [
            'accounts' => $accounts->values(),
            'transactions' => $transactions->values(),
            'month' => $month,
            'availableMonths' => $this->fetchTransactionMonths->run(),
            'monthlySalary' => $this->computeMonthlySalary->run(),
            'emergencyFund' => [
                'buffer' => round($this->computeBuffer->run(), 2),
                'targetMonths' => InvestorProfile::query()->first()?->emergency_fund_months,
                'monthlyExpense' => $this->computeMonthlyExpense->run(),
            ],
            // Whole-history and ISIN-deduplicated: unlike everything else here
            // it is not scoped to $month, so the positions card says so.
            'positionReturns' => $this->computePositionReturns->run(),
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
