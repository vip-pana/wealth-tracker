# Stage 1: base PHP
FROM php:8.4-cli AS base

RUN apt-get update && apt-get install -y \
    git curl zip unzip libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite pcntl \
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

COPY docker/prod-entrypoint.sh /usr/local/bin/prod-entrypoint
RUN chmod +x /usr/local/bin/prod-entrypoint

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
