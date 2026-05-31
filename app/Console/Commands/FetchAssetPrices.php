<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Prices\FetchAllPrices;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchAssetPrices extends Command
{
    protected $signature = 'prices:fetch';

    protected $description = 'Fetch current prices for all assets with a ticker';

    public function handle(FetchAllPrices $fetchAllPrices): int
    {
        $this->info('Fetching asset prices...');
        $result = $fetchAllPrices->run();
        $this->info(sprintf('Done. Updated %d, failed %d.', $result->updatedCount(), $result->failedCount()));

        if ($result->hasFailures()) {
            Log::warning('Scheduled price fetch had failures', ['failed' => $result->failed]);
        }

        return Command::SUCCESS;
    }
}
