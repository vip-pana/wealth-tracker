<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Transactions\ClassifyBankTransactions as ClassifyAction;
use Illuminate\Console\Command;

class ClassifyBankTransactions extends Command
{
    protected $signature = 'bank:classify-transactions';

    protected $description = 'Auto-classify bank transactions (income/expense/transfer), leaving manual rows intact';

    public function handle(ClassifyAction $classify): int
    {
        $this->info('Classifying bank transactions...');

        $result = $classify->run();

        $this->info(sprintf('Done. Classified %d transaction(s).', $result['classified']));

        return Command::SUCCESS;
    }
}
