<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cashflow;

use App\Actions\Dashboard\ComputeEmergencyBuffer;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\InvestorProfile;
use Inertia\Inertia;
use Inertia\Response;

class IndexController extends Controller
{
    public function __construct(
        private readonly ComputeEmergencyBuffer $computeBuffer,
    ) {}

    public function __invoke(): Response
    {
        $accounts = BankAccount::query()
            ->orderBy('id')
            ->get()
            ->map(fn (BankAccount $a): array => [
                'id' => $a->id,
                'label' => $a->linked_name ?? $a->name ?? $a->iban,
            ]);

        $rows = BankTransaction::query()
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
            // The monthly-salary card averages these; totals stay client-side so
            // they follow the date filter.
            'is_salary' => stripos($this->note($t), 'STIPENDIO') !== false,
        ]);

        return Inertia::render('Cashflow', [
            'accounts' => $accounts->values(),
            'transactions' => $transactions->values(),
            'emergencyFund' => [
                'buffer' => round($this->computeBuffer->run(), 2),
                'targetMonths' => InvestorProfile::query()->first()?->emergency_fund_months,
            ],
        ]);
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
