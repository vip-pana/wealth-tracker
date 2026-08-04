<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackupKeygen extends Command
{
    protected $signature = 'backup:keygen';

    protected $description = 'Generate a BACKUP_ENCRYPTION_KEY for off-site backups';

    public function handle(): int
    {
        $key = base64_encode(random_bytes(32));

        $this->newLine();
        $this->line('  BACKUP_ENCRYPTION_KEY='.$key);
        $this->newLine();
        $this->warn('  Store this key somewhere other than the backup destination.');
        $this->warn('  Without it the archives cannot be restored, and rotating it');
        $this->warn('  leaves every existing backup unreadable.');
        $this->newLine();

        return Command::SUCCESS;
    }
}
