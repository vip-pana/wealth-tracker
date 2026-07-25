<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Transactions\ImportScalableTransactions as ImportAction;
use Illuminate\Console\Command;

class ImportScalableTransactions extends Command
{
    protected $signature = 'transactions:import-scalable';

    protected $description = 'Import the Scalable Capital transaction history into tracked assets';

    public function handle(ImportAction $import): int
    {
        $this->info('Importing Scalable transactions...');

        $result = $import->run();

        if ($result === null) {
            $this->error('Scalable CLI is disabled or has no active session. Connect it in Settings, then retry.');

            return Command::FAILURE;
        }

        $this->info(sprintf('Done. Imported %d transaction(s).', $result['imported']));

        if ($result['skipped_isins'] !== []) {
            $this->warn('Skipped ISINs no tracked asset carries: '.implode(', ', $result['skipped_isins']));
        }

        return Command::SUCCESS;
    }
}
