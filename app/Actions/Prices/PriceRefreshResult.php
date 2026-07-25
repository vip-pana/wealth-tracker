<?php

declare(strict_types=1);

namespace App\Actions\Prices;

final readonly class PriceRefreshResult
{
    /**
     * @param  list<string>  $updated  tickers (or wallet labels) refreshed successfully
     * @param  list<string>  $failed  tickers (or wallet labels) that could not be refreshed
     */
    public function __construct(
        public array $updated = [],
        public array $failed = [],
    ) {}

    public function merge(self $other): self
    {
        return new self(
            [...$this->updated, ...$other->updated],
            [...$this->failed, ...$other->failed],
        );
    }

    public function updatedCount(): int
    {
        return count($this->updated);
    }

    public function failedCount(): int
    {
        return count($this->failed);
    }

    public function hasFailures(): bool
    {
        return $this->failed !== [];
    }

    public function nothingUpdated(): bool
    {
        return $this->updated === [];
    }
}
