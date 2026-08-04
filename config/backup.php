<?php

declare(strict_types=1);

return [
    'directory' => env('BACKUP_DIR', '/app/backups'),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),

    /*
     * Off-site copy. The local directory above only survives the machine it
     * lives on; a remote disk is what makes the backup a backup. When
     * BACKUP_DISK names a configured filesystem disk (e.g. 's3' pointed at
     * Cloudflare R2), every backup is uploaded there and the local file is a
     * staging artifact. Leave it empty to keep the local-only behaviour.
     */
    'disk' => env('BACKUP_DISK'),

    'disk_path' => env('BACKUP_DISK_PATH', 'wealth-tracker'),

    /*
     * Encryption key for uploaded backups: 32 raw bytes, base64-encoded (the
     * same shape as APP_KEY, generated with `php artisan backup:keygen`).
     * A backup on third-party storage holds the full financial history, so an
     * unset key with a remote disk configured is a hard error rather than a
     * silent plaintext upload.
     *
     * Keep this key somewhere other than the backup destination: without it
     * the archives cannot be restored.
     */
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),

    /*
     * Staleness threshold. When the newest successful off-site backup is older
     * than this, the health check raises a notification — the failure mode that
     * matters is not a loud error but months of silence.
     */
    'max_age_days' => (int) env('BACKUP_MAX_AGE_DAYS', 3),
];
