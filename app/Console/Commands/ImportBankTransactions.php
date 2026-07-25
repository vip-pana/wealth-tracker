<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Transactions\ImportBankTransactions as ImportAction;
use Illuminate\Console\Command;

class ImportBankTransactions extends Command
{
    protected $signature = 'bank:import-transactions';

    protected $description = 'Import Enable Banking transactions for all active bank accounts';

    public function handle(ImportAction $import): int
    {
        $this->info('Importing bank transactions...');

        $result = $import->run();

        $this->info(sprintf(
            'Done. Imported %d transaction(s) across %d account(s).',
            $result['imported'],
            $result['accounts'],
        ));

        return Command::SUCCESS;
    }
}
