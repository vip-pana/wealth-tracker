<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Prices\FetchAllPrices;
use Illuminate\Console\Command;

class FetchAssetPrices extends Command
{
    protected $signature = 'prices:fetch';

    protected $description = 'Fetch current prices for all assets with a ticker';

    public function handle(FetchAllPrices $fetchAllPrices): int
    {
        $this->info('Fetching asset prices...');
        $fetchAllPrices->run();
        $this->info('Done.');

        return Command::SUCCESS;
    }
}
