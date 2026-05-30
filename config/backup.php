<?php

declare(strict_types=1);

return [
    'directory' => env('BACKUP_DIR', '/app/backups'),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 2),
];
