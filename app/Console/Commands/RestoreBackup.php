<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Backup\CreateBackup;
use App\Actions\Backup\EncryptBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RestoreBackup extends Command
{
    protected $signature = 'backup:restore
        {archive? : Remote key or local path of the archive to restore}
        {--output= : Where to write the decrypted database (default: alongside the archive)}
        {--list : List the available off-site backups and exit}';

    protected $description = 'Decrypt a backup archive back into a usable SQLite file';

    public function handle(EncryptBackup $encrypt): int
    {
        /** @var string|null $diskName */
        $diskName = config('backup.disk');
        /** @var string $configuredPrefix */
        $configuredPrefix = config('backup.disk_path');
        $prefix = trim($configuredPrefix, '/');

        if ($this->option('list')) {
            return $this->listArchives($diskName, $prefix);
        }

        /** @var string|null $archive */
        $archive = $this->argument('archive');

        if ($archive === null) {
            $this->error('Which archive? Pass a key or path, or use --list to see what is stored.');

            return Command::FAILURE;
        }

        // A local path wins when it exists, so an archive already pulled down by
        // hand restores without needing the remote credentials.
        if (is_file($archive)) {
            $encryptedPath = $archive;
            $cleanup = false;
        } else {
            if ($diskName === null || $diskName === '') {
                $this->error("No remote disk configured and no local file at {$archive}.");

                return Command::FAILURE;
            }

            $disk = Storage::disk($diskName);

            if (! $disk->exists($archive)) {
                $this->error("Archive not found on disk [{$diskName}]: {$archive}");

                return Command::FAILURE;
            }

            $encryptedPath = storage_path('app/'.basename($archive));
            $stream = $disk->readStream($archive);

            if ($stream === null) {
                $this->error("Unable to download {$archive}.");

                return Command::FAILURE;
            }

            $local = fopen($encryptedPath, 'wb');
            if ($local === false) {
                fclose($stream);
                $this->error("Unable to stage the download at {$encryptedPath}.");

                return Command::FAILURE;
            }

            stream_copy_to_stream($stream, $local);
            fclose($stream);
            fclose($local);

            $cleanup = true;
            $this->line("Downloaded {$archive}");
        }

        /** @var string|null $output */
        $output = $this->option('output');
        $target = $output ?? $this->defaultTarget($encryptedPath);

        $encrypt->decrypt($encryptedPath, $target);

        if ($cleanup) {
            @unlink($encryptedPath);
        }

        $this->newLine();
        $this->info('Restored to: '.$target);
        $this->newLine();
        $this->line('  To put it live, stop the app and copy this file over the');
        $this->line('  database, then start the app again. Never swap the file');
        $this->line('  underneath a running container.');
        $this->newLine();

        return Command::SUCCESS;
    }

    private function listArchives(?string $diskName, string $prefix): int
    {
        if ($diskName === null || $diskName === '') {
            $this->error('No remote backup disk configured (set BACKUP_DISK).');

            return Command::FAILURE;
        }

        $files = Storage::disk($diskName)->files($prefix);
        sort($files);

        if ($files === []) {
            $this->warn('No backups stored on disk ['.$diskName.'].');

            return Command::SUCCESS;
        }

        $this->newLine();
        foreach ($files as $file) {
            $this->line('  '.$file);
        }
        $this->newLine();

        return Command::SUCCESS;
    }

    private function defaultTarget(string $encryptedPath): string
    {
        $name = basename($encryptedPath);

        if (str_ends_with($name, CreateBackup::ARCHIVE_SUFFIX)) {
            $name = substr($name, 0, -strlen(CreateBackup::ARCHIVE_SUFFIX)).'.sqlite';
        } else {
            $name .= '.restored.sqlite';
        }

        return storage_path('app/'.$name);
    }
}
