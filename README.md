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

**Requirements**: PHP 8.4+, Composer, Node.js 22+, pnpm

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
| Frontend tests | `pnpm run test` |
| Backend tests | `php artisan test` |
| Enable the pre-push hook | `composer setup-hooks` |

## Pre-push checks

A pre-push git hook (`.githooks/pre-push`) runs the full gate and **blocks
the push** if anything fails. Enable it once per clone:

```bash
composer setup-hooks   # sets git config core.hooksPath .githooks
```

The hook (and CI, on every push/PR) runs, in order:

1. `composer lint` — Laravel Pint style check
2. `composer analyse` — PHPStan level 9
3. `pnpm run lint` — ESLint
4. `pnpm run typecheck` — TypeScript strict
5. `pnpm run test` — Vitest (frontend)
6. `php artisan test` — PHPUnit (backend)

To bypass once (discouraged): `git push --no-verify`.

## Optional integrations

- **Database & backups** — [docs/database.md](docs/database.md)
- **Enable Banking** (open-banking bank balances) —
  [docs/enable-banking-usage.md](docs/enable-banking-usage.md)

### Scalable Capital sync

Syncs Scalable Capital broker balances into assets via the **official Scalable
CLI** (`sc`), which runs headless in the container with a file-backed session.
Optional: skip it entirely by leaving `SCALABLE_CLI_ENABLED` unset.

Enable it in `.env`, then clear the config:

```
SCALABLE_CLI_ENABLED=true
SCALABLE_CASH_CATEGORY_ID=<id of your "Liquidità" category>
SCALABLE_CASH_ASSET_NAME=Scalable Liquidità
```

```bash
docker compose exec app php artisan config:clear
```

Set each holding's **ISIN** in its asset (edit modal) so the sync can match it;
uninvested cash (derived as total − securities − crypto) is written to the asset
named by `SCALABLE_CASH_ASSET_NAME` in the given category.

**Login**: from the app, **Impostazioni → Scalable Capital → "Collega /
Riconnetti"** — a link and device code appear; open the link, enter the code and
complete 2FA on any device. The session is long-lived; reconnect the same way
when it lapses, or from a terminal with `docker exec -it wealth-tracker-app-1 sc
login`. The daily 06:00 price job then reads balances automatically.
