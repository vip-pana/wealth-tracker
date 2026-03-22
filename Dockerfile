# Stage 1: base PHP
FROM php:8.4-cli AS base

RUN apt-get update && apt-get install -y \
    git curl zip unzip libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite pcntl \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

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

RUN mkdir -p storage/app \
    && touch storage/app/database.sqlite \
    && php artisan config:cache 2>/dev/null || true \
    && php artisan route:cache 2>/dev/null || true \
    && php artisan view:cache 2>/dev/null || true

EXPOSE 8000

CMD ["sh", "-c", "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000"]
