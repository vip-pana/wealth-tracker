#!/bin/bash
#
# Atomic SQLite backup → cloud-synced folder.
#
# Required env vars:
#   WEALTH_TRACKER_DATA_DIR   directory containing database.sqlite (host path)
#   WEALTH_TRACKER_BACKUP_DIR destination directory (e.g. a Proton Drive folder)
#
# Optional env vars:
#   WEALTH_TRACKER_RETENTION_DAYS  how many days to keep backups (default: 30)
#
# The script uses `sqlite3 .backup` via the keinos/sqlite3 Docker image to
# produce a consistent snapshot even while the app is writing. The snapshot
# is staged on a local path first, then copied into the destination folder —
# this avoids `mv` failures into sandboxed cloud-sync folders on macOS.
#
set -euo pipefail

: "${WEALTH_TRACKER_DATA_DIR:?WEALTH_TRACKER_DATA_DIR not set}"
: "${WEALTH_TRACKER_BACKUP_DIR:?WEALTH_TRACKER_BACKUP_DIR not set}"

RETENTION_DAYS="${WEALTH_TRACKER_RETENTION_DAYS:-30}"
DB_PATH="$WEALTH_TRACKER_DATA_DIR/database.sqlite"
STAGING_DIR="$WEALTH_TRACKER_DATA_DIR/backup-staging"

if [ ! -f "$DB_PATH" ]; then
    echo "DB not found at $DB_PATH" >&2
    exit 1
fi

mkdir -p "$WEALTH_TRACKER_BACKUP_DIR"
mkdir -p "$STAGING_DIR"

TS=$(date +%Y%m%d-%H%M%S)
STAGED_FILE="$STAGING_DIR/database-$TS.sqlite"
FINAL_FILE="$WEALTH_TRACKER_BACKUP_DIR/database-$TS.sqlite"

docker run --rm \
    -v "$WEALTH_TRACKER_DATA_DIR":/src \
    -v "$STAGING_DIR":/dst \
    keinos/sqlite3 \
    sqlite3 /src/database.sqlite ".backup /dst/database-$TS.sqlite"

cp "$STAGED_FILE" "$FINAL_FILE"
rm "$STAGED_FILE"

echo "Backup created: $FINAL_FILE"

find "$WEALTH_TRACKER_BACKUP_DIR" -name "database-*.sqlite" -type f -mtime +$RETENTION_DAYS -delete -print 2>/dev/null || true
find "$STAGING_DIR" -name "database-*.sqlite" -type f -mtime +1 -delete 2>/dev/null || true
