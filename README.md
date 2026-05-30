# Wealth Tracker

Personal wealth tracker: log assets by category each month, take monthly snapshots, and visualize net worth over time with charts and forecasts. No authentication — single-user local app.

## Stack

- **Backend**: Laravel 12, PHP 8.4, SQLite
- **Frontend**: React 19 + TypeScript (strict), Inertia.js v2, Tailwind CSS, Recharts
- **Package manager**: pnpm

## Running with Docker (recommended)

```bash
cp .env.example .env
docker compose up
```

The app will be available at [http://localhost:8080](http://localhost:8080).

The SQLite database is persisted in a named Docker volume (`sqlite_data`).

### Accessing from a phone on the same Wi-Fi

Start with `DEV_HOST` set to your computer's LAN IP so the Vite assets are
served from that address instead of `localhost`:

```bash
DEV_HOST=192.168.1.7 docker compose up   # use your machine's LAN IP
```

Then open `http://192.168.1.7:8080` from the phone (same Wi-Fi, computer
awake, container running). Without `DEV_HOST` it defaults to `localhost`
for normal desktop use.

## Running locally

**Requirements**: PHP 8.2+, Composer, Node.js 22+, pnpm

```bash
cp .env.example .env
composer install
pnpm install
php artisan migrate
composer dev        # starts all services (PHP server + Vite)
```

The app will be available at [http://localhost:8000](http://localhost:8000).

## Development commands

| Task | Command |
|---|---|
| Start dev server | `composer dev` |
| Run migrations | `php artisan migrate` |
| PHP lint check | `composer lint` |
| PHP lint fix | `composer lint:fix` |
| PHP static analysis | `composer analyse` |
| JS lint check | `pnpm run lint` |
| JS lint fix | `pnpm run lint:fix` |
| TypeScript check | `pnpm run typecheck` |
| Run tests | `php artisan test` |

## Pre-push checks

Run in order before pushing:

1. `composer lint` — Laravel Pint style check
2. `composer analyse` — PHPStan level 9
3. `pnpm run lint` — ESLint
4. `pnpm run typecheck` — TypeScript strict

All four must pass.
