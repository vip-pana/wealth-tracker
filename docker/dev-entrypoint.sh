#!/bin/sh
#
# Development entrypoint. Everything here used to be a single `sh -c` line in
# docker-compose.yml, and that is how the quick-start ended up unable to reach a
# working instance at all: a step that silently did nothing looked exactly like
# a step that worked. A script can `set -eu`, so a step that fails stops the
# container instead of leaving the next one to fail further downstream.
#
# Unlike docker/prod-entrypoint.sh this one stays root — see the note at the top
# of docker-compose.yml — and it *creates* what is missing instead of refusing
# to start. That is the whole difference between the two: in development a
# missing database is a new checkout, in production it is a failed mount.
set -eu

ENV_FILE=/app/.env
DB_PATH="${DB_DATABASE:-/app/storage/app/database.sqlite}"
QUEUE_DB_PATH="${DB_QUEUE_DATABASE:-/app/storage/app/queue.sqlite}"

# The Scalable CLI defaults to the OS keyring, which reaches for D-Bus — absent
# in a container, so `sc login` dies with "Platform secure storage failure:
# DBus error". Point it at file-backed storage before anything can call it. The
# file lives in the scalable-cli volume, so an existing one is left alone.
mkdir -p /root/.config/scalable-cli
if [ ! -f /root/.config/scalable-cli/config.toml ]; then
    printf '%s\n' '[auth]' 'session_backend = "file"' 'signing_key_backend = "file"' \
        > /root/.config/scalable-cli/config.toml
fi

# Both SQLite files, not just the application one. The queue has its own
# database (the sqlite_queue connection) to keep the worker's polling off the
# single-writer app database, and nothing creates it on demand: the very first
# `migrate` dies on create_jobs_table with "Database file at path
# [/app/storage/app/queue.sqlite] does not exist", and keeps dying on every
# restart. Harmless to create, unlike the app database in production: the queue
# file is a work area and holds no user data.
touch "$DB_PATH" "$QUEUE_DB_PATH"

# APP_KEY. `.env.example` ships it empty, so a clean checkout has none, and
# generating it from a shell in the already-running container is a false
# positive: `env_file` baked the empty value into this container's environment
# when it was created, and an environment variable wins over the .env file for
# the whole life of the container (which is also why `restart` does not pick up
# a new one and `down && up` does). So generate it here, before anything boots
# the framework, and export it over the empty value this process inherited.
grep -q '^APP_KEY=' "$ENV_FILE" || printf 'APP_KEY=\n' >> "$ENV_FILE"
if ! grep -q '^APP_KEY=.' "$ENV_FILE"; then
    php artisan key:generate --force
fi
APP_KEY="$(sed -n 's/^APP_KEY=//p' "$ENV_FILE" | head -n 1)"
export APP_KEY

php artisan migrate --force

# Print the code /setup asks for, the way production does. Without this the
# token is only minted lazily, on the first request that redirects to /setup —
# so `docker compose logs app | grep "Setup code"`, which is the first thing the
# README tells you to run, matches nothing on a fresh install.
php artisan setup:token

pnpm install --frozen-lockfile --ignore-scripts

# `php artisan serve` is a supervisor: it hands requests to a `php -S` child
# that inherits only ServeCommand::$passthroughVariables, so the container's own
# environment does not reach the process actually serving pages. The variables
# this stack sets and the web process needs are registered in
# AppServiceProvider::boot(); without that, DB_DATABASE never arrives and every
# page is a 500 on a database path that does not exist.
exec pnpm dlx concurrently \
    -c '#93c5fd,#c4b5fd,#a78bfa,#fb7185,#fdba74' \
    --names=server,queue,scheduler,logs,vite \
    'php artisan serve --host=0.0.0.0' \
    'while true; do php artisan queue:work --tries=1 --timeout=660 --max-time=3600 --sleep=3; echo "[queue] worker exited, restarting in 2s"; sleep 2; done' \
    'php artisan schedule:work' \
    'php artisan pail --timeout=0' \
    'pnpm dev --host'
