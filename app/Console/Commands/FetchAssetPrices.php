<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PriceFetcherService;
use Illuminate\Console\Command;

class FetchAssetPrices extends Command
{
    protected $signature = 'prices:fetch';

    protected $description = 'Fetch current prices for all assets with a ticker';

    public function handle(PriceFetcherService $fetcher): int
    {
        $this->info('Fetching asset prices...');
        $fetcher->fetchAll();
        $this->info('Done.');

        return Command::SUCCESS;
    }
}
