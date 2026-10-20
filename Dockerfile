# Stage 1: base PHP
#
# FrankenPHP rather than php:8.4-cli, because the production stage needs a real
# web server and `php artisan serve` is not one — see docker/Caddyfile. The
# image is the official PHP image with the server embedded, so the CLI, the
# extension tooling and `php artisan` all behave exactly as before.
FROM dunglas/frankenphp:1-php8.4 AS base

# The image declares its own entrypoint, which would swallow every `command:`
# in docker-compose.yml and the prod entrypoint below. We run the server from
# our own supervisor, so reset it once here for both stages that build on this.
ENTRYPOINT []

RUN apt-get update && apt-get install -y \
    git curl zip unzip libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite pcntl opcache \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Scalable CLI: official read-only broker access (sc broker overview --json).
# Pinned + checksum-verified against the SHA256SUMS published with the release.
ARG SCALABLE_CLI_VERSION=v0.2.0
RUN set -eux; \
    arch="$(dpkg --print-architecture)"; \
    case "$arch" in \
      arm64) target="aarch64" ;; \
      amd64) target="x86_64" ;; \
      *) echo "unsupported arch: $arch" >&2; exit 1 ;; \
    esac; \
    base="https://github.com/ScalableCapital/scalable-cli/releases/download/${SCALABLE_CLI_VERSION}"; \
    asset="sc-${SCALABLE_CLI_VERSION}-linux-${target}-gnu.tar.gz"; \
    cd /tmp; \
    curl -fsSL -o "$asset" "${base}/${asset}"; \
    curl -fsSL -o SHA256SUMS "${base}/sc-${SCALABLE_CLI_VERSION}-SHA256SUMS"; \
    grep -F "  ${asset}" SHA256SUMS | sha256sum -c -; \
    tar -xzf "$asset"; \
    install -m 0755 "sc-${SCALABLE_CLI_VERSION}-linux-${target}-gnu/sc" /usr/local/bin/sc; \
    rm -rf /tmp/sc-* /tmp/SHA256SUMS; \
    sc --version

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction

COPY . .
RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app \
    && chmod -R 775 bootstrap/cache storage \
    && composer dump-autoload --optimize

# Stage 2: node build (for production assets)
FROM node:22-slim AS node-build

RUN npm install -g pnpm

WORKDIR /app

COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

COPY . .
RUN pnpm build

# Stage 3: dev (code mounted as volume, hot reload)
FROM base AS dev

# Install dev composer deps (needed for artisan commands, pail, etc.)
RUN composer install --no-scripts --no-interaction

# Install Node + pnpm for running Vite dev server inside container
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g pnpm \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

EXPOSE 8000 5173

# Stage 4: prod (assets baked in, no source mount)
FROM base AS prod

COPY --from=node-build /app/public/build ./public/build

# No `touch database.sqlite` here: the database must come from the mounted data
# volume. Creating it at build time bakes an empty database into the image, which
# a failed mount then serves as if the app were simply new. The entrypoint fails
# loudly instead.
RUN mkdir -p storage/app

# The web server's configuration and PHP's. Both are baked in rather than
# mounted: the image is pulled, not built, on the machines that run it, so
# anything the server needs has to travel with it.
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/php-production.ini $PHP_INI_DIR/conf.d/zz-production.ini

COPY docker/prod-entrypoint.sh /usr/local/bin/prod-entrypoint
RUN chmod +x /usr/local/bin/prod-entrypoint

# The account the app actually runs as. The entrypoint starts as root, makes the
# mounted directories writable by this account and re-execs itself through gosu,
# so every long-lived process — web server, queue worker, scheduler — is
# unprivileged. See docker/prod-entrypoint.sh.
#
# 1000 is only a starting point: the entrypoint moves this account onto whatever
# uid owns the bind-mounted data directory, because the image is pulled from
# ghcr rather than built on the host and so cannot be told the host's uid at
# build time.
#
# Only the runtime-writable paths are handed over. /app itself stays root-owned,
# which leaves the application code read-only to the process serving requests.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends gosu; \
    apt-get clean; rm -rf /var/lib/apt/lists/*; \
    groupadd --gid 1000 appuser; \
    useradd --uid 1000 --gid 1000 --create-home --shell /bin/bash appuser; \
    mkdir -p /app/backups /home/appuser/.config/scalable-cli; \
    chown -R appuser:appuser /app/storage /app/bootstrap/cache /home/appuser; \
    gosu appuser true

# The Scalable CLI and Laravel both resolve paths through HOME; left at root's,
# `sc login` would write its session where the unprivileged app cannot read it.
ENV HOME=/home/appuser

# The commit this image was built from. .git is excluded from the build context
# (see .dockerignore), so the running app cannot work out its own version any
# other way — and without it the update check has nothing to compare against.
# docker-compose.prod.yml fills this in from `git rev-parse HEAD`.
ARG GIT_COMMIT=unknown
ENV APP_COMMIT=$GIT_COMMIT

EXPOSE 8000

# Runs the web server, the queue worker and the scheduler together — see the
# entrypoint for why all three are required.
CMD ["prod-entrypoint"]
