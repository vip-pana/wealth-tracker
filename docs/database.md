# Database & Backup

## Where the DB lives

The dev SQLite database lives on the host at `~/wealth-tracker-data/database.sqlite` and is bind-mounted into the container at `/app/storage/app/database.sqlite`.

This is intentional: a bind mount means the file is a normal file on macOS that you can see in Finder, copy, zip, or recover with Time Machine. A named Docker volume would hide it inside the Docker VM and make it invisible to host-level tools.

To override the host path (e.g. for another machine), export `WEALTH_TRACKER_DATA_DIR` before `docker compose up`.

## How `php artisan test` is kept safe

The container exports `DB_CONNECTION` and `DB_DATABASE` as process env vars. These override `phpunit.xml` `<env>` entries and even `.env.testing`, so naively running tests inside the container would point `RefreshDatabase` at the dev DB and wipe it.

`tests/TestCase.php::createApplication()` forces `database.default = sqlite` and `database.connections.sqlite.database = :memory:` at application boot, before any test bootstraps. This guarantees the test suite never touches the dev DB regardless of container env.

Do **not** remove this override. Do **not** rely on `phpunit.xml` `<env>` or `.env.testing` alone — they are silently ignored when the host env wins.

## Backups

The canonical backup script lives in the repo at `scripts/backup.sh`. It reads two required env vars:

- `WEALTH_TRACKER_DATA_DIR` — directory containing `database.sqlite`
- `WEALTH_TRACKER_BACKUP_DIR` — destination directory (typically a cloud-synced folder)

Optional: `WEALTH_TRACKER_RETENTION_DAYS` (default 30).

On this machine, a thin wrapper at `~/wealth-tracker-data/backup.sh` exports the local paths and invokes `scripts/backup.sh`. A `launchd` job (`~/Library/LaunchAgents/com.example.wealth-tracker-backup.plist`) runs the wrapper every day at 03:00.

The script:

1. Uses `sqlite3 .backup` (via the `keinos/sqlite3` Docker image) to make an atomic snapshot, safe even if the app is writing.
2. Stages the snapshot on a local path first, then copies it into the destination — `mv` into a sandboxed cloud-sync folder fails on macOS, so `cp` is used.
3. Writes the final file as `database-YYYYMMDD-HHMMSS.sqlite`.
4. Deletes backups older than the retention window.

Proton Drive syncs the backup folder to the cloud automatically.

### Why not sync the live DB to Proton Drive directly

SQLite writes the file in-place while the app is running. A cloud sync client (Proton Drive, Dropbox, iCloud) copying the file mid-write would upload a corrupted snapshot or lock the file, which can crash the app. Only backups produced by `sqlite3 .backup` are safe to sync.

### Restoring from a backup

1. Stop the container: `docker compose stop app`
2. Copy the desired backup from Proton Drive over the live DB:
   ```
   cp ~/Library/CloudStorage/ProtonDrive-.../wealth-tracker-backups/database-YYYYMMDD-HHMMSS.sqlite \
      ~/wealth-tracker-data/database.sqlite
   ```
3. Start the container: `docker compose up -d app`

### Running a manual backup

There are two ways:

- From the UI: Settings → "Backup ora" button. Uses the in-app endpoint `POST /backup`, which runs SQLite's `VACUUM INTO` against the directory mounted at `/app/backups` (set via `BACKUP_DIR`, defaulting to the same Proton Drive folder used by the nightly job).
- From the host shell: `~/wealth-tracker-data/backup.sh`. Logs land at `~/wealth-tracker-data/backup.log` and `backup.error.log`.

Both paths apply the same retention window.

### Automatic backup on every snapshot

Saving a snapshot (`POST /snapshots`) dispatches a queued `BackupDatabase` job that runs the same `VACUUM INTO` backup. It is queued, not synchronous — the snapshot save returns immediately and a failed backup is logged (`Log::warning`) without breaking the snapshot. Requires a queue worker (`php artisan queue:listen`, already part of `composer dev` and the container start command).

### Managing the launchd job

```
launchctl list | grep wealth                                          # check status
launchctl unload ~/Library/LaunchAgents/com.example.wealth-tracker-backup.plist   # disable
launchctl load   ~/Library/LaunchAgents/com.example.wealth-tracker-backup.plist   # re-enable
```
