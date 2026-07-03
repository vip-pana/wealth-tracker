<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Prices\FetchEtfPrice;
use App\Models\Asset;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchEtfPriceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Yahoo carries fundProfile (the TER) only on the exchange-suffixed symbol
     * (ISAC.MI), not the bare ticker the price comes from. The backfill must try
     * every candidate, so the bare symbol returning an empty fundProfile does
     * not stop it from finding the TER on .MI.
     */
    public function test_backfills_ter_from_the_milan_suffixed_symbol(): void
    {
        $cat = Category::factory()->create();
        Asset::factory()->create([
            'category_id' => $cat->id,
            'ticker' => 'ISAC',
            'expense_ratio' => null,
            'date' => '2026-07-01',
        ]);

        Http::fake([
            // Price resolves on the bare symbol.
            'query1.finance.yahoo.com/v8/finance/chart/ISAC?*' => Http::response([
                'chart' => ['result' => [['meta' => ['regularMarketPrice' => 10.0]]]],
            ]),
            // Crumb handshake.
            'fc.yahoo.com*' => Http::response('', 200, ['Set-Cookie' => 'A3=token; Domain=.yahoo.com']),
            'query1.finance.yahoo.com/v1/test/getcrumb' => Http::response('crumb123'),
            // fundProfile: empty on the bare symbol, present on .MI.
            'query1.finance.yahoo.com/v10/finance/quoteSummary/ISAC?*' => Http::response([
                'quoteSummary' => ['result' => [['fundProfile' => []]]],
            ]),
            'query1.finance.yahoo.com/v10/finance/quoteSummary/ISAC.MI?*' => Http::response([
                'quoteSummary' => ['result' => [[
                    'fundProfile' => ['feesExpensesInvestment' => ['annualReportExpenseRatio' => ['raw' => 0.002]]],
                ]]],
            ]),
        ]);

        app(FetchEtfPrice::class)->run('ISAC');

        $this->assertEqualsWithDelta(
            0.2,
            (float) Asset::where('ticker', 'ISAC')->value('expense_ratio'),
            0.0001,
        );
    }

    public function test_leaves_ter_null_when_no_candidate_carries_it(): void
    {
        $cat = Category::factory()->create();
        Asset::factory()->create([
            'category_id' => $cat->id,
            'ticker' => 'ISAC',
            'expense_ratio' => null,
            'date' => '2026-07-01',
        ]);

        Http::fake([
            'query1.finance.yahoo.com/v8/finance/chart/*' => Http::response([
                'chart' => ['result' => [['meta' => ['regularMarketPrice' => 10.0]]]],
            ]),
            'fc.yahoo.com*' => Http::response('', 200, ['Set-Cookie' => 'A3=token; Domain=.yahoo.com']),
            'query1.finance.yahoo.com/v1/test/getcrumb' => Http::response('crumb123'),
            'query1.finance.yahoo.com/v10/finance/quoteSummary/*' => Http::response([
                'quoteSummary' => ['result' => [['fundProfile' => []]]],
            ]),
        ]);

        app(FetchEtfPrice::class)->run('ISAC');

        $this->assertNull(Asset::where('ticker', 'ISAC')->value('expense_ratio'));
    }
}
