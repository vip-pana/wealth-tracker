<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Actions\Transactions\ImportBankTransactions;
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

    private function transaction(string $date = '2026-07-01'): BankTransaction
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
            'amount' => -15.0, 'currency' => 'EUR', 'booking_date' => $date,
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

    public function test_marks_the_months_pending_rows_reviewed_without_any_change(): void
    {
        // The common case: the classifier was right, nothing was edited, but the
        // month has been gone through and must not come back.
        $tx = $this->transaction();

        $this->patch('/cashflow', ['month' => '2026-07-01'])->assertRedirect();

        $this->assertNotNull($tx->fresh()->reviewed_at);
        // Reviewing is not overriding: the classifier may still fix this row.
        $this->assertFalse($tx->fresh()->is_manual);
    }

    public function test_marks_rows_hidden_by_a_filter_too(): void
    {
        // The client sends the month, not a list of ids, so a row the active
        // filter was hiding can't be silently left behind.
        $shown = $this->transaction();
        $hidden = $this->transaction();

        $this->patch('/cashflow', [
            'changes' => [
                ['id' => $shown->id, 'flow_type' => BankTransaction::FLOW_INCOME, 'excluded' => false],
            ],
            'month' => '2026-07-01',
        ])->assertRedirect();

        $this->assertNotNull($hidden->fresh()->reviewed_at);
    }

    public function test_leaves_other_months_alone(): void
    {
        $july = $this->transaction('2026-07-15');
        $june = $this->transaction('2026-06-15');

        $this->patch('/cashflow', ['month' => '2026-07-01'])->assertRedirect();

        $this->assertNotNull($july->fresh()->reviewed_at);
        $this->assertNull($june->fresh()->reviewed_at);
    }

    public function test_does_not_re_stamp_an_already_reviewed_row(): void
    {
        // reviewed_at stays the date of the first look, so it keeps telling you
        // what arrived after it.
        $tx = $this->transaction();
        $first = now()->subDays(3);
        $tx->update(['reviewed_at' => $first]);

        $this->patch('/cashflow', ['month' => '2026-07-01'])->assertRedirect();

        $this->assertSame($first->format('Y-m-d H:i:s'), $tx->fresh()->reviewed_at?->format('Y-m-d H:i:s'));
    }

    public function test_rejects_a_submission_with_neither_changes_nor_month(): void
    {
        $this->patch('/cashflow', [])->assertSessionHasErrors('changes');
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

    public function test_saves_emergency_fund_target_months(): void
    {
        $this->patch('/cashflow/emergency-fund', ['target_months' => 9])->assertRedirect();

        $this->assertDatabaseHas('investor_profile', ['emergency_fund_months' => 9]);
    }

    public function test_rejects_a_non_positive_target(): void
    {
        $this->patch('/cashflow/emergency-fund', ['target_months' => 0])
            ->assertSessionHasErrors('target_months');
    }

    public function test_sync_reports_the_imported_count(): void
    {
        $import = $this->createMock(ImportBankTransactions::class);
        $import->method('run')->willReturn(['imported' => 12, 'accounts' => 2]);
        $this->app->instance(ImportBankTransactions::class, $import);

        $this->post('/cashflow/sync')
            ->assertRedirect()
            ->assertSessionHas('success', '12 transazioni sincronizzate su 2 conti.');
    }

    public function test_sync_reports_when_no_account_could_be_synced(): void
    {
        $import = $this->createMock(ImportBankTransactions::class);
        $import->method('run')->willReturn(['imported' => 0, 'accounts' => 0]);
        $this->app->instance(ImportBankTransactions::class, $import);

        $this->post('/cashflow/sync')
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
