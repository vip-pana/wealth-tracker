<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Backup\CreateBackup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BackupDatabase implements ShouldQueue
{
    use Queueable;

    public function handle(CreateBackup $createBackup): void
    {
        try {
            $createBackup->run();
        } catch (RuntimeException $e) {
            Log::warning('Automatic backup after snapshot failed', ['reason' => $e->getMessage()]);
        }
    }
}
