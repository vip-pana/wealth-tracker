<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\BankConnection;
use App\Models\BankTransaction;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashflowUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private int $seq = 0;

    private function transaction(): BankTransaction
    {
        $n = ++$this->seq;
        $connection = BankConnection::create([
            'aspsp_name' => 'Revolut', 'aspsp_country' => 'IT',
            'state' => 'state-'.$n, 'session_id' => 'sess-1',
            'status' => BankConnection::STATUS_ACTIVE,
        ]);
        $account = $connection->accounts()->create(['uid' => 'acc-'.$n, 'iban' => 'IT0'.$n]);

        return BankTransaction::create([
            'bank_account_id' => $account->id, 'external_id' => 'tx-'.$n,
            'amount' => -15.0, 'currency' => 'EUR', 'booking_date' => '2026-07-01',
            'flow_type' => BankTransaction::FLOW_EXPENSE,
        ]);
    }

    public function test_updates_flow_type_and_marks_manual(): void
    {
        $tx = $this->transaction();

        $this->patch('/cashflow', [
            'changes' => [
                ['id' => $tx->id, 'flow_type' => BankTransaction::FLOW_TRANSFER, 'excluded' => false],
            ],
        ])->assertRedirect();

        $tx->refresh();
        $this->assertSame(BankTransaction::FLOW_TRANSFER, $tx->flow_type);
        $this->assertTrue($tx->is_manual);
    }

    public function test_updates_several_transactions_at_once(): void
    {
        $a = $this->transaction();
        $b = $this->transaction();

        $this->patch('/cashflow', [
            'changes' => [
                ['id' => $a->id, 'flow_type' => BankTransaction::FLOW_EXPENSE, 'excluded' => true],
                ['id' => $b->id, 'flow_type' => BankTransaction::FLOW_TRANSFER, 'excluded' => false],
            ],
        ])->assertRedirect();

        $this->assertTrue($a->fresh()->excluded);
        $this->assertSame(BankTransaction::FLOW_TRANSFER, $b->fresh()->flow_type);
    }

    public function test_rejects_an_invalid_flow_type(): void
    {
        $tx = $this->transaction();

        $this->patch('/cashflow', [
            'changes' => [
                ['id' => $tx->id, 'flow_type' => 'nonsense', 'excluded' => false],
            ],
        ])->assertSessionHasErrors('changes.0.flow_type');
    }
}
