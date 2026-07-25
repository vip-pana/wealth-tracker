<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Transactions\ClassifyBankTransactions;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassifyBankTransactionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('transactions.self_transfer_name', 'Rossi');
        config()->set('transactions.transfer_markers', ['Apple Pay Top-Up by *0000']);
    }

    private function account(): BankAccount
    {
        $connection = BankConnection::create([
            'aspsp_name' => 'Revolut',
            'aspsp_country' => 'IT',
            'state' => 'state-1',
            'session_id' => 'sess-1',
            'status' => BankConnection::STATUS_ACTIVE,
        ]);

        return $connection->accounts()->create(['uid' => 'acc-1', 'iban' => 'IT01']);
    }

    /**
     * @param  string|list<string>  $note
     */
    private function tx(string $externalId, float $amount, string|array $note, bool $manual = false, ?string $flowType = null): BankTransaction
    {
        return BankTransaction::create([
            'bank_account_id' => $this->account()->id,
            'external_id' => $externalId,
            'amount' => $amount,
            'currency' => 'EUR',
            'booking_date' => '2026-07-01',
            'is_manual' => $manual,
            'flow_type' => $flowType,
            'raw' => ['remittance_information' => $note],
        ]);
    }

    public function test_marks_revolut_pos_topup_as_transfer(): void
    {
        $tx = $this->tx('t1', -100.0, ['PAGAMENTO POS EFFETTUATO IL 25/04/2026 ... PRESSO Revolut**9999* Dublin']);

        app(ClassifyBankTransactions::class)->run();

        $this->assertSame(BankTransaction::FLOW_TRANSFER, $tx->fresh()->flow_type);
    }

    public function test_marks_standing_order_to_self_as_transfer(): void
    {
        $tx = $this->tx('t2', -300.0, ['ORDINE PERMANENTE DI BONIFICO Bonifico a favore di: Mario Rossi']);

        app(ClassifyBankTransactions::class)->run();

        $this->assertSame(BankTransaction::FLOW_TRANSFER, $tx->fresh()->flow_type);
    }

    public function test_marks_apple_pay_topup_as_transfer(): void
    {
        $tx = $this->tx('t3', 100.0, ['Apple Pay Top-Up by *0000']);

        app(ClassifyBankTransactions::class)->run();

        $this->assertSame(BankTransaction::FLOW_TRANSFER, $tx->fresh()->flow_type);
    }

    public function test_marks_payment_from_self_as_transfer(): void
    {
        $tx = $this->tx('t4', 300.0, ['Budget di spese personali mensili', 'Payment from Rossi Mario']);

        app(ClassifyBankTransactions::class)->run();

        $this->assertSame(BankTransaction::FLOW_TRANSFER, $tx->fresh()->flow_type);
    }

    public function test_payment_from_a_friend_is_income_not_transfer(): void
    {
        $tx = $this->tx('t5', 30.0, ['Payment from Lorenzo Germano']);

        app(ClassifyBankTransactions::class)->run();

        $this->assertSame(BankTransaction::FLOW_INCOME, $tx->fresh()->flow_type);
    }

    public function test_negative_amount_is_expense(): void
    {
        $tx = $this->tx('t6', -15.0, ['PAGAMENTO TRAMITE POS SAMSARA BEACH RICCIONE']);

        app(ClassifyBankTransactions::class)->run();

        $this->assertSame(BankTransaction::FLOW_EXPENSE, $tx->fresh()->flow_type);
    }

    public function test_positive_amount_is_income(): void
    {
        $tx = $this->tx('t7', 1726.0, ['STIPENDIO O PENSIONE ... ACME S.P.A.']);

        app(ClassifyBankTransactions::class)->run();

        $this->assertSame(BankTransaction::FLOW_INCOME, $tx->fresh()->flow_type);
    }

    public function test_manual_rows_are_left_intact(): void
    {
        $tx = $this->tx('t8', -100.0, ['PRESSO Revolut**9999*'], manual: true, flowType: BankTransaction::FLOW_EXPENSE);

        app(ClassifyBankTransactions::class)->run();

        // The note screams "transfer", but the user pinned it as an expense.
        $this->assertSame(BankTransaction::FLOW_EXPENSE, $tx->fresh()->flow_type);
    }

    public function test_never_sets_excluded(): void
    {
        $tx = $this->tx('t9', -15.0, ['Coffee']);

        app(ClassifyBankTransactions::class)->run();

        $this->assertFalse($tx->fresh()->excluded);
    }
}
