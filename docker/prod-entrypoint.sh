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

DB_PATH="${DB_DATABASE:-/app/storage/app/database.sqlite}"

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
