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

Backups run **inside the app**, not on the host. `App\Actions\Backup\CreateBackup`
takes an atomic `VACUUM INTO` snapshot, encrypts it, and uploads it to a remote
disk. Everything is driven by `config/backup.php`:

| Env var | Meaning |
| --- | --- |
| `BACKUP_DIR` | Local staging directory (default `/app/backups`) |
| `BACKUP_DISK` | Filesystem disk for the off-site copy. Empty = local-only |
| `BACKUP_DISK_PATH` | Key prefix on that disk (default `wealth-tracker`) |
| `BACKUP_ENCRYPTION_KEY` | Required when `BACKUP_DISK` is set. `php artisan backup:keygen` |
| `BACKUP_RETENTION_DAYS` | Retention window, applied locally and remotely (default 30) |
| `BACKUP_MAX_AGE_DAYS` | Staleness threshold for the health check (default 3) |

The scheduler (`routes/console.php`) runs `backup:run` nightly at 03:00 and
`backup:health` at 09:00. Both need a running scheduler and queue worker —
`composer dev`, the dev container command, and `docker/prod-entrypoint.sh` all
start them.

### Why this replaced the host-side script

The previous design was a `launchd` job invoking `scripts/backup.sh`, which
shelled out to `docker run keinos/sqlite3` and wrote into a Proton Drive folder
that the desktop client then synced. It worked, but it only worked *on that Mac*:
the schedule, the Docker dependency, and the off-site step all lived on the host.
Hosting the app anywhere else silently left it with no backups.

The in-app path has no host dependencies, so backups behave identically wherever
the app runs. Note the tradeoff: Proton Drive has no S3 API, so the off-site
target moved to an S3-compatible provider (see `.env.example` for the Cloudflare
R2 settings).

### Encryption

Archives are `AES-256-CBC` with an `HMAC-SHA256` over the ciphertext, streamed in
1 MiB chunks (`App\Actions\Backup\EncryptBackup`). The MAC is verified in full
*before* any plaintext is written, so a tampered or truncated archive is rejected
rather than half-restored. Uploaded objects carry a `.sqlite.enc` suffix.

Backups hold the entire financial history and land on third-party storage, so a
configured remote disk without a key is a hard error, never a silent plaintext
upload.

> **Keep `BACKUP_ENCRYPTION_KEY` somewhere other than the backup destination.**
> Losing it makes every archive unrecoverable, and rotating it orphans the
> existing ones.

### Restoring from a backup

```bash
php artisan backup:restore --list                    # see what is stored
php artisan backup:restore wealth-tracker/database-20260804-030000.sqlite.enc
```

The command downloads the archive, verifies its MAC, decrypts it, and writes a
plain `.sqlite` file into `storage/app/`. It accepts a local path too, so an
archive fetched by hand restores without remote credentials.

To put it live:

```bash
docker compose stop app
rm -f ~/wealth-tracker-data/database.sqlite-wal \
      ~/wealth-tracker-data/database.sqlite-shm   # <- not optional
cp <restored file> ~/wealth-tracker-data/database.sqlite
docker compose up -d app
```

> **Deleting the `-wal` and `-shm` files is required.** The database runs in WAL
> mode, so `database.sqlite-wal` holds committed pages that are not yet in the
> main file. Copying only the `.sqlite` leaves that stale WAL in place and SQLite
> replays it over the restored database, mixing two unrelated states. The result
> is corrupted B-trees while the row data stays readable — so nothing looks wrong
> until a query walks a broken index and the app dies with
> `database disk image is malformed`. See the playbook in
> `context/debugging/playbooks.jsonl` for the repair.

Never copy the database while the container is running.

### Why not sync the live DB to a cloud folder directly

SQLite writes the file in-place while the app is running. A sync client copying
it mid-write would upload a corrupted snapshot or lock the file, which can crash
the app. Only `VACUUM INTO` / `sqlite3 .backup` output is safe to ship.

### Running a manual backup

Settings → "Backup ora" (`POST /backup`), or `php artisan backup:run`. Both take
the same path as the nightly job, including the upload and retention pruning.

### Automatic backup on every snapshot

Saving a snapshot (`POST /snapshots`) dispatches a queued `BackupDatabase` job
running the same action. It is queued, not synchronous — the snapshot save
returns immediately and a failed backup is logged (`Log::warning`) without
breaking the snapshot.

### When backups stop happening

A failed run raises a `backup_failed` notification. The quieter failure — runs
that never happen at all, because the scheduler is down — is caught by
`backup:health`, which raises `backup_stale` when the newest off-site archive is
older than `BACKUP_MAX_AGE_DAYS`. Both auto-resolve on the next success. This
matters because the default failure mode of a backup system is silence.
