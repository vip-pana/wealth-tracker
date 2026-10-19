#!/bin/bash
#
# Production entrypoint. The app is not just a web server: the AI advisor
# replies from a queued job, and four scheduled commands keep the data fresh
# (prices, bank transactions, the daily snapshot, and the Scalable session
# keep-alive that stops the broker login from lapsing). Serving HTTP alone
# would leave the advisor permanently silent and every source going stale, so
# all three processes run here.
#
set -eu

APP_USER=appuser
APP_HOME=/home/appuser

DB_PATH="${DB_DATABASE:-/app/storage/app/database.sqlite}"
DATA_DIR="$(dirname "$DB_PATH")"
BACKUP_DIR="${BACKUP_DIR:-/app/backups}"

# ─── Privileged preparation, then drop ────────────────────────────────────────
#
# This script runs twice. The first pass is root and does only what root is
# needed for: making the mounted directories writable by an unprivileged
# account. It then re-execs itself as that account, so PID 1 — and with it the
# web server, the queue worker and the scheduler — spends the container's whole
# life without privileges. A flaw reached over HTTP is then not a flaw reached
# as root.
#
# Skipped entirely when the container is already started as a non-root user
# (compose `user:`), which is then simply honoured as-is.
if [ "$(id -u)" = "0" ]; then
    # The data directory is a bind mount, so it is owned by a host uid that has
    # nothing to do with the one baked into the image. Adopting that uid is what
    # lets the app write the database on any host without asking the operator to
    # chown their own data. PUID/PGID override it when the guess is wrong.
    #
    # The fallback keeps a missing mount from dying here on a cryptic `stat`
    # error: a few lines down there is a message that actually explains it.
    TARGET_UID="${PUID:-$(stat -c '%u' "$DATA_DIR" 2>/dev/null || echo 1000)}"
    TARGET_GID="${PGID:-$(stat -c '%g' "$DATA_DIR" 2>/dev/null || echo 1000)}"

    # A root-owned mount is the one owner never to adopt: matching it would put
    # the app back exactly where it started. (Written as `if` rather than
    # `[ … ] && …`: under `set -e` a false test there is a failed command and
    # would kill the container at boot.)
    if [ "$TARGET_UID" = "0" ]; then TARGET_UID=1000; fi
    if [ "$TARGET_GID" = "0" ]; then TARGET_GID=1000; fi

    # --non-unique because the host's uid or gid may already belong to one of
    # the image's own accounts (gid 100 is `users` on Debian). Without it
    # groupmod refuses with "GID already exists" and the container never starts
    # — on the hosts least able to afford a surprise.
    [ "$TARGET_GID" = "$(id -g "$APP_USER")" ] || groupmod --non-unique --gid "$TARGET_GID" "$APP_USER"
    [ "$TARGET_UID" = "$(id -u "$APP_USER")" ] || usermod --non-unique --uid "$TARGET_UID" "$APP_USER"

    # Only what is written at runtime. The application code stays root-owned and
    # read-only to the app, so a write primitive cannot rewrite the code that
    # serves the next request.
    #
    # This does recurse into the mounted data directory (/app/storage/app), and
    # that is deliberate: it is what repairs a database, a queue file or an
    # enable-banking.pem left behind by the days when the app ran as root. In
    # the ordinary case the uid was adopted from that very directory, so it
    # changes nothing.
    chown -R "$TARGET_UID:$TARGET_GID" /app/storage /app/bootstrap/cache "$APP_HOME"

    # The staging directory is its own mount and may have been created by a
    # different account. Not recursive: existing archives keep the ownership —
    # and the 0600 mode — they were written with.
    if [ -d "$BACKUP_DIR" ]; then
        chown "$TARGET_UID:$TARGET_GID" "$BACKUP_DIR"
    fi

    echo "Dropping privileges: running as $APP_USER ($TARGET_UID:$TARGET_GID)"
    exec gosu "$APP_USER" "$0" "$@"
fi

# ─── Everything below here runs unprivileged ──────────────────────────────────

# The Scalable CLI defaults to the OS keyring, which reaches for D-Bus — absent
# in a container, so `sc login` dies with "Platform secure storage failure:
# DBus error". Point it at file-backed storage before anything can call it. The
# file lives in the scalable-cli volume, so it is written once and survives
# container recreation; an existing one is left alone.
mkdir -p "$APP_HOME/.config/scalable-cli"
if [ ! -f "$APP_HOME/.config/scalable-cli/config.toml" ]; then
    printf '%s\n' '[auth]' 'session_backend = "file"' 'signing_key_backend = "file"' \
        > "$APP_HOME/.config/scalable-cli/config.toml"
fi

# The data volume must be mounted before we touch the database. Creating the
# file when it is missing would silently start the app on an empty database —
# it looks like a fresh install rather than a mount that failed, which is the
# worst way to lose a financial history.
if [ ! -f "$DB_PATH" ]; then
    echo "FATAL: no database at $DB_PATH" >&2
    echo "" >&2
    echo "The data volume is probably not mounted. Refusing to start with an" >&2
    echo "empty database, which would look like a working app holding no data." >&2
    echo "" >&2
    echo "For a genuinely new install, create it explicitly first:" >&2
    echo "  touch \"$DB_PATH\"" >&2
    exit 1
fi

if [ ! -w "$DB_PATH" ]; then
    echo "FATAL: database at $DB_PATH is not writable" >&2
    exit 1
fi

# The queue lives in its own SQLite file (the sqlite_queue connection) to keep
# job writes off the single-writer main database. Unlike the main database this
# one holds no user data — it is a work area — so creating it when missing is
# correct rather than dangerous.
QUEUE_DB_PATH="${DB_QUEUE_DATABASE:-/app/storage/app/queue.sqlite}"
[ -f "$QUEUE_DB_PATH" ] || touch "$QUEUE_DB_PATH"

php artisan migrate --force

# On an install with no owner account yet, print the code /setup will ask for.
# This is what makes "read it from the logs" true: the alternative is poking
# around the data directory on a machine the owner reaches over ssh. Quiet and
# successful on every later start, when an account already exists.
php artisan setup:token

# Config is cached at build time, before any runtime env var exists. Rebuild the
# caches now so the running container reflects the env it was actually given.
php artisan config:cache
php artisan route:cache
php artisan view:cache

terminate() {
    echo "Shutting down…"
    kill 0
    exit 0
}
trap terminate INT TERM

# The queue worker is restarted in a loop: --max-time recycles it hourly to
# shed leaked memory, and an outright crash must not silently end the advisor.
(
    while true; do
        php artisan queue:work --tries=1 --timeout=660 --max-time=3600 --sleep=3
        echo "[queue] worker exited, restarting in 2s"
        sleep 2
    done
) &

php artisan schedule:work &

php artisan serve --host=0.0.0.0 --port=8000 &

# Exit as soon as any one of them dies, so the container's restart policy can
# do its job instead of leaving a half-running app that still answers HTTP.
wait -n
echo "A supervised process exited; stopping the container." >&2
terminate
