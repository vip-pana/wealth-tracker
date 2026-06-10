<?php

declare(strict_types=1);

namespace App\Http\Clients;

/**
 * A read-only source of Scalable Capital portfolio data. Implemented by both the
 * official CLI client and the unofficial proxy client so FetchScalableBalance can
 * pick one per run without caring which it is.
 */
interface ScalableSource
{
    /**
     * Current positions, each with its live market value, or null on any failure.
     *
     * @return list<array{isin: string, name: string, value: float}>|null
     */
    public function positions(): ?array;

    /**
     * The uninvested cash balance in EUR, or null on any failure.
     */
    public function cashBalance(): ?float;
}
