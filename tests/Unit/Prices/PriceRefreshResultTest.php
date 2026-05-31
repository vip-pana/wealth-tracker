<?php

declare(strict_types=1);

namespace Tests\Unit\Prices;

use App\Actions\Prices\PriceRefreshResult;
use PHPUnit\Framework\TestCase;

class PriceRefreshResultTest extends TestCase
{
    public function test_empty_result_reports_nothing(): void
    {
        $r = new PriceRefreshResult;

        $this->assertSame(0, $r->updatedCount());
        $this->assertSame(0, $r->failedCount());
        $this->assertFalse($r->hasFailures());
        $this->assertTrue($r->nothingUpdated());
    }

    public function test_merge_concatenates_updated_and_failed(): void
    {
        $a = new PriceRefreshResult(updated: ['BTC'], failed: ['ISAC']);
        $b = new PriceRefreshResult(updated: ['SGLD'], failed: ['EUNH']);

        $merged = $a->merge($b);

        $this->assertSame(['BTC', 'SGLD'], $merged->updated);
        $this->assertSame(['ISAC', 'EUNH'], $merged->failed);
        $this->assertSame(2, $merged->updatedCount());
        $this->assertSame(2, $merged->failedCount());
        $this->assertTrue($merged->hasFailures());
        $this->assertFalse($merged->nothingUpdated());
    }

    public function test_merge_does_not_mutate_operands(): void
    {
        $a = new PriceRefreshResult(updated: ['BTC']);
        $b = new PriceRefreshResult(updated: ['SGLD']);

        $a->merge($b);

        $this->assertSame(['BTC'], $a->updated);
        $this->assertSame(['SGLD'], $b->updated);
    }

    public function test_all_failed_is_nothing_updated_with_failures(): void
    {
        $r = new PriceRefreshResult(failed: ['BTC', 'ISAC']);

        $this->assertTrue($r->nothingUpdated());
        $this->assertTrue($r->hasFailures());
    }
}
